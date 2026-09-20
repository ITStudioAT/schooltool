---
paths:
  - '{app/Models/MaterialV2Attachment.php,app/Services/Teaching/CurriculumUnitFileService.php,app/Services/MaterialsV2/MaterialV2StorageService.php,app/Services/MaterialsV2/MaterialV2DocumentTextExtractor.php,app/Http/Controllers/Admin/MaterialsV2/MaterialV2ItemController.php}'
---

# Admin Materials V2

## Resolve copied S3 attachment metadata locally in preview
Preview snapshots place copied S3 objects in private local storage. Resolve known stored s3 disk metadata through explicit preview-only disk resolvers for reads, previews, extraction and deletion; preserve stored metadata and main behavior, and never remap unknown disks. Keep the global preview remote-storage block intact: preview consumers must not regain live S3 credentials or connectivity.
