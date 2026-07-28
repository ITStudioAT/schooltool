<template>
    <v-row class="w-100" dense>

        <!-- Register list -->
        <v-col cols="12" md="6" xl="4">
            <v-card rounded="xl" class="rs-card" flat>
                <v-card-text class="pa-4">
                    <div class="rs-card__header mb-3">
                        <div class="rs-card__icon-wrap">
                            <v-icon size="18" icon="mdi-clipboard-list-outline" />
                        </div>
                        <div>
                            <div class="rs-card__header-title">Anmeldetools</div>
                            <div class="rs-card__header-sub" v-if="selected_schoolyear">{{ selected_schoolyear.name }}</div>
                        </div>
                        <div class="ml-auto d-flex align-center ga-1" v-if="config?.user?.roles.some((role) => ['super_admin', 'admin', 'register_admin'].includes(role))">
                            <v-btn
                                v-if="selected_register"
                                icon="mdi-pencil-outline"
                                variant="tonal"
                                color="primary"
                                size="small"
                                title="Bearbeiten"
                                :disabled="action !== ''"
                                @click="edit(selected_register)" />
                            <v-btn
                                v-if="selected_register"
                                icon="mdi-delete-outline"
                                variant="tonal"
                                color="warning"
                                size="small"
                                title="Löschen"
                                :disabled="action !== ''"
                                @click="remove(selected_register)" />
                            <v-btn
                                v-if="selected_register"
                                icon="mdi-power-standby"
                                variant="tonal"
                                :color="selected_register.is_active ? 'error' : 'success'"
                                size="small"
                                :title="selected_register.is_active ? 'Anmeldesystem schließen' : 'Anmeldesystem öffnen'"
                                :disabled="action !== ''"
                                @click="toggleRegister(selected_register)" />
                            <v-btn
                                v-if="selected_register"
                                variant="flat"
                                color="primary"
                                size="small"
                                prepend-icon="mdi-arrow-right"
                                to="/admin/register_system/details"
                                class="rs-details-btn"
                                :disabled="action !== ''">
                                Details
                            </v-btn>
                            <v-btn
                                icon="mdi-plus"
                                variant="tonal"
                                color="success"
                                size="small"
                                title="Neues Anmeldesystem"
                                :disabled="action !== ''"
                                @click="create" />
                        </div>
                    </div>

                    <div v-if="!registers.length" class="rs-card__empty">
                        <v-icon size="16" class="mr-1">mdi-information-outline</v-icon>
                        Keine Anmeldetools für dieses Schuljahr.
                    </div>

                    <div v-else class="rs-card__list">
                        <div
                            v-for="register in registers"
                            :key="register.id"
                            class="rs-list-item"
                            :class="{ 'rs-list-item--selected': register.id === selected_register?.id }"
                            @click="setSelectedRegister(register)">
                            <div class="rs-list-item__name">{{ register.name }}</div>
                            <div class="ml-auto">
                                <v-chip
                                    size="x-small"
                                    :color="register.is_active ? 'success' : 'default'"
                                    :variant="register.is_active ? 'flat' : 'tonal'"
                                    class="rs-list-item__badge">
                                    {{ register.is_active ? 'Geöffnet' : 'Geschlossen' }}
                                </v-chip>
                            </div>
                        </div>
                    </div>
                </v-card-text>
            </v-card>
        </v-col>

        <!-- Edit / Create dialog -->
        <v-dialog :model-value="action === 'edit_register' || action === 'create_register'" max-width="560" persistent>
            <v-card rounded="xl" class="rs-dialog-card">
                <v-card-text class="pa-5">
                    <div class="rs-card__header mb-5">
                        <div class="rs-card__icon-wrap" :class="action === 'create_register' ? 'rs-card__icon-wrap--success' : ''">
                            <v-icon size="18" :icon="action === 'create_register' ? 'mdi-plus-circle-outline' : 'mdi-pencil-outline'" />
                        </div>
                        <div>
                            <div class="rs-card__header-title">{{ data.id ? selected_register.name : 'Neues Anmeldesystem' }}</div>
                            <div class="rs-card__header-sub">{{ action === 'create_register' ? 'Neues Tool anlegen' : 'Einstellungen bearbeiten' }}</div>
                        </div>
                    </div>

                    <v-form ref="form" v-model="is_valid" @submit.prevent="save(data)">
                        <v-text-field
                            autofocus
                            variant="outlined"
                            density="comfortable"
                            rounded="lg"
                            v-model="data.name"
                            label="Bezeichnung"
                            :rules="[required(), maxLength(255)]"
                            class="mb-2" />

                        <div class="mb-4">
                            <label class="rs-field-label">Beschreibung am Bildschirm</label>
                            <ItsRichTextEditor
                                v-if="action === 'edit_register' || action === 'create_register'"
                                v-model="data.description_on_website" />
                        </div>

                        <v-text-field
                            variant="outlined"
                            density="comfortable"
                            rounded="lg"
                            v-model="data.max_registrations"
                            label="Max. Anmeldungen gesamt (0 = unbegrenzt)"
                            :rules="[required(), min(0)]"
                            class="mb-3" />

                        <div class="rs-info-block mb-4">
                            <v-icon size="15" class="mr-1">mdi-information-outline</v-icon>
                            Nachname, Vorname und E-Mail sind immer Pflichtfelder. Weitere Felder können hier aktiviert werden.
                        </div>

                        <div class="rs-field-group mb-1">
                            <div class="rs-field-group__row">
                                <v-checkbox v-model="data.show_phone" hide-details density="compact" label="Telefon" color="primary" />
                                <v-checkbox v-if="data.show_phone" v-model="data.must_phone" hide-details density="compact" label="Pflichtfeld" color="warning" />
                            </div>
                            <div class="rs-field-group__row">
                                <v-checkbox v-model="data.show_student_last_name" hide-details density="compact" label="Nachname Kind" color="primary" />
                                <v-checkbox v-if="data.show_student_last_name" v-model="data.must_student_last_name" hide-details density="compact" label="Pflichtfeld" color="warning" />
                            </div>
                            <div class="rs-field-group__row">
                                <v-checkbox v-model="data.show_student_first_name" hide-details density="compact" label="Vorname Kind" color="primary" />
                                <v-checkbox v-if="data.show_student_first_name" v-model="data.must_student_first_name" hide-details density="compact" label="Pflichtfeld" color="warning" />
                            </div>
                            <div class="rs-field-group__row">
                                <v-checkbox v-model="data.show_student_birthdate" hide-details density="compact" label="Geburtsdatum" color="primary" />
                                <v-checkbox v-if="data.show_student_birthdate" v-model="data.must_student_birthdate" hide-details density="compact" label="Pflichtfeld" color="warning" />
                            </div>
                            <div class="rs-field-group__row">
                                <v-checkbox v-model="data.show_note" hide-details density="compact" label="Anmerkungen" color="primary" />
                                <v-checkbox v-if="data.show_note" v-model="data.must_note" hide-details density="compact" label="Pflichtfeld" color="warning" />
                            </div>
                        </div>

                        <v-divider class="my-3" style="border-color: rgba(148,163,184,0.16)" />

                        <v-checkbox
                            v-model="data.allow_siblings"
                            hide-details
                            density="compact"
                            label="Mehrere Kinder pro Buchung erlauben (Geschwister)"
                            color="primary"
                            class="mb-2" />
                    </v-form>
                </v-card-text>

                <v-card-actions class="px-5 pb-5 ga-2">
                    <v-btn color="success" variant="flat" rounded="lg" @click="save(data)" class="flex-1-1">
                        <v-icon size="16" class="mr-1">mdi-check</v-icon>
                        Speichern
                    </v-btn>
                    <v-btn color="error" variant="tonal" rounded="lg" @click="abort" class="flex-1-1">
                        Abbruch
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete confirmation dialog -->
        <v-dialog v-model="showDeleteDialog" max-width="420" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                    Anmeldesystem löschen
                </v-card-title>
                <v-card-text class="px-4">
                    Soll das Anmeldesystem <strong>„{{ selected_register?.name }}"</strong> wirklich gelöscht werden? Diese Aktion kann nicht rückgängig gemacht werden.
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn variant="tonal" @click="abort">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="error" variant="flat" @click="destroy(selected_register)">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

    </v-row>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'

