---
paths:
  - '{app/Http/Requests/Homepage/UpdateStudentTimetableV3StateRequest.php,tests/Feature/StudentsTimetablesStudentLoginTest.php}'
---

# Homepage Feature

## Allow empty manual-draft key lists
Student manual timetable drafts must include selected_course_keys and removed_course_keys, but either list may legitimately be empty. Validate them with present + array rather than required, because Laravel treats an empty required array as invalid and the Vue editor always sends both lists.
