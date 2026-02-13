<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Navigation Drawer -->
        <v-navigation-drawer v-model="showDrawer" temporary location="right" width="320">
            <div class="drawer-header">
                <div class="drawer-user-info">
                    <v-avatar color="#fd802e" size="56">
                        <span class="text-h6">{{ userInitials }}</span>
                    </v-avatar>
                    <div class="drawer-user-details">
                        <h3>{{ user?.first_name }} {{ user?.last_name }}</h3>
                        <p>{{ user?.email }}</p>
                    </div>
                </div>
            </div>

            <v-divider />

            <v-list>
                <v-list-item prepend-icon="mdi-lock-reset" @click="handlePasswordChange">
                    <v-list-item-title>Passwort ändern</v-list-item-title>
                    <v-list-item-subtitle>Ändere dein Passwort für mehr Sicherheit</v-list-item-subtitle>
                </v-list-item>

                <v-list-item prepend-icon="mdi-account-edit" @click="handleProfileEdit">
                    <v-list-item-title>Profil bearbeiten</v-list-item-title>
                    <v-list-item-subtitle>Bearbeite deine persönlichen Informationen</v-list-item-subtitle>
                </v-list-item>

                <v-list-item prepend-icon="mdi-cog" @click="handleSettings">
                    <v-list-item-title>Einstellungen</v-list-item-title>
                    <v-list-item-subtitle>Verwalte deine Benachrichtigungen und Präferenzen</v-list-item-subtitle>
                </v-list-item>

                <v-divider class="my-2" />

                <v-list-item prepend-icon="mdi-logout" @click="handleLogout">
                    <v-list-item-title>Abmelden</v-list-item-title>
                    <v-list-item-subtitle>Vom Unterrichtsbereich abmelden</v-list-item-subtitle>
                </v-list-item>
            </v-list>
        </v-navigation-drawer>

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

        <!-- Coming Soon Dialog -->
        <v-dialog v-model="showComingSoonDialog" max-width="400">
            <v-card>
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon color="primary" icon="mdi-information" />
                    <span>Demnächst verfügbar</span>
                </v-card-title>
                <v-card-text class="pt-4">Diese Funktion ist noch in Entwicklung und wird bald verfügbar sein.</v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" @click="showComingSoonDialog = false">OK</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'
import '../../../../../css/student.css'

export default {
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
            showComingSoonDialog: false,
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
        userInitials() {
            if (!this.user) return '?'
            const first = this.user.first_name?.[0] || ''
            const last = this.user.last_name?.[0] || ''
            return (first + last).toUpperCase()
        },
    },

    methods: {
        async handleLogout() {
            this.showDrawer = false
            await this.studentStore.logout()
            this.$router.push('/student')
        },

        handlePasswordChange() {
            this.showDrawer = false
            this.$router.push('/student/password')
        },

        handleProfileEdit() {
            this.showDrawer = false
            this.showComingSoonDialog = true
            // TODO: Implement profile edit functionality
        },

        handleSettings() {
            this.showDrawer = false
            this.showComingSoonDialog = true
            // TODO: Implement settings functionality
        },

        handleCourseClick(course) {
            this.showComingSoonDialog = true
            // TODO: Implement course detail view
            // this.$router.push(`/student/course/${course.id}`)
        },
    },
}
</script>
