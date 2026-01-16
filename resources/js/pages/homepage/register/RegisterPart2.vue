<template>
    <div class="register-page">
        <!-- Animated Background -->
        <div class="animated-bg">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
        </div>

        <!-- Floating Particles -->
        <div class="particles">
            <div class="particle" v-for="n in 15" :key="n" :style="getParticleStyle(n)"></div>
        </div>

        <!-- Main Content -->
        <div class="register-container" v-if="config">
            <!-- School Header Card -->
            <div class="school-header-card">
                <div class="school-header-content">
                    <div class="school-logo-wrapper" v-if="config?.school?.logo">
                        <img :src="`/storage/images/${config?.school?.logo}`" alt="Logo" class="school-logo" />
                    </div>
                    <div class="school-icon-wrapper" v-else>
                        <v-icon size="48" color="white">mdi-school</v-icon>
                    </div>
                    <div class="school-info">
                        <h1 class="school-name">{{ config?.school?.long_name }}</h1>
                        <p class="school-subtitle">{{ active_register?.name }}</p>
                    </div>
                </div>
            </div>

            <!-- Register Description -->
            <v-expand-transition>
                <div v-if="active_register?.description_on_website" class="register-info-card">
                    <div class="register-info-header">
                        <v-icon size="20" class="mr-2">mdi-information</v-icon>
                        <span>Information</span>
                    </div>
                    <div class="register-description" v-html="active_register.description_on_website"></div>
                </div>
            </v-expand-transition>

            <!-- User Info Card -->
            <div class="user-card">
                <div class="user-card-header">
                    <div class="user-avatar">
                        <v-icon size="28" color="white">mdi-account</v-icon>
                    </div>
                    <div class="user-info">
                        <h3 class="user-name">{{ config?.user?.last_name }} {{ config?.user?.first_name }}</h3>
                        <div class="user-details">
                            <div class="user-detail">
                                <v-icon size="16" class="mr-1">mdi-email</v-icon>
                                {{ config?.user?.email }}
                            </div>
                            <div class="user-detail" v-if="config?.user?.phone">
                                <v-icon size="16" class="mr-1">mdi-phone</v-icon>
                                {{ config?.user?.phone }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date Selection Section -->
            <div class="content-card" v-if="action == '' && bookings.length == 0">
                <div class="card-glow"></div>
                <div class="card-inner">
                    <div class="section-header">
                        <div class="section-icon">
                            <v-icon size="28" color="primary">mdi-calendar-month</v-icon>
                        </div>
                        <div class="section-info">
                            <h2 class="section-title">Termin auswählen</h2>
                            <p class="section-subtitle">Wählen Sie zuerst einen Tag und dann eine Uhrzeit</p>
                        </div>
                    </div>

                    <!-- Date Chips -->
                    <div class="date-selection">
                        <div class="date-label">Verfügbare Tage:</div>
                        <div class="date-chips">
                            <v-chip
                                v-for="date in dates"
                                :key="date.date"
                                :color="selected_date == date ? 'success' : 'default'"
                                :variant="selected_date == date ? 'flat' : 'outlined'"
                                size="large"
                                class="date-chip"
                                @click="selectDate(date)"
                            >
                                <div class="date-chip-content">
                                    <span class="date-weekday">{{ date.weekday }}</span>
                                    <span class="date-date">{{ date.date }}</span>
                                </div>
                            </v-chip>
                        </div>
                    </div>

                    <!-- Time Slots -->
                    <div class="time-selection" v-if="selected_date">
                        <div class="time-label">Verfügbare Zeiten am {{ selected_date.weekday }}, {{ selected_date.date }}:</div>
                        <div class="time-slots">
                            <div
                                v-for="register_date in possibleRegisterDates"
                                :key="register_date.id"
                                class="time-slot"
                                :class="{
                                    'time-slot-selected': selected_register_date.includes(register_date.id),
                                    'time-slot-disabled': register_date.is_locked || register_date.max_registrations - register_date.bookings_count == 0
                                }"
                                @click="!register_date.is_locked && (register_date.max_registrations - register_date.bookings_count > 0) && toggleTimeSlot(register_date.id)"
                            >
                                <div class="time-slot-time">
                                    <v-icon size="20" class="mr-2">mdi-clock-outline</v-icon>
                                    {{ register_date.from }} - {{ register_date.to }} Uhr
                                </div>
                                <div class="time-slot-status">
                                    <v-chip
                                        size="small"
                                        :color="getSlotStatusColor(register_date)"
                                        variant="tonal"
                                    >
                                        <v-icon start size="14">{{ getSlotStatusIcon(register_date) }}</v-icon>
                                        {{ getSlotStatusText(register_date) }}
                                    </v-chip>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-actions mt-6">
                        <v-btn color="grey" variant="tonal" size="large" rounded="lg" @click="logout">
                            <v-icon start>mdi-logout</v-icon>
                            Abmelden
                        </v-btn>
                        <v-btn
                            color="success"
                            variant="flat"
                            size="large"
                            rounded="lg"
                            @click="editKid"
                            :disabled="selected_register_date.length == 0"
                        >
                            Weiter
                            <v-icon end>mdi-arrow-right</v-icon>
                        </v-btn>
                    </div>
                </div>
            </div>

            <!-- Existing Bookings Section -->
            <div class="content-card" v-if="action == '' && bookings.length > 0">
                <div class="card-glow card-glow-success"></div>
                <div class="card-inner">
                    <div class="section-header">
                        <div class="section-icon section-icon-success">
                            <v-icon size="28" color="success">mdi-calendar-check</v-icon>
                        </div>
                        <div class="section-info">
                            <h2 class="section-title">Ihre Buchungen</h2>
                            <p class="section-subtitle">{{ bookings.length }} {{ bookings.length === 1 ? 'Termin' : 'Termine' }} gebucht</p>
                        </div>
                    </div>

                    <!-- Booking Cards -->
                    <div class="bookings-list">
                        <div class="booking-card" v-for="booking in bookings" :key="booking.id">
                            <div class="booking-content">
                                <div class="booking-datetime">
                                    <div class="booking-date">
                                        <v-icon size="18" class="mr-2">mdi-calendar</v-icon>
                                        {{ booking.date }}
                                    </div>
                                    <div class="booking-time">
                                        <v-icon size="18" class="mr-2">mdi-clock-outline</v-icon>
                                        {{ booking.from }} - {{ booking.to }} Uhr
                                    </div>
                                </div>
                                <div class="booking-student">
                                    <v-icon size="18" class="mr-2">mdi-account-school</v-icon>
                                    {{ (booking.student_last_name || '') + ' ' + (booking.student_first_name || '') }}
                                </div>
                            </div>
                            <div class="booking-actions">
                                <v-btn
                                    color="error"
                                    variant="tonal"
                                    size="small"
                                    rounded="lg"
                                    @click="deleteBooking(booking)"
                                >
                                    <v-icon start>mdi-delete</v-icon>
                                    Stornieren
                                </v-btn>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-actions mt-6">
                        <v-btn color="grey" variant="tonal" size="large" rounded="lg" @click="logout">
                            <v-icon start>mdi-logout</v-icon>
                            Abmelden
                        </v-btn>
                    </div>
                </div>
            </div>

            <!-- Student Data Form -->
            <div class="content-card" v-if="action == 'edit_kid'">
                <div class="card-glow"></div>
                <div class="card-inner">
                    <div class="section-header">
                        <div class="section-icon">
                            <v-icon size="28" color="primary">mdi-account-school</v-icon>
                        </div>
                        <div class="section-info">
                            <h2 class="section-title">Daten des Kindes</h2>
                            <p class="section-subtitle">Bitte geben Sie die Daten Ihres Kindes ein</p>
                        </div>
                    </div>

                    <!-- Selected Appointment Info -->
                    <div class="appointment-preview">
                        <div class="appointment-icon">
                            <v-icon size="24" color="success">mdi-calendar-check</v-icon>
                        </div>
                        <div class="appointment-info">
                            <div class="appointment-label">Gewählter Termin:</div>
                            <div class="appointment-datetime">
                                <span class="appointment-date">
                                    <v-icon size="16" class="mr-1">mdi-calendar</v-icon>
                                    {{ registerDate(selected_register_date[0])?.date }}, {{ weekday(registerDate(selected_register_date[0])?.date) }}
                                </span>
                                <span class="appointment-time">
                                    <v-icon size="16" class="mr-1">mdi-clock-outline</v-icon>
                                    {{ registerDate(selected_register_date[0])?.from }} - {{ registerDate(selected_register_date[0])?.to }} Uhr
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Form -->
                    <v-form ref="form" v-model="is_valid" @submit.prevent="book(data)" class="student-form">
                        <v-text-field
                            v-if="active_register.show_student_last_name"
                            autofocus
                            v-model="data.student_last_name"
                            label="Nachname des Kindes"
                            variant="outlined"
                            prepend-inner-icon="mdi-account"
                            :rules="active_register.must_student_last_name ? [required(), maxLength(255)] : [maxLength(255)]"
                            class="mb-4"
                        />
                        <v-text-field
                            v-if="active_register.show_student_first_name"
                            v-model="data.student_first_name"
                            label="Vorname des Kindes"
                            variant="outlined"
                            prepend-inner-icon="mdi-account-outline"
                            :rules="active_register.must_student_first_name ? [required(), maxLength(255)] : [maxLength(255)]"
                            class="mb-4"
                        />
                        <v-text-field
                            v-if="active_register.show_student_birthdate"
                            v-model="data.student_birthdate"
                            label="Geburtsdatum (JJJJ-MM-TT)"
                            variant="outlined"
                            prepend-inner-icon="mdi-cake-variant"
                            :rules="active_register.must_student_birthdate ? [required(), date()] : [date()]"
                            class="mb-4"
                        />
                        <v-text-field
                            v-if="active_register.show_note"
                            v-model="data.note"
                            label="Anmerkungen"
                            variant="outlined"
                            prepend-inner-icon="mdi-note-text"
                            :rules="active_register.must_note ? [required(), maxLength(255)] : [maxLength(255)]"
                            class="mb-6"
                        />

                        <div class="form-actions">
                            <v-btn color="grey" variant="tonal" size="large" rounded="lg" @click="backToDateSelection">
                                <v-icon start>mdi-arrow-left</v-icon>
                                Zurück
                            </v-btn>
                            <v-btn
                                color="success"
                                variant="flat"
                                size="large"
                                rounded="lg"
                                type="submit"
                            >
                                <v-icon start>mdi-check</v-icon>
                                Jetzt buchen
                            </v-btn>
                        </div>
                    </v-form>
                </div>
            </div>

            <!-- Fixed Bottom Hint when time slot is selected -->
            <Teleport to="body">
                <Transition name="slide-up">
                    <div v-if="selected_register_date.length > 0 && action == ''" class="confirm-hint-fixed" @click="scrollToActions">
                        <div class="confirm-hint-content">
                            <v-icon size="22" class="mr-2">mdi-arrow-down-circle</v-icon>
                            <span>Termin ausgewählt! Jetzt unten bestätigen</span>
                            <v-icon size="22" class="ml-2">mdi-chevron-down</v-icon>
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <!-- Booking Success -->
            <div class="content-card" v-if="action == 'booked'">
                <div class="card-glow card-glow-success"></div>
                <div class="card-inner">
                    <div class="success-state">
                        <div class="success-icon">
                            <v-icon size="64" color="success">mdi-check-circle</v-icon>
                        </div>
                        <h2 class="success-title">Buchung erfolgreich!</h2>
                        <p class="success-subtitle">Ihr Termin wurde erfolgreich gebucht.</p>

                        <div class="success-details">
                            <div class="success-detail">
                                <v-icon size="20" class="mr-2">mdi-calendar</v-icon>
                                {{ registerDate(selected_register_date[0])?.date }}, {{ weekday(registerDate(selected_register_date[0])?.date) }}
                            </div>
                            <div class="success-detail">
                                <v-icon size="20" class="mr-2">mdi-clock-outline</v-icon>
                                {{ registerDate(selected_register_date[0])?.from }} - {{ registerDate(selected_register_date[0])?.to }} Uhr
                            </div>
                            <div class="success-detail" v-if="data.student_last_name || data.student_first_name">
                                <v-icon size="20" class="mr-2">mdi-account-school</v-icon>
                                {{ data.student_last_name }} {{ data.student_first_name }}
                            </div>
                            <div class="success-detail" v-if="data.student_birthdate">
                                <v-icon size="20" class="mr-2">mdi-cake-variant</v-icon>
                                {{ data.student_birthdate }}
                            </div>
                            <div class="success-detail" v-if="data.note">
                                <v-icon size="20" class="mr-2">mdi-note-text</v-icon>
                                {{ data.note }}
                            </div>
                        </div>

                        <div class="form-actions mt-6">
                            <v-btn color="grey" variant="tonal" size="large" rounded="lg" @click="logout">
                                <v-icon start>mdi-logout</v-icon>
                                Abmelden
                            </v-btn>
                            <v-btn color="success" variant="flat" size="large" rounded="lg" @click="bookingFinished">
                                <v-icon start>mdi-format-list-bulleted</v-icon>
                                Zur Übersicht
                            </v-btn>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
