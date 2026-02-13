<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Navigation Drawer -->
        <StudentNavigationDrawer v-model="showDrawer" current-route="overview" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/')">Zur Startseite</v-btn>
                    <div class="chip-brand">Unterricht</div>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Unterricht</h1>
                <p class="hero-subtitle">Überblick bewahren. Inhalte kennen. Noten erfahren.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <!-- Courses Section -->
            <div class="content-card">
                <div class="content-head">
                    <v-icon size="26">mdi-book-open-variant</v-icon>
                    <h2>Meine Kurse</h2>
                    <v-chip size="small" class="ml-auto">{{ courses.length }}</v-chip>
                </div>
                <p class="content-copy">Hier findest du alle deine eingeschriebenen Kurse.</p>

                <!-- Courses Grid -->
                <div v-if="courses.length > 0" class="courses-grid">
                    <div v-for="course in courses" :key="course.id" class="course-card" @click="handleCourseClick(course)">
                        <div class="course-header">
                            <v-icon size="24" :color="course.color || '#fd802e'">mdi-school</v-icon>
                            <v-chip size="x-small" :color="course.color || '#fd802e'" variant="flat">{{ course.code }}</v-chip>
                        </div>
                        <h3 class="course-title">{{ course.name }}</h3>
                        <p class="course-teacher">{{ course.teacher }}</p>
                        <div class="course-footer">
                            <span class="course-info">
                                <v-icon size="16">mdi-calendar</v-icon>
                                {{ course.schedule }}
                            </span>
                            <span class="course-info">
                                <v-icon size="16">mdi-account-group</v-icon>
                                {{ course.students }} Schüler
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="courses-empty">
                    <v-icon size="64" color="#fd802e">mdi-book-off-outline</v-icon>
                    <h3>Keine Kurse gefunden</h3>
                    <p>Du bist derzeit in keinen Kursen eingeschrieben.</p>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'
import StudentNavigationDrawer from '../components/StudentNavigationDrawer.vue'
import '../../../../../css/student.css'

export default {
    components: {
        StudentNavigationDrawer,
    },
    async beforeMount() {
        this.studentStore = useStudentStore()

        // Try to fetch current authenticated user
        const isAuthenticated = await this.studentStore.getCurrentUser()

        // Prüfen ob User eingeloggt ist
        if (!isAuthenticated || !this.user) {
            this.$router.push('/student')
        }
    },

    data() {
        return {
            studentStore: null,
            showDrawer: false,
            courses: [
                {
                    id: 1,
                    name: 'Mathematik',
                    code: 'MATH-101',
                    teacher: 'Prof. Dr. Schmidt',
                    schedule: 'Mo, Mi 10:00-11:30',
                    students: 24,
                    color: '#2196F3',
                },
                {
                    id: 2,
                    name: 'Deutsch',
                    code: 'DEU-201',
                    teacher: 'Prof. Müller',
                    schedule: 'Di, Do 14:00-15:30',
                    students: 28,
                    color: '#4CAF50',
                },
                {
                    id: 3,
                    name: 'Englisch',
                    code: 'ENG-101',
                    teacher: 'Prof. Johnson',
                    schedule: 'Mo, Fr 08:00-09:30',
                    students: 22,
                    color: '#FF9800',
                },
                {
                    id: 4,
                    name: 'Physik',
                    code: 'PHY-301',
                    teacher: 'Dr. Weber',
                    schedule: 'Mi, Do 10:00-11:30',
                    students: 20,
                    color: '#9C27B0',
                },
            ],
        }
    },

    computed: {
        ...mapWritableState(useStudentStore, ['user']),

        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },
    },

    methods: {
        handleCourseClick(course) {
            // TODO: Implement course detail view
            // this.$router.push(`/student/course/${course.id}`)
        },
    },
}
</script>
