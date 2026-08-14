---
paths:
  - '{app/Models/Import116.php,app/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3StudentInformationController.php,app/Services/StudentsTimetables/StudentTimetableV3StudentInformationService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
---

# Students Timetables Js Pages Admin Students Timetables Timetable V3

## V3-Schulstufenkorrekturen reversibel halten
Eine falsche V3-Schulstufe darf nur auf eine für die klassenbasierte Studienform gültige Stufe geändert werden. Beim ersten Speichern bleiben ursprüngliche Schulstufe und Besuchsjahr separat erhalten. Der Korrekturdialog bietet diesen Originalwert dauerhaft als Rückfalloption an; nach dem Zurücksetzen gilt die Plausibilitätswarnung wieder.
