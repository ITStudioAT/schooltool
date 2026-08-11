---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables,app/Services/StudentsTimetables,resources/js/pages/admin/studentsTimetables}/**'
---

# Js Pages Admin Students Timetables

## Fachpläne ohne Benutzer-Import bereitstellen
Normalstudium und Kompaktstudium werden in der Oberfläche nicht per JSON-Prompt oder Datei importiert. Bestehende Normalstudium-Zeilen bleiben maßgeblich; der verifizierte Kompaktstudium-Plan wird als feste Systemdaten programmspezifisch bereitgestellt. Kompaktstunden sind Kontaktstunden (Vollstudium-Modulstunden geteilt durch zwei), Eigenstudium wird nicht eingerechnet.
