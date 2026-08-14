---
paths:
  - '{app/Models/StudentTimetableV3Timetable.php,app/Services/StudentsTimetables/StudentTimetableV3*.php,app/Listeners/StudentTimetableV3SessionSubscriber.php}'
---

# Listeners

## Scope generated V3 timetables to login sessions
Persist generated V3 timetables per server-side login session using only an HMAC of the session ID. Never accept or expose this scope through the client. Logout may delete only the current session's rows; login must preserve unexpired rows from other devices. GET restoration stays read-only and expired or legacy rows are removed by login cleanup and the scheduled model prune.
