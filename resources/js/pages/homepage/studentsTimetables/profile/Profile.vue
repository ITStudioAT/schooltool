<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <StudentTimetablesNavigationDrawer v-model="showDrawer" current-route="profile" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/students-timetables/overview')">Zurück</v-btn>
                    <div class="chip-brand">Ihr Profil</div>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Ihr Profil</h1>
                <p class="hero-subtitle">Hier sehen Sie Ihre persönlichen Informationen.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <div class="content-card">
                <div class="content-head">
                    <v-icon size="26">mdi-account-circle</v-icon>
                    <h2>Persönliche Daten</h2>
                    <v-btn class="ml-auto" variant="text" icon="mdi-close" @click="$router.push('/students-timetables/overview')" />
                </div>

                <div v-if="user" class="profile-info">
                    <div class="profile-section">
                        <h3 class="profile-section-title">
                            <v-icon size="20">mdi-account</v-icon>
                            Name
                        </h3>
                        <div class="profile-fields">
                            <div class="profile-field">
                                <label>Vorname</label>
                                <div class="profile-value">{{ user.first_name || '-' }}</div>
                            </div>
                            <div class="profile-field">
                                <label>Nachname</label>
                                <div class="profile-value">{{ user.last_name || '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-section">
                        <h3 class="profile-section-title">
                            <v-icon size="20">mdi-email</v-icon>
                            Kontaktdaten
                        </h3>
                        <div class="profile-fields">
                            <div class="profile-field">
                                <label>E-Mail-Adresse</label>
                                <div class="profile-value">{{ user.email }}</div>
                            </div>
                            <div class="profile-field">
                                <label>Telefon</label>
                                <div class="profile-value">{{ user.phone || '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-section">
                        <h3 class="profile-section-title">
                            <v-icon size="20">mdi-school</v-icon>
                            Schulinformationen
                        </h3>
                        <div class="profile-fields">
                            <div class="profile-field">
                                <label>Klasse</label>
                                <div class="profile-value">{{ user.schoolclass || '-' }}</div>
                            </div>
                            <div v-if="user.sex" class="profile-field">
                                <label>Geschlecht</label>
                                <div class="profile-value">{{ sexLabel }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="profile-loading">
                    <v-progress-circular indeterminate color="#fd802e" />
                    <p>Lade Profildaten...</p>
                </div>
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
        }
    },

    data() {
        return {
            studentTimetablesStore: null,
            showDrawer: false,
        }
    },

    computed: {
        ...mapWritableState(useStudentTimetablesUserStore, ['user']),

        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },
        sexLabel() {
            if (!this.user?.sex) return '-'
            const sexMap = {
                m: 'Männlich',
                w: 'Weiblich',
                d: 'Divers',
            }

            return sexMap[this.user.sex.toLowerCase()] || this.user.sex
        },
    },
}
</script>
