---
paths:
  - 'resources/js/pages/admin/{components/AdminImportCompletionListener.vue,superAdmin/components/TeachersList.vue},tests/ui/unit/components/admin/**/*TeachersList*'
---

# Components Admin

## Keep teacher-list import progress tied to backend completion
Start the visible Lehrerliste import state when FileUpload emits uploadStart, keep it active after the file upload completes, and finish it only when either TeachersListImportFinishedEvent or the authenticated status fallback confirms backend completion. Do not expose the Fertig action while the queued import is still running.
