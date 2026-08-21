---
paths:
  - '{app/Jobs/Teaching/Import116Job.php,app/Models/Import116.php,app/Services/StudentsTimetables/RecognitionImportService.php,app/Services/StudentsTimetables/StudentTimetableStudySelectionRefreshService.php,app/Http/Controllers/Admin/StudentsTimetables/**,resources/js/pages/admin/studentsTimetables/testsV3/**}'
---

# Students Timetables Tests V3

## Kursresultate als Import-Snapshot speichern
Speichere die normalisierten Kursresultate in import116.course_results erst nach erfolgreichen Import116- oder Anrechnungsimporten und erneuere sie beim Löschen eines Anrechnungsimports aus dem verbleibenden Bestand. B und 1–4 gehören zu completed (status exempt/passed), 5 und N zu negative; andere Notencodes werden ignoriert. Die Studierenden-Übersicht liest nur diesen Snapshot und berechnet die Kategorien nicht im Frontend.
