<template>
    <v-row class="w-100">
        <v-col cols="12">
            <its-grid-box
                color="primary"
                :title="'Anmeldesysteme ' + selected_schoolyear?.name"
                class="h-100 w-100"
                :disabled="action != ''">
                <div class="d-flex flex-wrap flex-row align-center ga-2">
                    <its-menu-button
                        :title="register.name"
                        :color="register.id == selected_register?.id ? 'success' : 'primary'"
                        :icon="register.is_active ? 'mdi-power-standby' : ''"
                        @click="setSelectedRegister(register)"
                        v-for="register in registers" />
                </div>
                <template
                    v-slot:title
                    v-if="
                        config?.user?.roles.some((role) => ['super_admin', 'admin', 'register_admin'].includes(role))
                    ">
                    <div class="d-flex flex-row align-center justify-space-between w-100">
                        <div class="mr-4">Anmeldesysteme {{ selected_schoolyear?.name }}</div>
                        <div class="d-flex flex-row align-center">
                            <v-btn flat tile icon="mdi-plus" color="primary" @click="create" />
                            <div class="d-flex flex-row align-center" v-if="selected_register">
                                <v-btn flat tile icon="mdi-pencil" color="primary" @click="edit(selected_register)" />
                                <v-btn flat tile icon color="primary" @click="remove(selected_register)">
                                    <v-icon icon="mdi-delete" color="warning"></v-icon>
                                </v-btn>
                                <v-btn flat tile @click="toggleRegister(selected_register)" icon color="primary">
                                    <v-icon
                                        icon="mdi-power-standby"
                                        :color="selected_register.is_active ? 'success' : 'error'"></v-icon>
                                </v-btn>
                                <v-btn
                                    class="ml-2"
                                    flat
                                    tile
                                    color="secondary"
                                    variant="outlined"
                                    to="/admin/register_system/details"
                                    text="Details" />
                            </div>
                        </div>
                    </div>
                </template>
            </its-grid-box>
        </v-col>

        <!-- ÄNDERN/ANLEGEN EINES Anmeldesystems -->
        <v-col cols="12" sm="6" md="4" xl="3" v-if="action == 'edit_register' || action == 'create_register'">
            <its-grid-box
                color="primary"
                :title="data.id ? selected_register.name : 'Neues Anmeldesystem anlegen'"
                class="h-100 w-100">
                <v-form ref="form" v-model="is_valid" @submit.prevent="save(data)" class="mb-4">
                    <v-text-field
                        autofocus
                        v-model="data.name"
                        label="Bezeichnung"
                        :rules="[required(), maxLength(255)]" />
                    <v-textarea
                        v-model="data.description"
                        label="Beschreibung am Bildschirm"
                        :rules="[maxLength(1024)]" />

                    <v-text-field
                        v-model="data.max_registrations"
                        label="Max. Anmeldungen gesamt (0=unendlich)"
                        :rules="[required(), min(0)]" />

                    <v-row dense>
                        <v-col cols="6">
                            <v-checkbox v-model="data.show_booked" hide-details label="Gebuchte anzeigen" />
                        </v-col>
                        <v-col cols="6">
                            <v-checkbox v-model="data.show_end_time" hide-details label="Endzeite anzeigen" />
                        </v-col>
                    </v-row>

                    <v-row dense>
                        <v-col cols="6">
                            <v-checkbox v-model="data.show_supervisor" hide-details label="Berater anzeigen" />
                        </v-col>
                        <v-col cols="6"></v-col>
                    </v-row>

                    <v-card tile flat color="primary">
                        <v-card-text>
                            Bei der Eingabe werden Nachname, Vorname und E-Mail verlangt. Weitere erforderliche Eingaben
                            können hier festgelegt werden.
                        </v-card-text>
                    </v-card>

                    <v-row dense>
                        <v-col cols="6">
                            <v-checkbox v-model="data.show_phone" hide-details label="Telefon" />
                        </v-col>
                        <v-col cols="6">
                            <v-checkbox
                                v-model="data.must_phone"
                                hide-details
                                label="Pflichtfeld"
                                v-if="data.show_phone" />
                        </v-col>
                    </v-row>

                    <v-row dense>
                        <v-col cols="6">
                            <v-checkbox v-model="data.show_student_last_name" hide-details label="Nachname Kind" />
                        </v-col>
                        <v-col cols="6">
                            <v-checkbox
                                v-model="data.must_student_last_name"
                                hide-details
                                label="Pflichtfeld"
                                v-if="data.show_student_last_name" />
                        </v-col>
                    </v-row>
                    <v-row dense>
                        <v-col cols="6">
                            <v-checkbox v-model="data.show_student_first_name" hide-details label="Vorname Kind" />
                        </v-col>
                        <v-col cols="6">
                            <v-checkbox
                                v-model="data.must_student_first_name"
                                hide-details
                                label="Pflichtfeld"
                                v-if="data.show_student_first_name" />
                        </v-col>
                    </v-row>

                    <v-row dense>
                        <v-col cols="6">
                            <v-checkbox v-model="data.show_birthdate" hide-details label="Geburtsdatum" />
                        </v-col>
                        <v-col cols="6">
                            <v-checkbox
                                v-model="data.must_birthdate"
                                hide-details
                                label="Pflichtfeld"
                                v-if="data.show_birthdate" />
                        </v-col>
                    </v-row>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" slim flat @click="abort">Abbruch</v-btn>
                        <v-btn color="success" slim flat type="submit">Speichern</v-btn>
                    </div>
                </v-form>
            </its-grid-box>
        </v-col>

        <!-- LÖSCHEN EINES Anmeldesystems -->
        <v-col cols="12" sm="6" md="4" xl="3" v-if="action == 'remove_register'">
            <its-grid-box color="primary" :title="selected_register?.name" class="h-100 w-100">
                <v-form ref="form" v-model="is_valid" @submit.prevent="destroy(selected_register)" class="mb-4">
                    <div class="text-h6">Soll dieses Anmeldesystem wirklich gelöscht werden?</div>
                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="success" slim flat @click="abort">Abbruch</v-btn>
                        <v-btn color="error" slim flat type="submit">Löschen</v-btn>
                    </div>
                </v-form>
            </its-grid-box>
        </v-col>
    </v-row>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

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
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, [
            'config',
            'selected_school',
            'selected_schoolyear',
            'selected_register',
            'selected_active_register',
            'action',
        ]),
        ...mapWritableState(useSchoolyearStore, []),
        ...mapWritableState(useRegisterStore, ['registers']),
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
            this.registerStore.index()
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
            this.selected_register = null
            await this.registerStore.loadActiveRegisters()
            if (answer) this.action = ''
        },

        async destroy(data) {
            var answer = false
            answer = await this.registerStore.destroy(data)
            await this.registerStore.loadActiveRegisters()
            this.selected_register = null
            if (answer) this.action = ''
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
            }
            this.action = 'create_register'
        },

        async setSelectedRegister(register) {
            await this.registerStore.setSelectedRegister(register.id)
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
