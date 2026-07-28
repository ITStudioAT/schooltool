# Queue-Überwachung mit Horizon

Dieses Dokument beschreibt die Queue-Überwachung und den durch Supervisor abgesicherten Horizon-Betrieb für SchoolTool.

## Übersicht

Das System bietet Queue-Überwachung und Wiederherstellung durch:

1. **Gesundheitsprüfung**: Der Artisan-Command meldet den Horizon-Status
2. **Automatischer Neustart**: Supervisor startet Horizon bei einem Prozessabbruch neu
3. **Manuelle Steuerung**: Admin-Dashboard zur manuellen Verwaltung
4. **Metriken**: `horizon:snapshot` erfasst Queue-Metriken alle fünf Minuten

## Komponenten

### 1. QueueHealthCheck Command

**Datei**: `app/Console/Commands/QueueHealthCheck.php`

Dieser Artisan-Command überprüft die Queue-Gesundheit:

```bash
php artisan queue:health-check
```

**Funktionen**:
- Erkennt ob Queue Worker läuft
- Liefert einen Fehlercode, wenn Horizon nicht aktiv ist
- Verändert keine Prozesse und keine Queue-Daten

### 2. Scheduled Tasks

**Datei**: `routes/console.php`

Der Scheduler erzeugt die Horizon-Metrik-Snapshots:

```php
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping();
```

**Wichtig**: Der Laravel Scheduler muss aktiviert sein!

#### Lokale Entwicklung (Windows)

Für lokale Entwicklung ist der Scheduler nicht kritisch, da Queue-Worker manuell gestartet werden:

```bash
# Composer dev startet bereits queue:listen
composer dev

# Oder manuell:
php artisan queue:listen --tries=1
```

#### Production (Cloudways/Linux)

Der Scheduler MUSS auf dem Server konfiguriert werden:

```bash
# Cron-Job hinzufügen
* * * * * cd /pfad/zu/schooltool && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Backend API (HealthController)

**Datei**: `app/Http/Controllers/Admin/HealthController.php`

API-Endpunkte:

- `GET /api/admin/queue/metrics` - Aktuelle Queue-Statistiken
- `POST /api/admin/queue/retry-failed` - Fehlgeschlagene Jobs neu starten
- `POST /api/admin/queue/restart-worker` - Worker manuell neu starten

### 4. Frontend Store (HealthStore)

**Datei**: `resources/js/stores/admin/HealthStore.js`

Pinia Store für Zustandsverwaltung:

```javascript
// Metriken abrufen
await healthStore.fetchMetrics()

// Fehlgeschlagene Jobs wiederholen
await healthStore.retryFailedJobs()

// Worker neu starten
await healthStore.restartWorker()
```

### 5. Admin Dashboard

**Datei**: `resources/js/pages/admin/index/Index.vue`

Das Dashboard zeigt:
- Queue Worker Status (Läuft/Gestoppt)
- Anzahl wartender Jobs
- Anzahl verarbeitender Jobs
- Anzahl fehlgeschlagener Jobs
- Buttons für manuelle Aktionen
- Zeitstempel des letzten Recovery-Versuchs

## Installation & Konfiguration

### Lokale Entwicklung (Windows)

1. **Queue Worker starten**:
   ```bash
   composer dev
   ```
   Dies startet automatisch `queue:listen`.

2. **Scheduler aktivieren** (optional für Testing):
   ```bash
   php artisan schedule:work
   ```

3. **System testen**:
   - Admin-Dashboard öffnen: `/admin`
   - "Status" Karte zeigt Queue-Metriken
   - Button "Tests durchführen" prüft Queue-Funktionalität

### Production Server (Cloudways)

#### Schritt 1: Supervisor Konfiguration

Supervisor stellt sicher, dass Horizon permanent läuft und alle segmentierten Redis-Queues verarbeitet.

**SSH zu Cloudways Server verbinden**:
```bash
ssh master@[SERVER-IP] -p [PORT]
```

**Supervisor Config erstellen**:
```bash
sudo nano /etc/supervisor/conf.d/schooltool-horizon.conf
```

Die mitgelieferte Datei `supervisor.horizon.conf.example` als Vorlage verwenden und `[APP-NAME]` ersetzen:
```ini
[program:schooltool-horizon]
process_name=%(program_name)s
directory=/home/master/applications/[APP-NAME]/public_html
command=php artisan horizon
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=master
numprocs=1
redirect_stderr=true
stdout_logfile=/home/master/applications/[APP-NAME]/public_html/storage/logs/horizon.log
stopwaitsecs=3600
```

**Wichtig**: Pfade anpassen!
- `[APP-NAME]` durch tatsächlichen Cloudways App-Namen ersetzen
- `.env` muss `QUEUE_CONNECTION=redis` verwenden

**Supervisor neu laden**:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start schooltool-horizon
```

