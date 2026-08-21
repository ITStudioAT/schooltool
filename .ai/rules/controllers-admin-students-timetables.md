---
paths:
  - '{app/Jobs/Teaching/Import116Job.php,app/Models/Import116.php,app/Services/StudentsTimetables/RecognitionImportService.php,app/Services/StudentsTimetables/StudentTimetableStudySelectionRefreshService.php,app/Http/Controllers/Admin/StudentsTimetables/**}'
---

# Controllers Admin Students Timetables

## Studienauswahl als Import-Snapshot speichern
Die in Tests V3 angezeigte Studienauswahl kommt aus `import116.study_selection` und wird beim Listen-GET nicht neu berechnet. Aktualisiere den Snapshot für das konkrete Schuljahr erst nach erfolgreichen Import116- oder Anrechnungsimporten; fehlgeschlagene bzw. ausstehende Importe dürfen ihn nicht verändern. Nach dem Löschen eines Anrechnungsimports wird aus verbleibenden abgeschlossenen Importen neu berechnet oder geleert.
