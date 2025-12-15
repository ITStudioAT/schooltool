# Queue Auto-Recovery System

Dieses Dokument beschreibt das automatische Queue-Überwachungs- und Wiederherstellungssystem für SchoolTool.

## Übersicht

Das System bietet automatische Erkennung und Behebung von Queue-Worker-Ausfällen durch:

1. **Automatische Überwachung**: Laravel Scheduler prüft jede Minute den Queue-Status
2. **Automatischer Neustart**: Bei erkannten Problemen wird der Worker automatisch neu gestartet
3. **Manuelle Steuerung**: Admin-Dashboard zur manuellen Verwaltung
4. **Metriken**: Echtzeit-Überwachung von wartenden, verarbeitenden und fehlgeschlagenen Jobs

## Komponenten

### 1. QueueHealthCheck Command

**Datei**: `app/Console/Commands/QueueHealthCheck.php`

Dieser Artisan-Command überprüft die Queue-Gesundheit:

```bash
# Nur Prüfung (ohne Neustart)
php artisan queue:health-check

# Automatischer Neustart bei Ausfall
php artisan queue:health-check --restart
```

**Funktionen**:
- Erkennt ob Queue Worker läuft
- Identifiziert "hängende" Jobs (>5 Minuten in Bearbeitung)
- Startet Worker automatisch neu (mit --restart Option)
- Cross-Platform kompatibel (Windows & Linux)
- Logging aller Aktionen

### 2. Scheduled Task

**Datei**: `routes/console.php`

Der Scheduler führt die Gesundheitsprüfung automatisch aus:

```php
Schedule::command('queue:health-check --restart')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
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

Supervisor stellt sicher, dass Queue Worker permanent läuft.

**SSH zu Cloudways Server verbinden**:
```bash
ssh master@[SERVER-IP] -p [PORT]
```

**Supervisor Config erstellen**:
```bash
sudo nano /etc/supervisor/conf.d/schooltool-worker.conf
```

**Config-Inhalt**:
```ini
[program:schooltool-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/master/applications/[APP-NAME]/public_html/artisan queue:work database --sleep=3 --tries=1 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=master
numprocs=1
redirect_stderr=true
stdout_logfile=/home/master/applications/[APP-NAME]/public_html/storage/logs/worker.log
stopwaitsecs=3600
```

**Wichtig**: Pfade anpassen!
- `[APP-NAME]` durch tatsächlichen Cloudways App-Namen ersetzen
- `database` ist der Queue-Connection Name (siehe `.env`)

**Supervisor neu laden**:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start schooltool-worker:*
```

**Status prüfen**:
```bash
sudo supervisorctl status
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
sudo supervisorctl status schooltool-worker:*
```

**Logs prüfen**:
```bash
tail -f storage/logs/worker.log
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

# Mit automatischem Neustart
php artisan queue:health-check --restart

# Fehlgeschlagene Jobs wiederholen
php artisan queue:retry all

# Queue-Status anzeigen
php artisan queue:work --once
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
sudo supervisorctl restart schooltool-worker:*
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

Bei hoher Last mehrere Worker starten:

**Supervisor Config**:
```ini
numprocs=3  # Statt 1
```

**Neu laden**:
```bash
sudo supervisorctl restart schooltool-worker:*
```

### Queue Prioritäten

Verschiedene Queues für unterschiedliche Prioritäten:

```php
// High priority queue
dispatch(new ImportantJob())->onQueue('high');

// Default queue
dispatch(new RegularJob());

// Low priority queue
dispatch(new BackgroundJob())->onQueue('low');
```

**Supervisor Config anpassen**:
```ini
command=php artisan queue:work database --queue=high,default,low
```

### Timeout erhöhen

Für lange laufende Jobs:

```ini
# In supervisor config
--timeout=300  # 5 Minuten

# In Job-Klasse
public $timeout = 300;
```

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

- **Laravel Horizon**: Für Redis-Queue (nicht aktuell verwendet)
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
3. Command manuell testen: `php artisan queue:health-check --restart`
4. Supervisor Status prüfen: `sudo supervisorctl status`

---

**Letzte Aktualisierung**: 2025-12-15
**Version**: 1.0.0
