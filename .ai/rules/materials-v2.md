---
paths:
  - 'app/Http/Controllers/Admin/MaterialsV2/**'
---

# Materials V2

## Stream material downloads without fpassthru
Cloudways disables fpassthru(), so Storage::response()/download() fail during body transmission. Use readStream() with bounded fread() chunks and close the stream in finally, preserving download headers and ownership checks. Download tests must assert streamed bytes, including multiple chunks; reproduce the hosting restriction by running Pest directly with php -d disable_functions=fpassthru.
