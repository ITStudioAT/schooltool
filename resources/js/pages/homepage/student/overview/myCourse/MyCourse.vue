<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Navigation Drawer -->
        <StudentNavigationDrawer v-model="showDrawer" current-route="course" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/student/overview')">Zurück zur Übersicht</v-btn>
                    <div class="chip-brand">Fach</div>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">{{ course?.title || 'Fach wird geladen...' }}</h1>
                <p class="hero-subtitle">Details zum Fach ansehen.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                    <span v-if="user?.schoolclass" class="hero-badge dark">{{ user.schoolclass }}</span>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <!-- Course Details Card -->
            <div class="content-card">
                <div class="content-head">
                    <v-icon size="26">mdi-book-open-variant</v-icon>
                    <h2>Fach-Details</h2>
                    <v-btn class="ml-auto" variant="text" icon="mdi-close" @click="$router.push('/student/overview')" />
                </div>
                <p class="content-copy">Hier werden die Details zum Fach angezeigt.</p>

                <!-- Loading State -->
                <div v-if="loading" class="courses-loading">
                    <v-progress-circular indeterminate color="#fd802e" />
                    <p>Lade Fach-Details...</p>
                </div>

                <!-- Course Details (Placeholder) -->
                <div v-else-if="course" class="profile-info">
                    <div class="profile-section">
                        <h3 class="profile-section-title">
                            <v-icon size="20">mdi-information</v-icon>
                            Allgemeine Informationen
                        </h3>
                        <div class="profile-fields">
                            <div class="profile-field">
                                <label>Fachbezeichnung</label>
                                <div class="profile-value">{{ course.title }}</div>
                            </div>
                            <div class="profile-field" v-if="course.teacher">
                                <label>Lehrkraft</label>
                                <div class="profile-value">{{ course.teacher }}</div>
                            </div>
                            <div class="profile-field" v-if="course.classes && course.classes.length > 0">
                                <label>Klassen</label>
                                <div class="profile-value">{{ course.classes.join(', ') }}</div>
                            </div>
                            <div class="profile-field">
                                <label>Anzahl Schüler</label>
                                <div class="profile-value">{{ course.students_count }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-info-box">
                        <v-icon color="#fd802e" size="24">mdi-information</v-icon>
                        <div>
                            <strong>Hinweis:</strong> Weitere Details wie Noten, Aufgaben und Materialien werden in Kürze verfügbar sein.
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="courses-empty">
                    <v-icon size="64" color="#fd802e">mdi-alert-circle-outline</v-icon>
                    <h3>Fach nicht gefunden</h3>
                    <p>Das angeforderte Fach konnte nicht geladen werden.</p>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'
import { useCourseStore } from '@/stores/student/CourseStore'
import StudentNavigationDrawer from '../../components/StudentNavigationDrawer.vue'
import '../../../../../../css/student.css'

export default {
    components: {
        StudentNavigationDrawer,
    },

    async beforeMount() {
        this.studentStore = useStudentStore()
        this.courseStore = useCourseStore()

        // Check authentication
        const isAuthenticated = await this.studentStore.getCurrentUser()
        if (!isAuthenticated || !this.user) {
            this.$router.push('/student')
            return
        }

        // Load course details
        await this.loadCourse()
    },

    data() {
        return {
            studentStore: null,
            courseStore: null,
            showDrawer: false,
            loading: false,
            course: null,
        }
    },

    computed: {
        ...mapWritableState(useStudentStore, ['user']),

        courseId() {
            return this.$route.params.id
        },

        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },
    },

    methods: {
        async loadCourse() {
            this.loading = true
            try {
                // Get courses from store
                await this.courseStore.getCourses()

                // Find the specific course by ID
                const courses = this.courseStore.courses || []
                this.course = courses.find(c => c.id === parseInt(this.courseId))

                if (!this.course) {
                    console.error('Course not found:', this.courseId)
                }
            } catch (error) {
                console.error('Error loading course:', error)
            } finally {
                this.loading = false
            }
        },
    },
}
</script>
