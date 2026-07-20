<template>
    <div class="lernportal-page" data-testid="student-profile-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Navigation Drawer -->
        <StudentNavigationDrawer v-model="showDrawer" current-route="profile" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/student/overview')">Zurück</v-btn>
                    <div class="chip-brand">Mein Profil</div>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Mein Profil</h1>
                <p class="hero-subtitle">Hier siehst du alle deine persönlichen Informationen.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                </div>

                <ParentAccessPanel />
            </div>
        </section>

        <section class="content-cover">
            <div class="content-card">
                <div class="content-head">
                    <v-icon size="26">mdi-account-circle</v-icon>
                    <h2>Persönliche Daten</h2>
                    <v-btn class="ml-auto" variant="text" icon="mdi-close" @click="$router.push('/student/overview')" />
                </div>
                <p class="content-copy">Deine aktuellen Profildaten. Änderungen sind derzeit nicht möglich.</p>

                <div v-if="user" class="profile-info">
                    <!-- Name Section -->
                    <div class="profile-section">
                        <h3 class="profile-section-title">
                            <v-icon size="20">mdi-account</v-icon>
                            Name
                        </h3>
                        <div class="profile-fields">
                            <div class="profile-field">
                                <label>Vorname</label>
                                <div class="profile-value" data-testid="student-profile-first-name">{{ user.first_name || '—' }}</div>
                            </div>
                            <div class="profile-field">
                                <label>Nachname</label>
                                <div class="profile-value" data-testid="student-profile-last-name">{{ user.last_name || '—' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Section -->
                    <div class="profile-section">
                        <h3 class="profile-section-title">
                            <v-icon size="20">mdi-email</v-icon>
                            Kontaktdaten
                        </h3>
                        <div class="profile-fields">
                            <div class="profile-field">
                                <label>E-Mail-Adresse</label>
                                <div class="profile-value">
                                    <span data-testid="student-profile-email">{{ user.email }}</span>
                                    <v-chip v-if="user.email_verified_at" size="x-small" color="success" variant="flat" class="ml-2">
                                        <v-icon size="12" class="mr-1">mdi-check-circle</v-icon>
                                        Verifiziert
                                    </v-chip>
                                </div>
                            </div>
                            <div class="profile-field">
                                <label>Telefon</label>
                                <div class="profile-value">{{ user.phone || '—' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- School Information Section -->
                    <div class="profile-section">
                        <h3 class="profile-section-title">
                            <v-icon size="20">mdi-school</v-icon>
                            Schulinformationen
                        </h3>
                        <div class="profile-fields">
                            <div class="profile-field">
                                <label>Klasse</label>
                                <div class="profile-value">{{ user.schoolclass || '—' }}</div>
                            </div>
                            <div class="profile-field" v-if="user.sex">
                                <label>Geschlecht</label>
                                <div class="profile-value">{{ sexLabel }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="profile-info-box">
                        <v-icon color="#fd802e" size="24">mdi-information</v-icon>
                        <div>
                            <strong>Hinweis:</strong> Wenn deine Daten nicht korrekt sind, wende dich bitte an deine Schule oder einen Administrator.
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
import { useStudentStore } from '@/stores/student/StudentStore'
import ParentAccessPanel from '../components/ParentAccessPanel.vue'
import StudentNavigationDrawer from '../components/StudentNavigationDrawer.vue'
import '../../../../../css/student.css'

export default {
    components: {
        ParentAccessPanel,
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
        sexLabel() {
            if (!this.user?.sex) return '—'
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
