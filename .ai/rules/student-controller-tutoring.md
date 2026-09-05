---
paths:
  - '{app/Services/{AdminService,StudentService,RestaurantHomepageAuthService,TutoringService}.php,app/Http/Controllers/{Homepage/HomepageController,Student/StudentController,Tutoring/TutoringController}.php}'
---

# Student Controller Tutoring

## Scope public login password overrides to active school super admins
Public homepage, student, tutoring, and restaurant password logins accept an active super_admin password only from the target user's school, via AdminService::activeSuperAdminPasswordIsValid. Preserve target account eligibility and existing public-login 2FA restrictions; the admin-login override has its separate established 2FA behavior. Never return submitted passwords in login responses, including rejection and prerequisite responses. Match supplied email and school to the target account.
