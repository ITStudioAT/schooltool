---
paths:
  - app/Http/Controllers/Admin/Teaching/FileUploadController.php
  - app/Http/Controllers/Admin/Teaching/TeachingBackupController.php
---

# Controllers Admin Teaching

## Keep Import116 XLSX-only
Import116 accepts XLSX only because the installed OpenSpout/SimpleExcel reader cannot parse legacy XLS reliably. Import166 retains its existing XLS support; do not broaden Import116 validation without adding a proven legacy reader.

## Stream teaching backup downloads without fpassthru
Cloudways disables fpassthru(), so Storage::download() fails while transmitting teaching backups. Use readStream() and bounded fread() chunks in streamDownload(), closing in finally. Preserve school/year authorization, ASCII filenames, Content-Length, ZIP/JSON content types and private/no-store headers. Verify actual streamed bytes with php -d disable_functions=fpassthru vendor/pestphp/pest/bin/pest --compact tests/Unit/TeachingBackupDownloadTest.php; these tests require no database mutations.
