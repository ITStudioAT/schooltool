<template>
    <v-col cols="12" md="6" xl="4">

        <!-- Step 0: E-Mail -->
        <v-card v-if="step === 0" rounded="xl" class="rd-card" flat>
            <v-card-text class="pa-4">
                <div class="rd-card__header mb-4">
                    <div class="rd-card__icon-wrap rd-card__icon-wrap--success">
                        <v-icon size="18" icon="mdi-account-plus" />
                    </div>
                    <div>
                        <div class="rd-card__header-title">Person anmelden</div>
                        <div class="rd-card__header-sub">Schritt 1 von 3</div>
                    </div>
                </div>

                <div class="rd-date-info mb-4" v-if="selectedRegisterDate">
                    <div class="rd-date-info__row"><v-icon size="14" class="mr-1">mdi-calendar</v-icon>{{ selectedRegisterDate.date }}</div>
                    <div class="rd-date-info__row"><v-icon size="14" class="mr-1">mdi-clock-outline</v-icon>{{ selectedRegisterDate.from }} – {{ selectedRegisterDate.to }}</div>
                    <div class="rd-date-info__row"><v-icon size="14" class="mr-1">mdi-account-hard-hat-outline</v-icon>{{ selectedRegisterDate.supervisor }}</div>
                </div>

                <v-form ref="form" v-model="is_valid" @submit.prevent="getUserWithEmail(person)">
                    <v-text-field autofocus variant="outlined" density="comfortable" rounded="lg" v-model="person.email" label="E-Mail" :rules="[mail()]" />
                </v-form>
            </v-card-text>
            <v-card-actions class="px-4 pb-4 ga-2">
                <v-btn color="success" variant="flat" rounded="lg" @click="getUserWithEmail(person)" class="flex-1-1">
                    <v-icon size="16" class="mr-1">mdi-arrow-right</v-icon>Weiter
                </v-btn>
                <v-btn color="error" variant="tonal" rounded="lg" @click="abort" class="flex-1-1">Abbruch</v-btn>
            </v-card-actions>
        </v-card>

        <!-- Step 1: Anmelder prüfen -->
        <v-card v-if="step === 1" rounded="xl" class="rd-card" flat>
            <v-card-text class="pa-4">
                <div class="rd-card__header mb-4">
                    <div class="rd-card__icon-wrap">
                        <v-icon size="18" icon="mdi-account-check-outline" />
                    </div>
                    <div>
                        <div class="rd-card__header-title">{{ user ? 'Anmelder prüfen' : 'Neuer Anmelder' }}</div>
                        <div class="rd-card__header-sub">Schritt 2 von 3</div>
                    </div>
                </div>

                <div class="rd-date-info mb-3" v-if="selectedRegisterDate">
                    <div class="rd-date-info__row"><v-icon size="14" class="mr-1">mdi-calendar</v-icon>{{ selectedRegisterDate.date }}</div>
                    <div class="rd-date-info__row"><v-icon size="14" class="mr-1">mdi-clock-outline</v-icon>{{ selectedRegisterDate.from }} – {{ selectedRegisterDate.to }}</div>
                    <div class="rd-date-info__row"><v-icon size="14" class="mr-1">mdi-account-hard-hat-outline</v-icon>{{ selectedRegisterDate.supervisor }}</div>
                </div>

                <v-alert :type="user ? 'success' : 'info'" variant="tonal" density="compact" rounded="lg" class="mb-3">
                    {{ user ? 'E-Mail bekannt – Daten können angepasst werden.' : 'Neue Person – wird neu registriert.' }}
                </v-alert>

                <div class="rd-email-badge mb-3">{{ person.email }}</div>

                <v-form ref="form" v-model="is_valid" @submit.prevent="updateOrCreateUser(person)">
                    <v-text-field autofocus variant="outlined" density="comfortable" rounded="lg" v-model="person.last_name" label="Nachname" :rules="[required(), maxLength(255)]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="person.first_name" label="Vorname" :rules="[maxLength(255)]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="person.phone" label="Telefon" :rules="[maxLength(255)]" />
                </v-form>
            </v-card-text>
            <v-card-actions class="px-4 pb-4 ga-2">
                <v-btn color="secondary" variant="tonal" rounded="lg" @click="step--">Zurück</v-btn>
                <v-btn color="success" variant="flat" rounded="lg" @click="updateOrCreateUser(person)" class="flex-1-1">
                    <v-icon size="16" class="mr-1">mdi-arrow-right</v-icon>Weiter
                </v-btn>
                <v-btn color="error" variant="tonal" rounded="lg" @click="abort">Abbruch</v-btn>
            </v-card-actions>
        </v-card>

        <!-- Step 2: Kind erfassen -->
        <v-card v-if="step === 2" rounded="xl" class="rd-card" flat>
            <v-card-text class="pa-4">
                <div class="rd-card__header mb-4">
                    <div class="rd-card__icon-wrap rd-card__icon-wrap--success">
                        <v-icon size="18" icon="mdi-human-child" />
                    </div>
                    <div>
                        <div class="rd-card__header-title">Kind erfassen</div>
                        <div class="rd-card__header-sub">Schritt 3 von 3</div>
                    </div>
                </div>

                <div class="rd-person-summary mb-3">
                    <div class="rd-person-summary__name">{{ person.last_name }} {{ person.first_name }}</div>
                    <div class="rd-person-summary__detail"><v-icon size="13" class="mr-1">mdi-email-outline</v-icon>{{ person.email }}</div>
                    <div class="rd-person-summary__detail" v-if="person.phone"><v-icon size="13" class="mr-1">mdi-phone-outline</v-icon>{{ person.phone }}</div>
                </div>

                <v-form ref="form" v-model="is_valid" @submit.prevent="createBooking(selectedRegisterDate, person)">
                    <v-text-field autofocus variant="outlined" density="comfortable" rounded="lg" v-model="person.student_last_name" label="Nachname Kind" :rules="[required(), maxLength(255)]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="person.student_first_name" label="Vorname Kind" :rules="[maxLength(255)]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="person.student_birthdate" label="Geburtsdatum (JJJJ-MM-TT)" :rules="[dateOrNull()]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="person.note" label="Anmerkungen" :rules="[maxLength(255)]" class="mb-2" />
                    <v-checkbox density="compact" label="Verständigung per E-Mail?" v-model="person.is_notify" color="primary" hide-details class="mb-2" />
                </v-form>
            </v-card-text>
            <v-card-actions class="px-4 pb-4 ga-2">
                <v-btn color="secondary" variant="tonal" rounded="lg" @click="step--">Zurück</v-btn>
                <v-btn color="success" variant="flat" rounded="lg" @click="createBooking(selectedRegisterDate, person)" class="flex-1-1">
                    <v-icon size="16" class="mr-1">mdi-check</v-icon>Buchen
                </v-btn>
                <v-btn color="error" variant="tonal" rounded="lg" @click="abort">Abbruch</v-btn>
            </v-card-actions>
        </v-card>

    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import { useRegisterDateStore } from '@/stores/admin/RegisterDateStore'
