---
paths:
  - '{resources/js/pages/homepage/studentsTimetables/overviewV2/**,tests/ui/unit/components/homepage/studentsTimetables/StudentTimetablesOverviewV2.test.ts}'
---

# Homepage Students Timetables

## Show when the personal timetable was saved
On the OverviewV2 “Mein gespeicherter Stundenplan” card, replace the generic available label with `Gespeichert am dd.mm.yyyy, hh:mm Uhr` using `personal_timetable.adopted_at` formatted in Europe/Vienna. Keep the generic availability text as the fallback for legacy or invalid timestamps.

## Open saved student timetables in the manual editor
This supersedes routing personal or teacher-published timetable cards through overview-v1. Both cards open `/students-timetables/create/adoption` as “Manueller Stundenplan” with `manual_timetable=personal|published`, restore that saved plan as the editable base, and return directly to the student timetable overview.

## Show the personal save time in the manual editor
When `/students-timetables/create/adoption` opens with `manual_timetable=personal`, show `Zuletzt gespeichert: dd.mm.yyyy, hh:mm Uhr` directly below the “Manueller Stundenplan” title using `personal_timetable.adopted_at` in Europe/Vienna. Do not show this personal timestamp for the teacher-published source or invalid legacy timestamps.

## Reveal the save time after saving a teacher timetable
This refines the manual-editor timestamp rule. A teacher-published source starts without the personal save time, even if an older personal timetable exists; after the current editor successfully saves it as the student's personal timetable, immediately show the new `Zuletzt gespeichert` timestamp from the refreshed `personal_timetable.adopted_at`.

## Restore selected modules from published V3 state
Published V3 timetable records may have no top-level `active_course_group_keys`. When opening them in the student manual editor, rebuild the selected courses/modules from `state.moduleSelection.selectedCourseKeys`, merge `state.manualTimetableDraft.selectedCourseKeys`, and exclude its `removedCourseKeys`; retain the top-level keys as the legacy/personal fallback.

## Never rebuild a saved timetable from the current catalog
This refines selected-module restoration for personal and teacher-published manual entry. Always render the exact serialized saved timetable as the base; use V3 module/course keys only for the module summary, duplicate prevention, and removal metadata. Never replace the saved slots with `manualTimetableForCourses`, because current catalog times can differ from the published plan.

## Show the teacher timetable number in manual adoption
When the student manual editor is opened with manual_timetable=published, show the scoped published timetable name as “Nr. xxYYY” directly beside “Manueller Stundenplan”. Hide it for personal and ordinary calculated adoption sources and for legacy published rows without a name.
