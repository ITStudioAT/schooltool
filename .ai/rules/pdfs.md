---
paths:
  - resources/views/pdfs/students-timetable-overview.blade.php
  - 'resources/views/pdfs/**'
---

# Pdfs

## Expand timetable subject abbreviations in PDFs
Render known timetable subject abbreviations as uppercase full German subject names in every PDF section (timetable, overlap summary, course directory, and overview). Keep course identifiers unchanged. User-set mappings include BU=Biologie, INF=Informatik, LET/LPT=Lern- und Präsentationstechniken, BOKS=Bosnisch/Kroatisch/Serbisch, GuS=Gesundheit und Soziales, and D_DK/E_DK/M_DK=Deutsch/Englisch/Mathematik.

## Show teaching-form hints in subject overview
The PDF Fächerübersicht includes a Hinweise column. For each subject/day row, show applicable values in this order: Fernunterricht, 2-wöchig A/B, Kompaktunterricht, Block; show a hyphen when none apply.

## Show subject overview dates for irregular courses
In the PDF Fächerübersicht Hinweise column, append every distinct appointment date in chronological dd.mm. format when the row is 2-wöchig or Block. Show the date list once per subject/day row; do not add it for ordinary Fernunterricht or Kompaktunterricht alone.