const ItsRichTextEditor = defineAsyncComponent(() => import('@/components/ItsRichTextEditor.vue'))

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsRichTextEditor },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        this.registerStore = useRegisterStore()
        await this.loadRegisters()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,
            registerStore: null,
            is_valid: false,
            data: {},
            selected_register_array: [],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'selected_active_register', 'action']),
        ...mapWritableState(useSchoolyearStore, []),
        ...mapWritableState(useRegisterStore, ['registers']),

        showDeleteDialog() {
            return this.action === 'remove_register'
        },
    },

    watch: {
        async selected_schoolyear() {
            if (this.selected_schoolyear) {
                this.loadRegisters()
            } else {
                this.registerStore.registers = []
                this.registerStore.selected_register = null
            }
        },
    },

    methods: {
        async loadRegisters() {
            await this.registerStore.index()
            if (this.selected_register) {
                this.selected_register = this.registers.find((r) => r.id === this.selected_register.id)
                this.selected_register_array = [this.selected_register]
            }
            await this.registerStore.loadActiveRegisters()
        },

        async save(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            var answer = false
            if (data.id) {
                answer = await this.registerStore.update(data)
            } else {
                answer = await this.registerStore.store(data)
            }
            await this.loadRegisters()
            if (answer) this.action = ''
        },

        async destroy(data) {
            if (!(await this.registerStore.destroy(data))) return
            await this.loadRegisters()
            this.action = ''
            this.selected_register = null
        },

        abort() {
            this.action = ''
            this.data = {}
        },

        edit(selected_register) {
            this.data = JSON.parse(JSON.stringify(selected_register))
            this.action = 'edit_register'
        },

        remove() {
            this.action = 'remove_register'
        },

        create() {
            this.data = {
                max_registrations: 0,
                is_active: false,
                show_phone: false,
                must_phone: false,
                show_student_last_name: false,
                must_student_last_name: false,
                show_student_first_name: false,
                must_student_first_name: false,
                show_student_birthdate: false,
                must_student_birthdate: false,
                allow_siblings: false,
            }
            this.action = 'create_register'
        },

        async setSelectedRegister(register) {
            await this.registerStore.setSelectedRegister(register.id)
            this.selected_register = register
            this.selected_register_array = [register]
        },

        async toggleRegister(register) {
            await this.registerStore.toggleRegister(register)
            await this.loadRegisters()
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
    gap: 12px;
}

.rs-card__icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: rgba(99, 102, 241, 0.18);
    color: #818cf8;
    flex-shrink: 0;
}

