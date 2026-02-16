<template>
    <div class="tutoring-page">
        <!-- Animated Background -->
        <div class="animated-bg">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>

        <!-- Floating particles -->
        <div class="particles">
            <div class="particle" v-for="n in 15" :key="n" :style="getParticleStyle(n)"></div>
        </div>

        <!-- Main Content -->
        <div class="main-container" v-if="auth">
            <!-- Header -->
            <div class="header-section">
                <div class="brand-container">
                    <div class="logo-wrapper">
                        <v-icon size="40" color="white">mdi-account-group</v-icon>
                    </div>
                    <h1 class="brand-title">
                        <span class="brand-nach">Nach</span>
                        <span class="brand-hilfe">hilfe</span>
                        <span class="brand-tool">Tool</span>
                    </h1>
                    <p class="brand-tagline">Schüler helfen Schülern</p>
                </div>

                <!-- School Info -->
                <div class="school-info" v-if="auth.school_logo || auth.school_long_name">
                    <div class="school-badge">
                        <div class="school-logo" v-if="auth.school_logo">
                            <img :src="'/storage/images/' + auth.school_logo" alt="Logo" />
                        </div>
                        <v-icon v-else size="32" color="primary">mdi-school</v-icon>
                        <span class="school-name">{{ auth.school_long_name }}</span>
                    </div>
                </div>
            </div>

            <!-- User Info Card -->
            <div class="user-section">
                <div class="user-card">
                    <div class="user-avatar">
                        <v-icon size="32" color="white">mdi-account</v-icon>
                    </div>
                    <div class="user-info">
                        <h3 class="user-name">{{ auth.auth_user.first_name }} {{ auth.auth_user.last_name }}</h3>
                        <p class="user-class">{{ auth.auth_user.schoolclass }}</p>
                    </div>
                </div>
            </div>

            <!-- Menu Section -->
            <div class="menu-section" v-if="action == ''">
                <div class="menu-grid">
                    <!-- Back to Overview -->
                    <div class="menu-card card-back" data-testid="tutoring-menu-back-to-overview" @click="moveToTutoringOverview">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-arrow-left</v-icon>
                            </div>
                            <h3 class="card-title">Zurück zur Übersicht</h3>
                            <p class="card-description">Hier gelangst Du wieder zurück zur Übersicht.</p>
                            <div class="card-action">
                                <span>Zur Übersicht</span>
                                <v-icon size="18">mdi-arrow-right</v-icon>
                            </div>
                        </div>
                    </div>

                    <!-- Create New Offer -->
                    <div class="menu-card card-offer" data-testid="tutoring-menu-create-offer" @click="createOffer">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-plus-circle</v-icon>
                            </div>
                            <h3 class="card-title">Neue Nachhilfe</h3>
                            <p class="card-description">Hier kannst Du ein neues Nachhilfe-Angebot erstellen.</p>
                            <div class="card-action">
                                <span>Los</span>
                                <v-icon size="18">mdi-arrow-right</v-icon>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile -->
                    <div class="menu-card card-profile" data-testid="tutoring-menu-edit-profile" @click="editProfile">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-account-edit</v-icon>
                            </div>
                            <h3 class="card-title">Profil ändern</h3>
                            <p class="card-description">Bearbeite Deine persönlichen Daten und Kontaktinformationen.</p>
                            <div class="card-action">
                                <span>Bearbeiten</span>
                                <v-icon size="18">mdi-arrow-right</v-icon>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="menu-card card-password" data-testid="tutoring-menu-edit-password" @click="editPassword">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-lock-reset</v-icon>
                            </div>
                            <h3 class="card-title">Kennwort ändern</h3>
                            <p class="card-description">Ändere Dein Passwort für mehr Sicherheit.</p>
                            <div class="card-action">
                                <span>Ändern</span>
                                <v-icon size="18">mdi-arrow-right</v-icon>
                            </div>
                        </div>
                    </div>

                    <!-- Logout -->
                    <div class="menu-card card-logout" data-testid="tutoring-menu-logout" @click="logout">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-logout</v-icon>
                            </div>
                            <h3 class="card-title">Abmelden</h3>
                            <p class="card-description">Vom System ausloggen.</p>
                            <div class="card-action">
                                <span>Abmelden</span>
                                <v-icon size="18">mdi-arrow-right</v-icon>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sub-Menu with Back Button -->
            <div class="menu-section" v-if="action != ''">
                <div class="sub-menu">
                    <div class="menu-card card-back" @click="action = ''">
                        <div class="card-glow"></div>
                        <div class="card-content">
                            <div class="card-icon">
                                <v-icon size="36">mdi-arrow-left</v-icon>
                            </div>
                            <h3 class="card-title">Zurück</h3>
                            <p class="card-description">Zurück zum Menü.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Section -->
            <div class="content-section" v-if="auth">
                <!-- My Offers -->
                <MyOffers v-if="action == '' || action == 'edit_offer'" />

                <!-- Profile -->
                <div class="content-card" v-if="action == 'profile'">
                    <Profile />
                </div>

                <!-- Password -->
                <div class="content-card" v-if="action == 'password'">
                    <Password />
                </div>

                <!-- Create Offer -->
                <div class="content-card" v-if="action == 'create_offer'">
                    <Offer />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import { useSubjectStore } from '@/stores/tutoring/SubjectStore'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import Profile from './components/Profile.vue'
