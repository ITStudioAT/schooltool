---
paths:
  - app/Http/Controllers/Admin/Teaching/FileUploadController.php
---

# Controllers Admin Teaching

## Keep Import116 XLSX-only
Import116 accepts XLSX only because the installed OpenSpout/SimpleExcel reader cannot parse legacy XLS reliably. Import166 retains its existing XLS support; do not broaden Import116 validation without adding a proven legacy reader.
