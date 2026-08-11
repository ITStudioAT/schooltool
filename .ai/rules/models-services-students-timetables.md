---
paths:
  - '{app/Enums/StudentTimetableStudyProgram.php,app/Models/Import116.php,app/Services/StudentsTimetables/**}'
---

# Models Services Students Timetables

## Resolve V3 study program from Stundentafel
V3 module recommendations resolve study_program from the explicit recognition CSV `stundentafel`: `AHS-KS-*` is Kompaktstudium and other `AHS-*` values are Normalstudium. Persist this on import116 even for assessment-empty rows; missing, unknown, or conflicting signals fall back to Normalstudium and must never be inferred from the Q–V Kompaktunterricht class rule. Keep recognition module normalization on the Normalstudium rows because recognition `semester` is a module number (1–8), while compact subject-row `semester` is a planning term (1–5).
