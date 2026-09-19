---
paths:
  - app/Services/AbaLocalDocumentTextExtractor.php
  - 'app/Services/PersonalTeachingBackup*.php'
  - 'app/Services/TeachingBackupArchive*.php'
  - 'app/Services/TeachingBackup*.php'
  - app/Services/TeachingCourseService.php
  - 'app/Services/FeaturePreviewSnapshot*.php'
---

# Services

## Do not install document extractors at runtime
Mammoth is optional and may be invoked only when already installed. Never call npx --yes or another runtime network installer for an uploaded document; keep the local XML/PhpWord fallbacks deterministic.

## Keep personal teaching recovery isolated from school backups
Personal teaching snapshots span all schoolyears of one owner/current school and must never call school-wide TeachingBackupService restoration. Preserve the complete course graph, including orphan children left by legacy course deletion, and reject foreign incoming references before rebuilding with stable IDs. Shared student accounts remain unchanged; missing identities require administrator-approved remapping. Download/import envelopes are authenticated and encrypted with the installation key; never silently promise recovery without that key.

## Keep teaching archives compatible with disabled tmpfile
Cloudways production disables tmpfile(), causing school teaching backup creation and attachment restoration to fail. Use tempnam() plus fopen() for disk-backed streams; register paths before opening and close/unlink them in finally. Keep writer temporary paths until ZIP finalization. TeachingBackupArchiveTest runs archive round-trip and failure cleanup in a child PHP process with disable_functions=tmpfile because PHPUnit itself needs tmpfile().

## Keep school backup entry settings as a complete graph
School archive v3 includes teaching_entry_areas, teaching_entry_grading_parts and teaching_entry_definitions. Remap their owner and foreign IDs before inserting courses; partial restores clone settings to preserve other courses. Continue reading v1/v2, but reject archives whose courses reference omitted entry areas before queueing or creating safety backups. Refresh preview validation from archive contents, never trust cached summary validation.

## Synchronize course student rows once across identity aliases
A TeachingCourseStudent may have user, Import116 and linked-import-user identity keys. Process each stored row once, match all aliases before choosing active/deleted/omitted state, give active selection priority and consume all matched aliases. Looping mutations over the alias map can immediately soft-delete a row just updated or restored. Preserve removal protection across every alias.

## Keep S3 snapshot support exclusive to main exports
Main exports may read configured S3 objects into private snapshot records using conditional streamed reads and complete collision-checked inventory. Retain strict local-only validation for preview import, restore and activation; never enable preview access to live S3 credentials. Abort export on changed fingerprints or failed conditional reads. S3 copying is checked for concurrent changes, not an atomic S3 transaction.
