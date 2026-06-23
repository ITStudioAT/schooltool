<template>
    <div class="lernportal-page students-timetables-overview-v2-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <StudentTimetablesNavigationDrawer v-model="showDrawer" current-route="overview-v2" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/students-timetables/overview')">Zur Übersicht</v-btn>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Schülerstundenpläne V2</h1>
                <p class="hero-subtitle">Neue Übersicht.</p>

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
            <div class="content-card students-timetables-overview-v2-card">
                <div class="content-head">
                    <v-icon size="26">mdi-calendar-star-outline</v-icon>
                    <h2>Vorbereitung</h2>
                </div>

                <div class="students-timetables-overview-v2-ready">
                    <v-icon icon="mdi-tools" size="28" color="primary" />
                    <div>
                        <h3>Bereit</h3>
                        <p>Die neue Ansicht ist vorbereitet.</p>
                    </div>
                </div>

                <v-btn
                    color="primary"
                    variant="tonal"
                    rounded="pill"
                    prepend-icon="mdi-arrow-left"
                    @click="$router.push('/students-timetables/overview')">
                    Aktuelle Übersicht
                </v-btn>
            </div>
        </section>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentTimetablesUserStore } from '@/stores/studentsTimetables/StudentTimetablesUserStore'
import StudentTimetablesNavigationDrawer from '../components/StudentTimetablesNavigationDrawer.vue'
import '../../../../../css/student.css'

export default {
    components: {
        StudentTimetablesNavigationDrawer,
    },

    async beforeMount() {
        this.studentTimetablesStore = useStudentTimetablesUserStore()
        const isAuthenticated = await this.studentTimetablesStore.getCurrentUser()

        if (!isAuthenticated || !this.user) {
            this.$router.push('/homepage/students-timetables')
            return
        }

        await this.studentTimetablesStore.loadOverview()
    },

    data() {
        return {
            studentTimetablesStore: null,
            showDrawer: false,
        }
    },

    computed: {
        ...mapWritableState(useStudentTimetablesUserStore, ['user', 'overview']),

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
            await this.studentTimetablesStore.logout()
            this.$router.push('/homepage/students-timetables')
        },
    },
}
</script>

<style scoped>
.students-timetables-overview-v2-card {
    display: grid;
    gap: 16px;
}

.students-timetables-overview-v2-ready {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border: 1px solid rgba(253, 128, 46, 0.18);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.72);
}

.students-timetables-overview-v2-ready h3 {
    margin: 0;
    color: #10263a;
    font-size: 1rem;
    font-weight: 800;
}

.students-timetables-overview-v2-ready p {
    margin: 2px 0 0;
    color: rgba(16, 38, 58, 0.72);
    font-size: 0.92rem;
}

@media (max-width: 520px) {
    .students-timetables-overview-v2-ready {
        align-items: flex-start;
    }
}
</style>
