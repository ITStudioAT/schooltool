import {
    deleteTeachers,
    index,
    store,
    update,
    upload,
} from '@/actions/App/Http/Controllers/Admin/TeachersListController'

export const teachersListApi = Object.freeze({
    index: () => index.url(),
    store: () => store.url(),
    update: (teacher) => update.url(teacher),
    deleteTeachers: () => deleteTeachers.url(),
    upload: () => upload.url(),
})
