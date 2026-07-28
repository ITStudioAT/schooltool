import { readFileSync } from 'node:fs'

export function readTimetableV2Source() {
    return [
        readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue', 'utf8'),
        readFileSync('resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.css', 'utf8'),
    ].join('\n')
}
