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
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Unterricht</h1>
                <p class="hero-subtitle">Überblick bewahren. Inhalte kennen. Noten erfahren.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                    <span v-if="user?.schoolclass" class="hero-badge dark">{{ user.schoolclass }}</span>
                </div>

                <div class="hero-logout-row">
                    <v-btn class="logout-btn" variant="text" prepend-icon="mdi-logout" @click="handleLogout">Abmelden</v-btn>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <!-- Courses Section -->
            <MyCourses />
        </section>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'
import StudentNavigationDrawer from '../components/StudentNavigationDrawer.vue'
import MyCourses from './myCourses/MyCourses.vue'
import '../../../../../css/student.css'

export default {
    components: {
        StudentNavigationDrawer,
        MyCourses,
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
        async handleLogout() {
            this.showDrawer = false
            await this.studentStore.logout()
            this.$router.push('/student')
        },
    },
}
</script>

<style scoped>
.logout-btn {
    color: var(--charcoal);
    font-weight: 700;
    border-radius: 999px;
}

.hero-logout-row {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
}
</style>
