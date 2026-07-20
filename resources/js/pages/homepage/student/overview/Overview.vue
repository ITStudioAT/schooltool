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
                    <v-btn class="menu-btn" data-testid="student-overview-open-menu" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Unterricht</h1>
                <p class="hero-subtitle">Überblick bewahren. Inhalte kennen. Noten erfahren.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                    <span v-if="user?.schoolclass" class="hero-badge dark">{{ user.schoolclass }}</span>
                </div>

                <ParentAccessPanel />

                <div class="hero-logout-row">
                    <v-btn class="logout-btn" data-testid="student-overview-logout" variant="text" prepend-icon="mdi-logout" @click="handleLogout">Abmelden</v-btn>
                </div>

                <div v-if="heroLiveTimer" class="hero-on-air">
                    <div class="on-air-badge">
                        <span class="on-air-dot"></span>
                        <span class="on-air-label">{{ heroLiveTimer.isSimulated ? 'TESTMODUS' : 'ON AIR' }}</span>
                    </div>
                    <div class="on-air-title">{{ heroLiveTimer.title }}</div>
                    <div class="on-air-timer">endet in {{ heroLiveTimer.remainingLabel }}</div>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <!-- Courses Section -->
            <MyCourses @live-timer-change="updateHeroLiveTimer" />
        </section>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'
import ParentAccessPanel from '../components/ParentAccessPanel.vue'
import StudentNavigationDrawer from '../components/StudentNavigationDrawer.vue'
import MyCourses from './myCourses/MyCourses.vue'
import '../../../../../css/student.css'

export default {
    components: {
        ParentAccessPanel,
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
            heroLiveTimer: null,
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
        updateHeroLiveTimer(timer) {
            this.heroLiveTimer = timer || null
        },
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

.hero-on-air {
    margin-top: 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 20px 28px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
    text-align: center;
}

.on-air-badge {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: #e53935;
    color: #fff;
    border-radius: 999px;
    padding: 10px 32px;
    font-size: 1.36rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.on-air-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    animation: on-air-pulse 1s ease-in-out infinite;
}

@keyframes on-air-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.7); }
}

.on-air-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #1a1a1a;
    line-height: 1.2;
    margin-top: 4px;
}

.on-air-timer {
    font-size: 2rem;
    font-weight: 600;
    color: var(--primary, #fd802e);
}
</style>
