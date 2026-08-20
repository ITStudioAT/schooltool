---
paths:
  - '{app/Services/StudentsTimetables/**,app/Jobs/Teaching/Import116Job.php,resources/js/pages/admin/studentsTimetables/**}'
---

# Teaching Js Pages Admin Students Timetables

## Importe dürfen Bestandsdaten nur nach fachlichem Preflight verändern
Stundenplan-, Anrechnungs- und Sokrates-Importe müssen vor jeder Bestandsmutation mindestens einen fachlich gültigen Datensatz nachweisen; bei 0 gültigen Datensätzen bleibt der aktive Bestand unverändert. Vor einem Stundenplan-Unimport müssen alle verbleibenden Quelldateien lesbar und replaybar sein, andernfalls wird nichts gelöscht.
