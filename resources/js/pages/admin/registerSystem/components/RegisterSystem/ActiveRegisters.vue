<template>
    <v-row class="w-100 mb-1" dense>
        <v-col cols="12" md="6" xl="4">
            <v-card rounded="xl" class="rs-card" flat>
                <v-card-text class="pa-4">
                    <div class="rs-card__header mb-3">
                        <div class="rs-card__icon-wrap" :class="hasActive ? 'rs-card__icon-wrap--success' : ''">
                            <v-icon size="18" :icon="hasActive ? 'mdi-check-circle-outline' : 'mdi-circle-off-outline'" />
                        </div>
                        <div class="rs-card__header-title">Geöffnete Anmeldesysteme</div>
                        <div class="ml-auto" v-if="selected_active_register && config?.user?.roles.some((role) => ['super_admin', 'admin', 'register_admin'].includes(role))">
                            <v-btn
                                icon="mdi-power-standby"
                                variant="tonal"
                                color="success"
                                size="small"
                                title="Anmeldesystem schließen"
                                @click="toggleRegister(selected_active_register)" />
                        </div>
                    </div>

                    <div v-if="active_registers?.length === 0" class="rs-card__empty">
                        <v-icon size="16" class="mr-1">mdi-information-outline</v-icon>
                        Kein Anmeldesystem aktuell geöffnet.
                    </div>

                    <div v-else class="rs-card__list">
                        <div
                            v-for="register in active_registers"
                            :key="register.id"
                            class="rs-list-item"
                            :class="{ 'rs-list-item--selected': selected_active_register_array?.includes(register) }"
                            @click="selected_active_register_array = [register]">
                            <div class="rs-list-item__dot rs-list-item__dot--active"></div>
                            <div class="rs-list-item__body">
                                <div class="rs-list-item__name">{{ register.name }}</div>
                                <div class="rs-list-item__meta">{{ register.schoolyear_name }}</div>
                            </div>
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
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        this.registerStore = useRegisterStore()
        await this.loadActiveRegisters()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,
            registerStore: null,
            is_valid: false,
            data: {},
            selected_active_register_array: [],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'action']),
        ...mapWritableState(useSchoolyearStore, ['selected_active_register']),
        ...mapWritableState(useRegisterStore, ['registers', 'active_registers']),

        hasActive() {
            return Array.isArray(this.active_registers) && this.active_registers.length > 0
        },

        selected_active_register: {
            get() {
                return this.selected_active_register_array?.[0]
            },
            set(registerObj) {
                this.selected_active_register_array = registerObj ? [registerObj] : []
            },
        },
    },

    watch: {},

    methods: {
        async loadActiveRegisters() {
            this.registerStore.loadActiveRegisters()
        },

        async setActiveRegister(register) {
            await this.registerStore.setActiveRegister(register.id)
            this.selected_register = register
        },

        async toggleRegister(register) {
            await this.registerStore.toggleRegister(register)
            await this.registerStore.loadActiveRegisters()
            this.selected_active_register = null
        },
    },
}
</script>

<style scoped>
.rs-card {
    border: 1px solid rgba(16, 38, 58, 0.1);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;
    backdrop-filter: blur(4px);
    color: #10263a !important;
}

.rs-card__header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rs-card__icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(148, 163, 184, 0.12);
    color: #64748b;
    flex-shrink: 0;
}

.rs-card__icon-wrap--success {
    background: rgba(34, 197, 94, 0.16);
    color: #4ade80;
}

.rs-card__header-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #10263a;
}

.rs-card__empty {
    display: flex;
    align-items: center;
    font-size: 0.84rem;
    color: rgba(16, 38, 58, 0.68);
    padding: 6px 0;
}

.rs-card__list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.rs-list-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: background 0.15s;
}

.rs-list-item:hover {
    background: rgba(46, 104, 171, 0.045);
}

.rs-list-item--selected {
    background: rgba(34, 197, 94, 0.1) !important;
    border-color: rgba(34, 197, 94, 0.25) !important;
}

.rs-list-item__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.rs-list-item__dot--active {
    background: #4ade80;
    box-shadow: 0 0 6px rgba(74, 222, 128, 0.5);
}

.rs-list-item__body {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.rs-list-item__name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #10263a;
}

.rs-list-item__meta {
    font-size: 0.76rem;
    color: rgba(16, 38, 58, 0.68);
}
</style>