**Status prüfen**:
```bash
sudo supervisorctl status schooltool-horizon
php artisan horizon:status
```

#### Schritt 2: Laravel Scheduler aktivieren

**Cron-Job über Cloudways Panel einrichten**:

1. Cloudways Panel öffnen
2. Application → Cron Job Management
3. Neuen Cron hinzufügen:
   - **Frequency**: Every Minute (`* * * * *`)
   - **Command**:
     ```
     cd /home/master/applications/[APP-NAME]/public_html && php artisan schedule:run >> /dev/null 2>&1
     ```

**Alternative**: SSH Crontab bearbeiten:
```bash
crontab -e
```

Zeile hinzufügen:
```
* * * * * cd /home/master/applications/[APP-NAME]/public_html && php artisan schedule:run >> /dev/null 2>&1
```

#### Schritt 3: Berechtigungen prüfen

```bash
cd /home/master/applications/[APP-NAME]/public_html
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

#### Schritt 4: Testen

**Worker Status prüfen**:
```bash
sudo supervisorctl status schooltool-horizon
php artisan horizon:status
```

**Logs prüfen**:
```bash
tail -f storage/logs/horizon.log
tail -f storage/logs/laravel.log
```

**Queue-Health Command testen**:
```bash
php artisan queue:health-check
```

**Scheduler testen**:
```bash
php artisan schedule:list
```

## Verwendung

### Automatisch

Das System läuft nach Installation vollständig automatisch:

1. **Jede Minute**: Scheduler prüft Queue-Status
2. **Bei Ausfall**: Automatischer Worker-Neustart
3. **Bei hängenden Jobs**: Log-Warnung

### Manuell (Admin Dashboard)

**Metriken anzeigen**:
- Dashboard automatisch alle 30 Sekunden aktualisiert
- Refresh-Button für manuelle Aktualisierung

**Fehlgeschlagene Jobs wiederholen**:
- Button erscheint automatisch bei failed jobs > 0
- Klick startet alle fehlgeschlagenen Jobs neu

**Worker manuell neu starten**:
- Button erscheint automatisch wenn Worker gestoppt
- Klick startet Worker neu und zeigt Bestätigung

### Via Artisan Command

```bash
# Health Check durchführen
php artisan queue:health-check

# Fehlgeschlagene Jobs wiederholen
php artisan queue:retry all

# Queue-Status anzeigen
php artisan horizon:status
```

## Troubleshooting

### Worker läuft nicht

**Problem**: Dashboard zeigt "Worker Status: Gestoppt"

**Lokale Entwicklung**:
```bash
php artisan queue:listen --tries=1
```

**Production mit Supervisor**:
```bash
sudo supervisorctl restart schooltool-horizon
php artisan horizon:status
```

### Scheduler läuft nicht

**Prüfen ob Cron aktiv ist**:
```bash
# Auf Server
crontab -l

