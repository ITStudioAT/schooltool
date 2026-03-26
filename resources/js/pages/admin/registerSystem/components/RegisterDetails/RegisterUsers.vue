<template>
    <!-- Column 1: User list -->
    <v-col cols="12" md="7" xl="5">
        <v-card rounded="xl" class="rd-card" flat>
            <v-card-text class="pa-4">
                <div class="rd-card__header mb-3">
                    <div class="rd-card__icon-wrap">
                        <v-icon size="18" icon="mdi-account-multiple" />
                    </div>
                    <div class="rd-card__header-title">Benutzer</div>
                    <div class="ml-auto d-flex align-center ga-2" v-if="count_deletable_users > 0">
                        <div class="rd-cleanup-info">
                            {{ count_deletable_users }} ohne Anmeldung
                        </div>
                        <v-btn
                            prepend-icon="mdi-vacuum"
                            variant="tonal"
                            color="warning"
                            size="small"
                            rounded="lg"
                            @click="deleteUsers">
                            Bereinigen
                        </v-btn>
                    </div>
                </div>

                <SearchField :store="registerUserStore" selected_field="selected_register_users" class="mb-2" />

                <div class="d-flex align-center ga-2 mb-2" :class="{ 'rd-locked': action_2 !== '' }">
                    <v-btn size="x-small" variant="tonal" color="secondary" rounded="lg" @click="selectAll">
                        Alle ({{ register_users.length - selected_register_users.length }})
                    </v-btn>
                    <v-btn size="x-small" variant="tonal" color="secondary" rounded="lg" @click="unselectAll">
                        Abwählen ({{ selected_register_users.length }})
                    </v-btn>
                </div>

                <v-list
                    variant="flat"
                    select-strategy="leaf"
                    v-model:selected="selected_register_users"
                    color="success"
                    bg-color="transparent"
                    density="compact">
                    <v-list-item
                        v-for="item in register_users"
                        :key="item.id"
                        :value="item.id"
                        rounded="lg"
                        class="rd-user-item mb-1">
                        <template #title>
                            <div class="rd-user-item__name">{{ item.last_name }} {{ item.first_name }}</div>
                            <div class="rd-user-item__email">{{ item.email }}</div>
                            <div
                                v-for="booking in item.registerDateBookings"
                                :key="booking.id"
                                class="rd-user-item__booking">
                                <v-icon size="12" class="mr-1">mdi-human-child</v-icon>
                                {{ booking.student_last_name }} {{ booking.student_first_name }}
                                <span v-if="booking.student_birthdate" class="ml-2">
                                    <v-icon size="11" class="mr-1">mdi-cake-variant-outline</v-icon>{{ booking.student_birthdate }}
                                </span>
                                <template v-for="(sibling, si) in (booking.siblings || [])" :key="si">
                                    <span class="rd-user-item__sibling">
                                        <v-icon size="11" class="mr-1">mdi-account-multiple</v-icon>
                                        {{ sibling.last_name }} {{ sibling.first_name || '' }}
                                        <span v-if="sibling.birthdate" class="ml-1">
                                            <v-icon size="11" class="mr-1">mdi-cake-variant-outline</v-icon>{{ sibling.birthdate }}
                                        </span>
                                    </span>
                                </template>
                            </div>
                        </template>
                    </v-list-item>
                </v-list>

                <Pagination :meta="meta" :store="registerUserStore" selected_field="selected_register_users" class="mt-2" />
            </v-card-text>
        </v-card>
    </v-col>

    <!-- Column 2: Info card -->
    <v-col cols="12" md="5" xl="3">
        <v-card rounded="xl" class="rd-card" flat>
            <v-card-text class="pa-4">
                <div class="rd-card__header mb-4">
                    <div class="rd-card__icon-wrap">
                        <v-icon size="18" icon="mdi-chart-bar" />
                    </div>
                    <div class="rd-card__header-title">Statistik</div>
                </div>

                <div class="rd-stat-row">
                    <div class="rd-stat-row__label">
                        <v-icon size="14" class="mr-2">mdi-account-multiple-outline</v-icon>
                        Benutzer gesamt
                    </div>
                    <div class="rd-stat-row__value">{{ count_all_register_users }}</div>
                </div>

                <div class="rd-stat-row">
                    <div class="rd-stat-row__label">
                        <v-icon size="14" class="mr-2">mdi-account-check-outline</v-icon>
                        Benutzer in dieser Anmeldung
                    </div>
                    <div class="rd-stat-row__value rd-stat-row__value--success">{{ count_users_in_register }}</div>
                </div>

                <div class="rd-stat-row" :class="{ 'rd-stat-row--warning': count_deletable_users > 0 }">
                    <div class="rd-stat-row__label">
                        <v-icon size="14" class="mr-2">mdi-account-off-outline</v-icon>
                        Benutzer ohne Anmeldung
                    </div>
                    <div class="rd-stat-row__value" :class="{ 'rd-stat-row__value--warning': count_deletable_users > 0 }">{{ count_deletable_users }}</div>
                </div>
            </v-card-text>
        </v-card>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'