import Password from './components/Password.vue'
import Offer from './components/Offer.vue'
import MyOffers from './components/MyOffers.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { Profile, Password, Offer, MyOffers },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.userStore = useUserStore()
        this.subjectStore = useSubjectStore()
        this.offerStore = useOfferStore()
        await this.tutoringStore.loadAuth()
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            userStore: null,
            offerStore: null,
            subjectStore: null,
            is_valid: false,
            is_password_visible: false,
            is_password_visible_confirm: false,
            selectedSubject: null,
            step: 0,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth', 'action']),
        ...mapWritableState(useUserStore, ['error', 'data']),
        ...mapWritableState(useSubjectStore, ['subjects']),
        ...mapWritableState(useOfferStore, []),
    },

    watch: {},

    methods: {
        getParticleStyle(n) {
            const random = (min, max) => Math.random() * (max - min) + min
            return {
                left: `${random(0, 100)}%`,
                top: `${random(0, 100)}%`,
                width: `${random(4, 10)}px`,
                height: `${random(4, 10)}px`,
                animationDelay: `${random(0, 15)}s`,
                animationDuration: `${random(15, 25)}s`,
            }
        },

        moveToTutoringOverview() {
            this.offerStore.error = null
            this.$router.push('/homepage/tutoring_overview/?school=' + this.auth?.school_short_name)
        },

        createOffer() {
            this.action = 'create_offer'
        },

        editProfile() {
            this.action = 'profile'
        },

        editPassword() {
            this.action = 'password'
        },

        async logout() {
            const school = this.auth?.school_short_name
            await this.userStore.logout()
            this.action = ''
            this.$router.push('/homepage/tutoring_overview/?school=' + school)
        },
    },
}
</script>

<style scoped>
/* Base Layout */
.tutoring-page {
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
}

/* Animated Background */
.animated-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: 0;
    pointer-events: none;
}

.gradient-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.5;
    animation: float 20s ease-in-out infinite;
}

.orb-1 {
    width: 500px;
    height: 500px;
    background: linear-gradient(135deg, #f39200 0%, #d67f00 100%);
    top: -150px;
    left: -150px;
    animation-delay: 0s;
}

.orb-2 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #3aaa35 0%, #2d8a2a 100%);
    bottom: -100px;
    right: -100px;
    animation-delay: -7s;
}

