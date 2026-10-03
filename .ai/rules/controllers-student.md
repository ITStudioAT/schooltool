---
paths:
  - '{app/Http/Controllers/Student/StudentController.php,app/Services/StudentService.php,app/Http/Controllers/Admin/UserController.php,tests/Feature/Controllers/Student/StudentControllerTest.php}'
---

# Controllers Student

## Preserve student roles when blocking imported students
Unterricht login treats a matching Import116 for the requested school and its active schoolyear as first-time approval only when the existing account lacks the student role: reuse the account, activate it and add student, then still require password or single-use code. Existing inactive student accounts remain blocked, including accounts linked by import after an email change. Manual blocking must retain student; removing that role permits import approval again.
