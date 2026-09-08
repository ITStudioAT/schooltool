---
paths:
  - '{app/Services/ParentStudentAccessService.php,app/Services/RestaurantHomepageAuthService.php,app/Services/RestaurantBookingService.php,tests/Feature/Controllers/Student/StudentControllerTest.php}'
---

# Student

## End parent access on the eighteenth birthday except restaurant
Parent access to a child's non-restaurant app areas ends at the start of the child's eighteenth birthday, including existing parent sessions. Restaurant is explicitly exempt: parents may continue signing in and ordering for adult children. Restaurant parent-origin child sessions must not grant access to other app areas through the child's ordinary roles.