# Scheduler-Log prüfen
grep CRON /var/log/syslog
```

**Manuell testen**:
```bash
php artisan schedule:run
```

### Jobs hängen fest

**Problem**: Jobs bleiben in "processing" Status

**Lösung 1**: Hängende Jobs freigeben
```bash
php artisan queue:restart
```

**Lösung 2**: Reserved Jobs zurücksetzen
```sql
UPDATE jobs SET reserved_at = NULL, attempts = 0 WHERE reserved_at IS NOT NULL;
```

**Lösung 3**: Worker neu starten (Dashboard)

### Logs prüfen

```bash
# Laravel Log
tail -f storage/logs/laravel.log | grep -i queue

# Worker Log (nur Supervisor)
tail -f storage/logs/worker.log

# Health Check Events
tail -f storage/logs/laravel.log | grep "Queue health check"
```

### Permission Errors

```bash
# Supervisor kann nicht auf storage zugreifen
sudo chown -R master:www-data storage
chmod -R 775 storage
```

## Performance-Tuning

### Worker-Anzahl erhöhen

Bei hoher Last die `maxProcesses`-Werte der betroffenen Supervisor-Gruppe in `config/horizon.php` erhöhen:

```php
'supervisor-imports' => [
    'minProcesses' => 1,
    'maxProcesses' => 3,
],
```

**Horizon neu laden**:
```bash
php artisan horizon:terminate
```

### Queue Prioritäten

Die Queues `critical`, `notifications`, `default`, `imports`, `materials` und `maintenance` sind in `config/horizon.php` auf getrennte Horizon-Supervisoren aufgeteilt. Jobs werden mit `onQueue()` der passenden Queue zugeordnet.

### Timeout erhöhen

Für lange laufende Jobs müssen Job-Timeout, Horizon-Timeout und Redis-`retry_after` in dieser Reihenfolge bleiben:

`Job timeout < Horizon timeout < REDIS_QUEUE_RETRY_AFTER`

## Monitoring & Alerts

### Log-Monitoring

Queue Health Check schreibt Events:

- `INFO`: Worker läuft normal
- `WARNING`: Hängende Jobs erkannt
- `ERROR`: Worker nicht erreichbar
- `INFO`: Worker erfolgreich neu gestartet

### Email-Benachrichtigungen (optional)

**Eigenen Failure-Handler erstellen**:

```php
// app/Console/Commands/QueueHealthCheck.php
private function notifyAdmin($message) {
    Mail::to(config('mail.admin'))->send(
        new QueueHealthAlert($message)
    );
}
```

### Externe Monitoring-Tools

- **Laravel Telescope**: Entwicklungs-Monitoring (bereits installiert)
- **Uptime Robot**: Externe Health-Check URL überwachen

## Sicherheit

### Rate Limiting

API-Endpunkte sind geschützt:
- Authentifizierung erforderlich (`auth:sanctum`)
- Admin-Rolle erforderlich (`api-allowed:admin`)
- Rate Limit: 600 Requests/Minute

### Command-Line Sicherheit

Health Check Command:
- Prüft Prozesse ohne Shell-Injection Risiko
- Verwendet `exec()` mit gefilterten Ausgaben
- Logs alle Restart-Versuche

## Weitere Ressourcen

- [Laravel Queues Dokumentation](https://laravel.com/docs/queues)
- [Laravel Scheduler Dokumentation](https://laravel.com/docs/scheduling)
- [Supervisor Dokumentation](http://supervisord.org/)
- [Cloudways Queue Documentation](https://support.cloudways.com/en/articles/5124441-how-to-set-up-supervisor-for-laravel-queue-jobs)

## Support

Bei Problemen:

1. Logs prüfen: `storage/logs/laravel.log`
2. Worker Status prüfen: Admin Dashboard
3. Command manuell testen: `php artisan queue:health-check`
4. Supervisor Status prüfen: `sudo supervisorctl status schooltool-horizon`

---

**Letzte Aktualisierung**: 2026-07-28
**Version**: 1.0.0
