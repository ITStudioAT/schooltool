<template>
    <v-col cols="12" md="6" xl="4">
        <v-card rounded="xl" class="rd-card" flat>
            <v-card-text class="pa-4">
                <div class="rd-card__header mb-4">
                    <div class="rd-card__icon-wrap rd-card__icon-wrap--success">
                        <v-icon size="18" icon="mdi-calendar-plus" />
                    </div>
                    <div class="rd-card__header-title">Neue Termine anlegen</div>
                </div>

                <v-form ref="form" v-model="is_valid" @submit.prevent="createDates(data)">
                    <v-text-field autofocus variant="outlined" density="comfortable" rounded="lg" v-model="data.date_from" hide-details label="Datum von (JJJJ-MM-TT)" :rules="[date()]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.date_until" hide-details label="Datum bis (optional)" :rules="[dateOrNull()]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.time_from" label="Uhrzeit von (hh:mm)" :rules="[required(), time()]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.time_until" label="Uhrzeit bis (hh:mm)" :rules="[required(), time()]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.min_per_date" label="Minuten pro Termin" :rules="[required(), min(1)]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.pause" label="Pause in Minuten" :rules="[required(), min(0)]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.max_registrations" label="Max. Registrierungen pro Termin (0 = unbegrenzt)" :rules="[required(), min(0)]" class="mb-3" />

                    <div class="rd-info-block mb-3">
                        <v-icon size="14" class="mr-1">mdi-information-outline</v-icon>
                        Tage, an denen Termine erstellt werden.
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <v-checkbox hide-details density="compact" v-model="data.monday" label="Mo" color="primary" />
                        <v-checkbox hide-details density="compact" v-model="data.tuesday" label="Di" color="primary" />
                        <v-checkbox hide-details density="compact" v-model="data.wednesday" label="Mi" color="primary" />
                        <v-checkbox hide-details density="compact" v-model="data.thursday" label="Do" color="primary" />
                        <v-checkbox hide-details density="compact" v-model="data.friday" label="Fr" color="primary" />
                        <v-checkbox hide-details density="compact" v-model="data.saturday" label="Sa" color="primary" />
                        <v-checkbox hide-details density="compact" v-model="data.sunday" label="So" color="primary" />
                    </div>

                    <div class="rd-info-block mb-3">
                        <v-icon size="14" class="mr-1">mdi-information-outline</v-icon>
                        Gleiche Termine werden für jeden Berater angelegt.
                    </div>

                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.supervisor_1" label="Berater 1" :rules="[required(), maxLength(255)]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.supervisor_2" label="Berater 2" :rules="[maxLength(255)]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.supervisor_3" label="Berater 3" :rules="[maxLength(255)]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.supervisor_4" label="Berater 4" :rules="[maxLength(255)]" class="mb-2" />
                    <v-text-field variant="outlined" density="comfortable" rounded="lg" v-model="data.supervisor_5" label="Berater 5" :rules="[maxLength(255)]" class="mb-4" />
                </v-form>
            </v-card-text>

            <v-card-actions class="px-4 pb-4 ga-2">
                <v-btn color="success" variant="flat" rounded="lg" @click="createDates(data)" class="flex-1-1">
                    <v-icon size="16" class="mr-1">mdi-check</v-icon>
                    Speichern
                </v-btn>
                <v-btn color="error" variant="tonal" rounded="lg" @click="abort" class="flex-1-1">
                    Abbruch
                </v-btn>
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

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerStore = useRegisterStore()
        this.registerDateStore = useRegisterDateStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'selected_active_register', 'action']),
        ...mapWritableState(useRegisterStore, []),
        ...mapWritableState(useRegisterDateStore, ['data', 'selected_day']),
    },

    watch: {},

    methods: {
        abort() {
            this.action = ''
        },
        async createDates(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            if (!(await this.registerDateStore.createDates(data))) return
            await this.registerStore.index()
            await this.registerDateStore.loadDays()
            if (this.selected_day) {
                await this.registerDateStore.loadRegisterDates(this.selected_day.date)
            }
            this.action = ''
        },
    },
}
</script>

<style scoped>
.rd-card {
    border: 1px solid rgba(148, 163, 184, 0.14);
    background: rgba(30, 41, 59, 0.82) !important;
    backdrop-filter: blur(4px);
    color: #e2e8f0 !important;
}

.rd-card__header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rd-card__icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: rgba(99, 102, 241, 0.18);
    color: #818cf8;
    flex-shrink: 0;
}

.rd-card__icon-wrap--success {
    background: rgba(34, 197, 94, 0.16);
    color: #4ade80;
}

.rd-card__header-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #f1f5f9;
}

.rd-info-block {
    display: flex;
    align-items: center;
    font-size: 0.8rem;
    color: #64748b;
    background: rgba(15, 23, 42, 0.4);
    border: 1px solid rgba(148, 163, 184, 0.1);
    border-radius: 8px;
    padding: 8px 12px;
}

.gap-2 {
    gap: 8px;
}
</style>