.rs-card__icon-wrap--success {
    background: rgba(34, 197, 94, 0.16);
    color: #4ade80;
}

.rs-card__header-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #10263a;
    line-height: 1.2;
}

.rs-card__header-sub {
    font-size: 0.76rem;
    color: rgba(16, 38, 58, 0.68);
    margin-top: 1px;
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
    padding: 9px 10px;
    border-radius: 9px;
    cursor: pointer;
    border: 1px solid rgba(16, 38, 58, 0.18);
    transition: background 0.15s;
}

.rs-list-item:hover {
    background: rgba(46, 104, 171, 0.045);
}

.rs-list-item--selected {
    background: rgba(99, 102, 241, 0.12) !important;
    border-color: rgba(99, 102, 241, 0.28) !important;
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

.rs-list-item__dot--inactive {
    background: #475569;
}

.rs-list-item__name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #10263a;
    flex: 1;
}

.rs-details-btn {
    font-weight: 700 !important;
    letter-spacing: 0.02em;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
}

.rs-list-item__badge {
    font-size: 0.7rem !important;
    letter-spacing: 0;
}

.rs-info-block {
    display: flex;
    align-items: flex-start;
    font-size: 0.8rem;
    color: rgba(16, 38, 58, 0.72);
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    padding: 10px 12px;
}

.rs-field-label {
    display: block;
    font-size: 0.76rem;
    color: rgba(16, 38, 58, 0.68);
    margin-bottom: 6px;
    letter-spacing: 0.02em;
}

.rs-field-group {
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 10px;
    padding: 4px 8px;
    background: rgba(255, 255, 255, 0.72);
}

.rs-field-group__row {
    display: flex;
    align-items: center;
    gap: 16px;
    min-height: 36px;
}

.rs-dialog-card {
    border: 1px solid rgba(16, 38, 58, 0.1);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.96)) !important;
    color: #10263a !important;
}

.rs-dialog-card :deep(.toolbar .v-btn) {
    color: #64748b;
    border-color: rgba(16, 38, 58, 0.16) !important;
}

.rs-dialog-card :deep(.toolbar .v-btn--variant-flat) {
    color: #fff;
    background: rgba(99, 102, 241, 0.7) !important;
}

.rs-dialog-card :deep(.toolbar .v-btn-group) {
    border-color: rgba(148, 163, 184, 0.22) !important;
}

.rs-dialog-card :deep(.editor-content) {
    border-color: rgba(16, 38, 58, 0.16) !important;
    background: rgba(255, 255, 255, 0.82);
    color: #10263a;
}

.rs-dialog-card :deep(.ProseMirror) {
    color: #10263a;
}
</style>
