import { createRouter, createWebHistory } from 'vue-router'
import Index from '@/pages/homepage/index/Index.vue'
import Impressum from '@/pages/homepage/index/Impressum.vue'
import Register from '@/pages/homepage/register/Register.vue'
import Register_Part2 from '@/pages/homepage/register/RegisterPart2.vue'
import Application_Error from '@/pages/homepage/error/Error.vue'
import TutoringOverview from '@/pages/homepage/tutoring/TutoringOverview.vue'
import Tutoring from '@/pages/homepage/tutoring/Tutoring.vue'
import TutoringResponse from '@/pages/homepage/tutoring/responses/TutoringResponse.vue'
import Student from '@/pages/homepage/student/Student.vue'
import StudentOverview from '@/pages/homepage/student/overview/Overview.vue'
import StudentPassword from '@/pages/homepage/student/password/Password.vue'
import StudentProfile from '@/pages/homepage/student/profile/Profile.vue'
import StudentCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'
import StudentTimetables from '@/pages/homepage/studentsTimetables/StudentTimetables.vue'
import StudentTimetablesOverview from '@/pages/homepage/studentsTimetables/overview/Overview.vue'
import StudentTimetablesOverviewV2 from '@/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue'
import StudentTimetablesPassword from '@/pages/homepage/studentsTimetables/password/Password.vue'
import StudentTimetablesProfile from '@/pages/homepage/studentsTimetables/profile/Profile.vue'
import Cashier from '@/pages/homepage/cashier/Cashier.vue'
import Restaurant from '@/pages/homepage/index/Restaurant.vue'
import Products from '@/pages/homepage/index/Products.vue'
import NotesDemo from '@/pages/homepage/NotesDemo.vue'

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
    { path: '/students-timetables/overview', component: StudentTimetablesOverview },
    { path: '/students-timetables/overview-v2', component: StudentTimetablesOverviewV2 },
    { path: '/students-timetables/password', component: StudentTimetablesPassword },
    { path: '/students-timetables/profile', component: StudentTimetablesProfile },
    { path: '/homepage/cashier', component: Cashier },
    { path: '/homepage/restaurant', component: Restaurant },
    { path: '/homepage/notes-demo', component: NotesDemo },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach(async (to, from, next) => {
    next()
    return
})

export default router
