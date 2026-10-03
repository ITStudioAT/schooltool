---
paths:
  - '{app/Services/TeachingWorkMarkdownImport.php,app/Http/Controllers/Admin/Teaching/TeachingCourseController.php}'
---

# Http Controllers Admin Teaching

## Use current linked student-import class for course students
Course participants may have an empty or outdated users.schoolclass while their current Import116 row has the class. Course payloads expose the class from the school/year-scoped linked import without rewriting user accounts. Evaluation matching uses full name and class from a verified current import linked to the same user; otherwise it uses the account identity. Keep course membership, school/year, unique matching and server authorization checks; previews never create or repair accounts.