import { useRegisterStore } from '@/stores/homepage/RegisterStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.homepageStore = useHomepageStore()
        this.registerStore = useRegisterStore()
        await this.registerStore.loadRegisterAndUser()
        if (this.dates.length > 0) this.selected_date = this.dates[0]
    },

    data() {
        return {
            homepageStore: null,
            registerStore: null,
            selected_date: null,
            selected_register_date: [],
            action: '',
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useRegisterStore, ['config', 'registers', 'active_register', 'selected_register_id', 'data', 'register_dates', 'dates', 'bookings']),

        possibleRegisterDates() {
            if (!this.selected_date || !this.selected_date.date) return []
            return this.register_dates.filter((d) => d.date === this.selected_date.date)
        },
    },

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

        getSlotStatusColor(register_date) {
            if (register_date.is_locked) return 'warning'
            const available = register_date.max_registrations - register_date.bookings_count
            if (available === 0) return 'error'
            if (available <= 3) return 'warning'
            return 'success'
        },

        getSlotStatusIcon(register_date) {
            if (register_date.is_locked) return 'mdi-lock'
            const available = register_date.max_registrations - register_date.bookings_count
            if (available === 0) return 'mdi-close-circle'
            return 'mdi-check-circle'
        },

        getSlotStatusText(register_date) {
            if (register_date.is_locked) return 'Gesperrt'
            const available = register_date.max_registrations - register_date.bookings_count
            if (available === 0) return 'Ausgebucht'
            return `${available} frei`
        },

        toggleTimeSlot(id) {
            if (this.selected_register_date.includes(id)) {
                this.selected_register_date = []
            } else {
                this.selected_register_date = [id]
            }
        },

        async deleteBooking(booking) {
            if (!(await this.registerStore.deleteBooking(booking.id))) return
            await this.registerStore.loadRegisterAndUser()
            if (this.dates.length > 0) this.selected_date = this.dates[0]
            this.selected_register_date = []
        },

        async book(input) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            const data = {
                register_id: this.active_register?.id ?? null,
                register_date_id: this.selected_register_date[0] ?? null,
                student_last_name: input?.student_last_name ?? null,
                student_first_name: input?.student_first_name ?? null,
                student_birthdate: input?.student_birthdate ?? null,
                note: input?.note ?? null,
            }

            if (!(await this.registerStore.book(data))) return
            this.action = 'booked'
        },

        async bookingFinished() {
            this.action = ''
            await this.registerStore.loadRegisterAndUser()
            if (this.dates.length > 0) this.selected_date = this.dates[0]
            this.selected_register_date = []
        },

        weekday(date) {
            if (!date) return ''
            const d = new Date(date)
            if (isNaN(d)) return ''
            return new Intl.DateTimeFormat('de-DE', { weekday: 'long' }).format(d)
        },

        registerDate(register_date_id) {
            return this.register_dates.find((d) => d.id === register_date_id)
        },

        editKid() {
            this.action = 'edit_kid'
        },

        backToDateSelection() {
            this.action = ''
            this.selected_register_date = []
        },

        selectDate(date) {
            this.selected_date = date
            this.selected_register_date = []
        },

        scrollToActions() {
            const actions = document.querySelector('.form-actions')
            if (actions) {
                actions.scrollIntoView({ behavior: 'smooth', block: 'center' })
            }
        },

        async logout() {
            await this.homepageStore.logout()
            this.$router.push('/')
        },
    },
}
</script>