import { useRegisterUserStore } from '@/stores/admin/RegisterUserStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerUserStore = useRegisterUserStore()
        this.register_id = this.selected_register.id
        await this.registerUserStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerUserStore: null,
            is_valid: false,
            upload_file: null,
            is_uploading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config', 'selected_register']),
        ...mapWritableState(useRegisterUserStore, [
            'register_users',
            'meta',
            'selected_register_users',
            'search_string',
            'data',
            'answer',
            'register_id',
            'count',
            'count_all_register_users',
            'count_users_in_register',
            'count_deletable_users',
        ]),
    },

    methods: {
        onUploadStart() {
            this.is_uploading = true
        },
        async deleteUsers() {
            this.action = 'deleteUsers'
            await this.registerUserStore.deleteRegisterUsers()
            await this.registerUserStore.index()
            this.action = ''
        },
        selectAll() {
            this.selected_register_users = this.register_users.map((item) => item.id)
        },
        unselectAll() {
            this.selected_register_users = []
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

.rd-card__header { display: flex; align-items: center; gap: 10px; }

.rd-card__icon-wrap {
    display: flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 8px;
    background: rgba(99, 102, 241, 0.18); color: #818cf8; flex-shrink: 0;
}

.rd-card__header-title { font-size: 0.95rem; font-weight: 700; color: #f1f5f9; }

.rd-cleanup-info { font-size: 0.76rem; color: #64748b; }

.rd-locked { opacity: 0.6; pointer-events: none; }

.rd-user-item { border-radius: 8px !important; }
.rd-user-item__name { font-size: 0.88rem; font-weight: 600; color: #e2e8f0; }
.rd-user-item__email { font-size: 0.76rem; color: #64748b; }

.rd-user-item__booking {
    display: flex; align-items: center; flex-wrap: wrap;
    font-size: 0.78rem; color: #94a3b8; margin-top: 2px;
}

.rd-user-item__sibling {
    display: inline-flex; align-items: center;
    margin-left: 12px; font-size: 0.75rem; color: #64748b;
}

:deep(.v-list-item) {
    background: rgba(15, 23, 42, 0.4) !important;
    border: 1px solid rgba(148, 163, 184, 0.1);
}

:deep(.v-list-item--active) {
    background: rgba(34, 197, 94, 0.12) !important;
    border-color: rgba(34, 197, 94, 0.25) !important;
}

:deep(.v-list-item__content),
:deep(.v-list-item-title) {
    color: #e2e8f0 !important;
}

.rd-stat-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 12px;
    background: rgba(15, 23, 42, 0.4);
    border: 1px solid rgba(148, 163, 184, 0.1);
    border-radius: 8px;
    margin-bottom: 6px;
}

.rd-stat-row--warning {
    border-color: rgba(234, 179, 8, 0.2);
    background: rgba(234, 179, 8, 0.05);
}

.rd-stat-row__label {
    display: flex; align-items: center;
    font-size: 0.82rem; color: #94a3b8;
}

.rd-stat-row__value {
    font-size: 1rem; font-weight: 700; color: #f1f5f9;
}

.rd-stat-row__value--success { color: #4ade80; }
.rd-stat-row__value--warning { color: #facc15; }
</style>
