<template>
    <v-row class="w-100 mb-1" dense v-if="selected_register">
        <!-- Status card -->
        <v-col cols="12" sm="6" xl="4">
            <v-card rounded="xl" class="rd-card" flat>
                <v-card-text class="pa-4">
                    <div class="rd-card__header mb-3">
                        <div class="rd-card__icon-wrap" :class="selected_register.is_active ? 'rd-card__icon-wrap--success' : 'rd-card__icon-wrap--muted'">
                            <v-icon size="18" :icon="selected_register.is_active ? 'mdi-check-circle-outline' : 'mdi-circle-off-outline'" />
                        </div>
                        <div class="rd-card__header-title">{{ selected_register.name }}</div>
                        <v-btn
                            class="ml-auto"
                            icon="mdi-power-standby"
                            variant="tonal"
                            :color="selected_register.is_active ? 'error' : 'success'"
                            size="small"
                            :title="selected_register.is_active ? 'Schließen' : 'Öffnen'"
                            :disabled="action !== ''"
                            @click="toggleRegister(selected_register)" />
                    </div>
                    <div class="rd-status-badge" :class="selected_register.is_active ? 'rd-status-badge--open' : 'rd-status-badge--closed'">
                        <div class="rd-status-badge__dot"></div>
                        Anmeldesystem {{ selected_register.is_active ? 'geöffnet' : 'geschlossen' }}
                    </div>
                </v-card-text>
            </v-card>
        </v-col>

        <!-- Stats card -->
        <v-col cols="12" sm="6" xl="4">
            <v-card rounded="xl" class="rd-card" flat>
                <v-card-text class="pa-4">
                    <div class="rd-card__header mb-3">
                        <div class="rd-card__icon-wrap">
                            <v-icon size="18" icon="mdi-chart-bar" />
                        </div>
                        <div class="rd-card__header-title">Statistik</div>
                    </div>
                    <div class="rd-stats">
                        <div class="rd-stat-row">
                            <span class="rd-stat-label">Anmeldungen</span>
                            <span class="rd-stat-value">{{ selected_register.bookings_count }}</span>
                        </div>
                        <div class="rd-stat-row">
                            <span class="rd-stat-label">Termine</span>
                            <span class="rd-stat-value">{{ selected_register.dates_count }}</span>
                        </div>
                        <div class="rd-stat-row">
                            <span class="rd-stat-label">Tage</span>
                            <span class="rd-stat-value">{{ selected_register.different_dates_count }}</span>
                        </div>
                    </div>
                </v-card-text>
            </v-card>
        </v-col>
    </v-row>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerStore = useRegisterStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'selected_active_register', 'action']),
        ...mapWritableState(useRegisterStore, ['active_registers']),
    },

    watch: {},

    methods: {
        async toggleRegister(register) {
            await this.registerStore.toggleRegister(register)
            await this.registerStore.loadActiveRegisters()
            this.selected_register.is_active = !this.selected_register.is_active
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
    height: 100%;
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

.rd-card__icon-wrap--muted {
    background: rgba(148, 163, 184, 0.1);
    color: #475569;
}

.rd-card__header-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #f1f5f9;
}

.rd-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.84rem;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 999px;
}

.rd-status-badge__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}

.rd-status-badge--open {
    background: rgba(34, 197, 94, 0.14);
    color: #4ade80;
}

.rd-status-badge--open .rd-status-badge__dot {
    background: #4ade80;
    box-shadow: 0 0 6px rgba(74, 222, 128, 0.6);
}

.rd-status-badge--closed {
    background: rgba(148, 163, 184, 0.1);
    color: #64748b;
}

.rd-status-badge--closed .rd-status-badge__dot {
    background: #475569;
}

.rd-stats {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.rd-stat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.4);
    border: 1px solid rgba(148, 163, 184, 0.1);
}

.rd-stat-label {
    font-size: 0.84rem;
    color: #94a3b8;
}

.rd-stat-value {
    font-size: 1rem;
    font-weight: 700;
    color: #f1f5f9;
}
</style>