<style scoped>
/* Base Layout */
.register-page {
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 24px 16px;
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
    filter: blur(100px);
    opacity: 0.4;
    animation: float 25s ease-in-out infinite;
}

.orb-1 {
    width: 500px;
    height: 500px;
    background: linear-gradient(135deg, #3AAA35 0%, #2d8a2a 100%);
    top: -150px;
    right: -150px;
    animation-delay: 0s;
}

.orb-2 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #37474F 0%, #263238 100%);
    bottom: -100px;
    left: -100px;
    animation-delay: -12s;
    opacity: 0.25;
}

@keyframes float {
    0%, 100% {
        transform: translate(0, 0) scale(1);
    }
    33% {
        transform: translate(30px, -30px) scale(1.05);
    }
    66% {
        transform: translate(-20px, 20px) scale(0.95);
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
    overflow: hidden;
}

.particle {
    position: absolute;
    background: rgba(58, 170, 53, 0.25);
    border-radius: 50%;
    animation: drift 20s ease-in-out infinite;
}

.particle:nth-child(even) {
    background: rgba(55, 71, 79, 0.2);
}

@keyframes drift {
    0%, 100% {
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

/* Container */
.register-container {
    position: relative;
    z-index: 1;
    max-width: 600px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* School Header Card */
.school-header-card {
    background: linear-gradient(135deg, #37474F 0%, #263238 100%);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 10px 40px rgba(55, 71, 79, 0.3);
}

.school-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.school-logo-wrapper {
    width: 64px;
    height: 64px;
    background: white;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    padding: 6px;
}

.school-logo {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.school-icon-wrapper {
    width: 64px;
    height: 64px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.school-info {
    flex: 1;
    min-width: 0;
}

.school-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: white;
    margin: 0;
    line-height: 1.3;
}

.school-subtitle {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.75);
    margin: 4px 0 0 0;
}

/* Register Info Card */
.register-info-card {
    background: linear-gradient(135deg, #3AAA35 0%, #2d8a2a 100%);
    border-radius: 16px;
    padding: 16px 20px;
    color: white;
    box-shadow: 0 6px 25px rgba(58, 170, 53, 0.25);
}

.register-info-header {
    display: flex;
    align-items: center;
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 8px;
}

.register-description {
    font-size: 0.9rem;
    line-height: 1.6;
    opacity: 0.95;
}

/* User Card */
.user-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}

.user-card-header {
    display: flex;
    align-items: center;
    gap: 16px;
}

.user-avatar {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, #3AAA35 0%, #2d8a2a 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 4px 0;
}

.user-details {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.user-detail {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    color: #607D8B;
}

/* Content Card */
.content-card {
    position: relative;
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.content-card:hover {
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
}

.card-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3AAA35, #4bc044);
}

.card-glow-success {
    background: linear-gradient(90deg, #4CAF50, #66BB6A);
}

.card-inner {
    padding: 28px 24px;
}

/* Section Header */
.section-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.section-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(58, 170, 53, 0.1);
    flex-shrink: 0;
}

.section-icon-success {
    background: rgba(76, 175, 80, 0.1);
}

.section-info {
    flex: 1;
    min-width: 0;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #263238;
    margin: 0;
    line-height: 1.3;
}

.section-subtitle {
    font-size: 0.9rem;
    color: #607D8B;
    margin: 4px 0 0 0;
}

/* Date Selection */
.date-selection {
    margin-bottom: 24px;
}

.date-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: #37474F;
    margin-bottom: 12px;
}

.date-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.date-chip {
    cursor: pointer;
    transition: all 0.2s ease;
}

.date-chip:hover {
    transform: translateY(-2px);
}

.date-chip-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 4px 8px;
}

.date-weekday {
    font-size: 0.75rem;
    font-weight: 500;
    opacity: 0.8;
}

.date-date {
    font-size: 0.9rem;
    font-weight: 600;
}

/* Time Selection */
.time-selection {
    margin-top: 24px;
}

.time-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: #37474F;
    margin-bottom: 12px;
}

.time-slots {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.time-slot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: #f5f7fa;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

.time-slot:hover:not(.time-slot-disabled) {
    background: #e8f5e9;
    border-color: #c8e6c9;
}

.time-slot-selected {
    background: #e8f5e9;
    border-color: #4CAF50;
}

.time-slot-disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.time-slot-time {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: #37474F;
}

/* Fixed Confirm Hint */
.confirm-hint-fixed {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    padding: 16px 20px;
    padding-bottom: calc(16px + env(safe-area-inset-bottom));
    background: linear-gradient(135deg, #4CAF50 0%, #2e7d32 100%);
    color: white;
    cursor: pointer;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
}

.confirm-hint-content {
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
    animation: bounce-hint 1.5s ease-in-out infinite;
}

@keyframes bounce-hint {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}

/* Slide-up transition */
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}

/* Bookings List */
.bookings-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.booking-card {
    background: #f5f7fa;
    border-radius: 14px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.booking-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.booking-datetime {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.booking-date,
.booking-time,
.booking-student {
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    color: #37474F;
}

.booking-student {
    font-weight: 600;
}

.booking-actions {
    display: flex;
    justify-content: flex-end;
}

/* Appointment Preview */
.appointment-preview {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #e8f5e9;
    border-radius: 12px;
    margin-bottom: 24px;
}

.appointment-icon {
    width: 48px;
    height: 48px;
    background: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.appointment-info {
    flex: 1;
}

.appointment-label {
    font-size: 0.85rem;
    color: #4CAF50;
    font-weight: 500;
    margin-bottom: 4px;
}

.appointment-datetime {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.appointment-date,
.appointment-time {
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    font-weight: 600;
    color: #2e7d32;
}

/* Student Form */
.student-form {
    display: flex;
    flex-direction: column;
}

/* Success State */
.success-state {
    text-align: center;
    padding: 20px 0;
}

.success-icon {
    margin-bottom: 20px;
    animation: scaleIn 0.5s ease-out;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.success-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2e7d32;
    margin: 0 0 8px 0;
}

.success-subtitle {
    font-size: 1rem;
    color: #607D8B;
    margin: 0 0 24px 0;
}

.success-details {
    background: #f5f7fa;
    border-radius: 12px;
    padding: 20px;
    display: inline-flex;
    flex-direction: column;
    gap: 12px;
    text-align: left;
}

.success-detail {
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    color: #37474F;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.form-actions .v-btn {
    flex: 1;
    min-width: 140px;
}

/* Responsive */
@media (max-width: 600px) {
    .register-page {
        padding: 16px 12px;
    }

    .school-header-card {
        padding: 20px 16px;
    }

    .school-header-content {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }

    .school-name {
        font-size: 1.1rem;
    }

    .user-card-header {
        flex-direction: column;
        text-align: center;
    }

    .user-details {
        justify-content: center;
    }

    .card-inner {
        padding: 24px 20px;
    }

    .section-header {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }

    .section-title {
        font-size: 1.1rem;
    }

    .date-chips {
        justify-content: center;
    }

    .time-slot {
        flex-direction: column;
        gap: 12px;
        text-align: center;
    }

    .booking-card {
        text-align: center;
    }

    .booking-datetime {
        justify-content: center;
    }

    .booking-actions {
        justify-content: center;
    }

    .appointment-preview {
        flex-direction: column;
        text-align: center;
    }

    .appointment-datetime {
        justify-content: center;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .v-btn {
        width: 100%;
    }

    .success-details {
        width: 100%;
    }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .gradient-orb,
    .particle,
    .register-container,
    .success-icon,
    .confirm-hint-content {
        animation: none;
    }

    .content-card:hover {
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    }

    .date-chip:hover {
        transform: none;
    }

    .slide-up-enter-active,
    .slide-up-leave-active {
        transition: none;
    }
}
</style>
