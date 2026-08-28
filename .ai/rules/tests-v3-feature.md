---
paths:
  - '{app/Services/StudentsTimetables/**,app/Http/Controllers/Admin/StudentsTimetables/**,resources/js/pages/admin/studentsTimetables/testsV3/**,tests/Feature/StudentsTimetablesModuleTest.php}'
---

# Tests V3 Feature

## Gate Tests V3 on imported datasets, not individual student quality
Tests V3 is globally ready when the required Import 116, subject plans, recognition import, course-result snapshots, and refresh state are complete. Individual student data_quality_issues must not set global readiness to false; keep those students visible, skip them individually during the run, and report them separately as invalid data. This supersedes the earlier requirement that every imported school level must be valid before Run Tests is enabled.
