<template>
    <div class="lernportal-page student-workspace">
        <StudentNavigationDrawer v-model="showDrawer" current-route="overview" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <span class="workspace-label"><v-icon size="20">mdi-school-outline</v-icon> Dein Lernraum</span>
                    <div class="overview-actions">
                        <v-btn class="logout-btn" data-testid="student-overview-logout" variant="text" prepend-icon="mdi-logout" @click="handleLogout">Abmelden</v-btn>
                        <v-btn class="menu-btn" data-testid="student-overview-open-menu" variant="tonal" icon="mdi-menu" aria-label="Mein Menü öffnen" @click="showDrawer = true" />
                    </div>
                </div>

                <div class="overview-welcome">
                    <div>
                        <h1 class="hero-title">Hallo{{ user?.first_name ? `, ${user.first_name}` : '' }}!</h1>
                        <p class="hero-subtitle">Deine Fächer. Dein Fortschritt. Alles an einem Ort.</p>
                    </div>
                    <div class="overview-date">
                        <span>{{ weekdayLabel }}</span>
                        <strong>{{ dateLabel }}</strong>
                    </div>
                </div>

                <div class="hero-badges">
                    <span v-if="user?.schoolclass" class="hero-badge"><v-icon size="16">mdi-account-group-outline</v-icon> Klasse {{ user.schoolclass }}</span>
                </div>

                <ParentAccessPanel />

                <div v-if="heroLiveTimer" class="hero-on-air">
                    <div class="on-air-badge">
                        <span class="on-air-dot"></span>
                        <span class="on-air-label">{{ heroLiveTimer.isSimulated ? 'Testmodus' : 'Gerade im Unterricht' }}</span>
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
.overview-actions,
.workspace-label {
    display: flex;
    align-items: center;
    gap: 8px;
}

.workspace-label {
    color: #4056d6;
    font-size: 0.85rem;
    font-weight: 750;
}

.overview-welcome {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-top: 24px;
}

.overview-date {
    display: grid;
    gap: 4px;
    color: #647086;
    font-size: 0.85rem;
    text-align: right;
    white-space: nowrap;
}

.overview-date strong {
    color: #18243b;
    font-weight: 600;
}

.logout-btn {
    color: #647086;
    font-weight: 600;
    border-radius: 12px;
    text-transform: none;
}

.hero-on-air {
    margin-top: 20px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 16px;
    padding: 16px 18px;
    border-radius: 16px;
    background: #ecf8f3;
    border: 1px solid #d3ebe0;
    color: #245d49;
}

.on-air-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    font-weight: 700;
}

.on-air-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #338866;
}

.on-air-title {
    font-size: 0.95rem;
    font-weight: 700;
}

.on-air-timer {
    margin-left: auto;
    font-size: 0.85rem;
    font-weight: 600;
}

@media (max-width: 600px) {
    .overview-welcome {
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
        margin-top: 20px;
    }

    .overview-date {
        display: flex;
        gap: 8px;
        text-align: left;
    }

    .workspace-label {
        font-size: 0.78rem;
    }

    .overview-actions {
        gap: 2px;
    }

    .logout-btn {
        font-size: 0.78rem;
        padding: 0 8px;
    }
}
</style>
