---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables,app/Models,app/Services/StudentsTimetables,resources/js/pages/admin/studentsTimetables}/**'
---

# Pages Admin Students Timetables

## Studienform nicht aus Kompaktunterricht ableiten
Fächerimporte und Fachpläne verwenden `study_program` mit `normalstudium` oder `kompaktstudium`. Das ist unabhängig von `Kompaktunterricht` (Unterrichtsform/Klassenregel Q–V) und darf daraus weder abgeleitet noch damit benannt werden. Bestehende untypisierte Fachzeilen und Stundenplan-Verbraucher gelten rückwärtskompatibel als Normalstudium; Kompaktstudium wird nur über einen expliziten Programmscope gelesen.
