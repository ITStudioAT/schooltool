import { createRouter, createWebHistory } from 'vue-router'

const Index = () => import('@/pages/homepage/index/Index.vue')
const Impressum = () => import('@/pages/homepage/index/Impressum.vue')
const Register = () => import('@/pages/homepage/register/Register.vue')
const Register_Part2 = () => import('@/pages/homepage/register/RegisterPart2.vue')
const Application_Error = () => import('@/pages/homepage/error/Error.vue')
const TutoringOverview = () => import('@/pages/homepage/tutoring/TutoringOverview.vue')
const Tutoring = () => import('@/pages/homepage/tutoring/Tutoring.vue')
const TutoringResponse = () => import('@/pages/homepage/tutoring/responses/TutoringResponse.vue')
const Student = () => import('@/pages/homepage/student/Student.vue')
const StudentOverview = () => import('@/pages/homepage/student/overview/Overview.vue')
const StudentPassword = () => import('@/pages/homepage/student/password/Password.vue')
const StudentProfile = () => import('@/pages/homepage/student/profile/Profile.vue')
const StudentCourse = () => import('@/pages/homepage/student/overview/myCourse/MyCourse.vue')
const StudentTimetables = () => import('@/pages/homepage/studentsTimetables/StudentTimetables.vue')
const StudentTimetablesOverview = () => import('@/pages/homepage/studentsTimetables/overview/Overview.vue')
const StudentTimetablesOverviewV2 = () => import('@/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue')
const StudentTimetablesPassword = () => import('@/pages/homepage/studentsTimetables/password/Password.vue')
const StudentTimetablesProfile = () => import('@/pages/homepage/studentsTimetables/profile/Profile.vue')
const Cashier = () => import('@/pages/homepage/cashier/Cashier.vue')
const Restaurant = () => import('@/pages/homepage/index/Restaurant.vue')
const Products = () => import('@/pages/homepage/index/Products.vue')
const NotesDemo = () => import('@/pages/homepage/NotesDemo.vue')

const routes = [
    { path: '/', component: Index },
    { path: '/homepage/products', component: Products },
    { path: '/homepage/impressum', component: Impressum },
    { path: '/homepage/register', component: Register },
    { path: '/homepage/register2', component: Register_Part2 },
    { path: '/homepage/error', component: Application_Error },
    { path: '/homepage/tutoring_overview', component: TutoringOverview },
    { path: '/homepage/tutoring', component: Tutoring },
    { path: '/homepage/tutoring_response', component: TutoringResponse },
    { path: '/homepage/student', component: Student },
    { path: '/student', component: Student },
    { path: '/student/overview', component: StudentOverview },
    { path: '/student/password', component: StudentPassword },
    { path: '/student/profile', component: StudentProfile },
    { path: '/student/course/:id', component: StudentCourse },
    { path: '/homepage/students-timetables', component: StudentTimetables },
    { path: '/students-timetables', component: StudentTimetables },
    { path: '/students-timetables/overview', component: StudentTimetablesOverviewV2 },
    { path: '/students-timetables/overview-v1', component: StudentTimetablesOverview },
    { path: '/students-timetables/overview-v2', component: StudentTimetablesOverviewV2 },
    { path: '/students-timetables/create', component: StudentTimetablesOverviewV2 },
    { path: '/students-timetables/create/results', component: StudentTimetablesOverviewV2 },
    { path: '/students-timetables/create/adoption', component: StudentTimetablesOverviewV2 },
    { path: '/students-timetables/password', component: StudentTimetablesPassword },
    { path: '/students-timetables/profile', component: StudentTimetablesProfile },
    { path: '/homepage/cashier', component: Cashier },
    { path: '/homepage/restaurant', component: Restaurant },
    { path: '/homepage/notes-demo', component: NotesDemo },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition

        if (to.path.startsWith('/students-timetables/')) {
            return { left: 0, top: 0 }
        }

        return undefined
    },
})

router.beforeEach(async (to, from, next) => {
    const isStudentArea = to.path === '/student'
        || to.path.startsWith('/student/')
        || to.path === '/homepage/student'

    if (isStudentArea) {
        try {
            const response = await axios.get('/api/admin/impersonation/status')
            if (response.data?.is_students_timetables_restricted === true) {
                next('/students-timetables/overview')
                return
            }
        } catch {
            // The server-side route and API middleware remain authoritative.
        }
    }

    next()
})

export default router