import { useRegisterDateBookingStore } from '@/stores/admin/RegisterDateBookingStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerStore = useRegisterStore()
        this.registerDateStore = useRegisterDateStore()
        this.registerDateBookingStore = useRegisterDateBookingStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
            registerDateStore: null,
            registerDateBookingStore: null,
            is_valid: false,
            step: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'selected_active_register', 'action']),
        ...mapWritableState(useRegisterStore, []),
        ...mapWritableState(useRegisterDateStore, ['selected_day', 'selected_register_dates', 'register_dates', 'days']),
        ...mapWritableState(useRegisterDateBookingStore, ['person', 'user']),

        selectedRegisterDate() {
            const selectedId = this.selected_register_dates[0]
            return this.register_dates.find((item) => item.id === selectedId)
        },
    },

    watch: {},

    methods: {
        abort() {
            this.action = ''
        },
        async getUserWithEmail(person) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.user = null
            if (!(await this.registerDateBookingStore.getUserWithEmail(person))) return
            this.person.last_name = this.user ? this.user.last_name : null
            this.person.first_name = this.user ? this.user.first_name : null
            this.person.phone = this.user ? this.user.phone : null
            this.step = 1
        },
        async updateOrCreateUser(person) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.user = null
            if (!(await this.registerDateBookingStore.updateOrCreateUser(person))) return
            this.person.last_name = this.user ? this.user.last_name : null
            this.person.first_name = this.user ? this.user.first_name : null
            this.person.phone = this.user ? this.user.phone : null
            this.person.student_last_name = this.person.last_name
            this.step = 2
        },
        async createBooking(register_date, person) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.user = null
            person.register_date_id = register_date.id
            if (!(await this.registerDateBookingStore.createBooking(person))) return
            await this.registerDateStore.loadDays()
            this.selected_day = this.days.find((item) => item.id === this.selected_day.id)
            const index = this.register_dates.findIndex((d) => d.id === register_date.id)
            this.register_dates[index].count_bookings++
            this.action = ''
        },
    },
}
</script>

<style scoped>
.rd-card {
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;
    backdrop-filter: blur(4px);
    color: #10263a !important;
}

.rd-card__header { display: flex; align-items: center; gap: 10px; }

.rd-card__icon-wrap {
    display: flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 8px;
    background: rgba(99, 102, 241, 0.18); color: #818cf8; flex-shrink: 0;
}

.rd-card__icon-wrap--success { background: rgba(34, 197, 94, 0.16); color: #4ade80; }

.rd-card__header-title { font-size: 0.95rem; font-weight: 700; color: #10263a; line-height: 1.2; }
.rd-card__header-sub { font-size: 0.74rem; color: rgba(16, 38, 58, 0.62); }

.rd-date-info {
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px;
    padding: 10px 12px;
}

.rd-date-info__row {
    display: flex; align-items: center;
    font-size: 0.82rem; color: rgba(16, 38, 58, 0.68); padding: 2px 0;
}

.rd-email-badge {
    font-size: 0.88rem; font-weight: 600; color: #818cf8;
    background: rgba(99, 102, 241, 0.12);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px; padding: 6px 12px;
}

.rd-person-summary {
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px; padding: 10px 12px;
}

.rd-person-summary__name { font-size: 0.92rem; font-weight: 700; color: #10263a; margin-bottom: 4px; }
.rd-person-summary__detail { display: flex; align-items: center; font-size: 0.8rem; color: rgba(16, 38, 58, 0.62); }
</style>