.orb-3 {
    width: 350px;
    height: 350px;
    background: linear-gradient(135deg, #37474f 0%, #263238 100%);
    top: 60%;
    left: 30%;
    animation-delay: -14s;
    opacity: 0.3;
}

@keyframes float {
    0%,
    100% {
        transform: translate(0, 0) scale(1);
    }
    25% {
        transform: translate(30px, -30px) scale(1.05);
    }
    50% {
        transform: translate(-20px, 20px) scale(0.95);
    }
    75% {
        transform: translate(-30px, -20px) scale(1.02);
    }
}

/* Particles */
.particles {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

.particle {
    position: absolute;
    background: rgba(243, 146, 0, 0.3);
    border-radius: 50%;
    animation: drift 20s ease-in-out infinite;
}

.particle:nth-child(even) {
    background: rgba(58, 170, 53, 0.3);
}

.particle:nth-child(3n) {
    background: rgba(55, 71, 79, 0.2);
}

@keyframes drift {
    0%,
    100% {
        transform: translate(0, 0);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translate(80px, -80px);
        opacity: 0;
    }
}

/* Main Container */
.main-container {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* Header */
.header-section {
    text-align: center;
    margin-bottom: 32px;
    animation: fadeInDown 0.8s ease-out;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.brand-container {
    margin-bottom: 20px;
}

.logo-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, #f39200 0%, #3aaa35 100%);
    border-radius: 18px;
    margin-bottom: 16px;
    box-shadow: 0 10px 40px rgba(243, 146, 0, 0.3);
    animation: pulse-glow 3s ease-in-out infinite;
}

@keyframes pulse-glow {
    0%,
    100% {
        box-shadow: 0 10px 40px rgba(243, 146, 0, 0.3);
    }
    50% {
        box-shadow: 0 10px 60px rgba(58, 170, 53, 0.4);
    }
}

.brand-title {
    font-size: clamp(2rem, 6vw, 3.5rem);
    font-weight: 800;
    letter-spacing: -1px;
    margin: 0;
    line-height: 1;
}

.brand-nach {
    color: #f39200;
}
.brand-hilfe {
    color: #3aaa35;
}
.brand-tool {
    color: #37474f;
}

.brand-tagline {
    font-size: 1.1rem;
    color: #546e7a;
    margin-top: 8px;
}

/* School Info */
.school-info {
    margin-top: 16px;
}

.school-badge {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    padding: 10px 20px;
    border-radius: 30px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.school-logo img {
    height: 32px;
    width: auto;
    object-fit: contain;
}

.school-name {
    font-weight: 500;
    color: #37474f;
}

/* User Section */
.user-section {
    display: flex;
    justify-content: center;
    margin-bottom: 32px;
    animation: fadeInUp 0.8s ease-out 0.1s both;
}

.user-card {
    display: flex;
    align-items: center;
    gap: 16px;
    background: linear-gradient(135deg, #3aaa35 0%, #2d8a2a 100%);
    padding: 16px 28px;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(58, 170, 53, 0.25);
}

.user-avatar {
    width: 52px;
    height: 52px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.user-info {
    color: white;
}

.user-name {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
}

.user-class {
    font-size: 0.9rem;
    opacity: 0.9;
    margin: 4px 0 0 0;
}

/* Menu Section */
.menu-section {
    margin-bottom: 32px;
    animation: fadeInUp 0.8s ease-out 0.2s both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.menu-grid,
.sub-menu {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
}

/* Menu Cards */
.menu-card {
    position: relative;
    background: white;
    border-radius: 16px;
    padding: 24px;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.menu-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.card-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    transition: height 0.3s ease;
}

.menu-card:hover .card-glow {
    height: 5px;
}

/* Card Variants */
.card-back .card-glow {
    background: linear-gradient(90deg, #78909c, #90a4ae);
}
.card-back .card-icon {
    background: rgba(120, 144, 156, 0.1);
    color: #78909c;
}
.card-back .card-action {
    color: #78909c;
}

.card-offer .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}
.card-offer .card-icon {
    background: rgba(58, 170, 53, 0.1);
    color: #3aaa35;
}
.card-offer .card-action {
    color: #3aaa35;
}

.card-profile .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}
.card-profile .card-icon {
    background: rgba(58, 170, 53, 0.1);
    color: #3aaa35;
}
.card-profile .card-action {
    color: #3aaa35;
}

.card-password .card-glow {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}
.card-password .card-icon {
    background: rgba(58, 170, 53, 0.1);
    color: #3aaa35;
}
.card-password .card-action {
    color: #3aaa35;
}

.card-logout .card-glow {
    background: linear-gradient(90deg, #f39200, #ffb74d);
}
.card-logout .card-icon {
    background: rgba(243, 146, 0, 0.1);
    color: #f39200;
}
.card-logout .card-action {
    color: #f39200;
}

.card-content {
    position: relative;
    z-index: 1;
}

.card-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    transition: transform 0.3s ease;
}

.menu-card:hover .card-icon {
    transform: scale(1.1);
}

.card-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 8px 0;
}

.card-description {
    font-size: 0.9rem;
    color: #607d8b;
    line-height: 1.5;
    margin: 0 0 16px 0;
}

.card-action {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: gap 0.3s ease;
}

.menu-card:hover .card-action {
    gap: 10px;
}

/* Content Section */
.content-section {
    animation: fadeInUp 0.8s ease-out 0.3s both;
}

.content-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
}

/* Responsive */
@media (max-width: 768px) {
    .main-container {
        padding: 20px 16px;
    }

    .brand-title {
        font-size: 2rem;
    }

    .menu-grid,
    .sub-menu {
        grid-template-columns: 1fr;
    }

    .menu-card {
        padding: 20px;
    }

    .user-card {
        padding: 12px 20px;
    }

    .user-name {
        font-size: 1rem;
    }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .gradient-orb,
    .particle,
    .menu-card,
    .logo-wrapper {
        animation: none;
    }
    .menu-card:hover {
        transform: none;
    }
}
</style>
