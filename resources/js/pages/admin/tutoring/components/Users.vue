<template>
    <v-col cols="12" md="6" xl="4" v-if="users">
        <its-grid-box color="primary" title="Benutzer" class="w-100" :disabled="action != ''">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <!-- SEARCHFIELD -->
                        <SearchField :store="tutoringUserStore" selected_field="selected_users" />

                        <!-- Abwählen / Auswählen-->
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="selectAll">Alle auswählen [{{ users.length - selected_users.length }}]</v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="unselectAll">Alle abwählen [{{ selected_users.length }}]</v-btn>
                        </v-card>

                        <!-- RECORDS -->
                        <v-list dense variant="elevated" select-strategy="leaf" v-model:selected="selected_users" color="success-lighten-2">
                            <v-list-item dense v-for="item in users" :key="item.id" :value="item.id">
                                <template v-slot:title>
                                    <div class="w-100">
                                        <div class="text-body-1 d-flex flex-row align-center justify-space-between w-100">
                                            <div class="d-flex flex-row align-center ga-2">
                                                <v-icon color="error" size="small" icon="mdi-lock" v-if="!item.is_active" />
                                                <div>{{ item.last_name + ' ' + item.first_name }}</div>
                                            </div>
                                            <div class="text-rigtht text-body-2">{{ item.email }}</div>
                                        </div>
                                        <div class="text-caption">
                                            {{ item.roles }}
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>

                        <!-- PAGINATION-->
                        <Pagination :meta="meta" :store="tutoringUserStore" selected_field="selected_users" />
                    </v-card-text>
                </v-card>

                <!-- MENÜ -->
                <v-card tile flat color="transparent" style="width: 150px" class="d-flex flex-column ga-2">
                    <!-- AUSWAHl EGAL -->
                    <div class="d-flex flex-column ga-2">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-plus" @click="createUser">Hinzufügen</v-btn>
                    </div>
                    <!-- GENAU 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_users.length == 1">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-pencil" @click="editUser(selected_users[0])">Ändern</v-btn>
                        <v-btn
                            block
                            tile
                            flat
                            color="error"
                            class="text-caption"
                            prepend-icon="mdi-lock"
                            @click="toggleIsActive(selected_users[0])"
                            v-if="selectedUser(selected_users[0]).is_active">
                            Sperren
                        </v-btn>
                        <v-btn
                            block
                            tile
                            flat
                            color="success"
                            class="text-caption"
                            prepend-icon="mdi-lock-open"
                            @click="toggleIsActive(selected_users[0])"
                            v-if="!selectedUser(selected_users[0]).is_active">
                            Entsperren
                        </v-btn>
                    </div>
                    <!-- MINDEST 1 ELEMENT AUSGEWÄHLT -->
                    <div class="d-flex flex-column ga-2" v-if="selected_users.length >= 1">
                        <v-btn block tile flat color="warning" class="text-caption" prepend-icon="mdi-delete" @click="deleteUser">Löschen</v-btn>
                    </div>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>
    <!-- Neuer Benutzer / Benutzer ändern -->
    <v-col cols="12" md="6" xl="4" v-if="action == 'create_user' || action == 'edit_user'">
        <its-grid-box color="primary" :title="data.id ? 'Benutzer ändern' : 'Neuer Benutzer'" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="saveUser(data)" class="mb-4">
                <v-row dense>
                    <v-col cols="12">
                        <v-text-field autofocus v-model="data.last_name" label="Nachname" :rules="[required(), maxLength(255)]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="data.email" label="E-Mail" :rules="[mail(), maxLength(255)]" />
                    </v-col>
                    <v-radio-group v-model="data.sex" :rules="[required()]">
                        <v-radio label="Männlich" value="m" color="blue"></v-radio>
                        <v-radio label="Weiblich" value="f" color="pink"></v-radio>
                        <v-radio label="Divers" value="d" color="yellow"></v-radio>
                    </v-radio-group>
                </v-row>
                <v-row>
                    <v-col cols="12">
                        <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between" :disabled="is_uploading">
                            <v-btn color="warning" flat tile @click="abort">Abbruch</v-btn>
                            <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                        </v-card>
                    </v-col>
                </v-row>
            </v-form>
        </its-grid-box>
    </v-col>
    <!-- Löschen -->
    <v-col cols="12" md="6" xl="4" v-if="action == 'delete_user'">
        <its-grid-box color="primary" title="Löschen" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteUsers(selected_users)">
                <v-card tile flat color="transparent" class="text-body-1">
                    <div v-if="selected_users.length == 1">Es soll ein Benutzer gelöscht werden. Sind Sie sicher, dass Sie den markierten Benutzer löschen möchten?</div>
                    <div v-if="selected_users.length > 1">
                        Es sollen {{ selected_users.length }} Benutzer gelöscht werden. Sind Sie sicher, dass Sie die markierten Benutzer löschen möchten?
                    </div>
                </v-card>
                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between mt-4">
                    <v-btn color="success" flat tile @click="action = ''">Abbruch</v-btn>
                    <v-btn color="error" flat tile type="submit">Löschen</v-btn>
                </v-card>
            </v-form>
        </its-grid-box>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'
import FileUpload from '@/pages/components/FileUpload.vue'

// SPECIFIC

import { useTutoringUserStore } from '@/stores/admin/tutoring/UserStore'
import { useUserStore } from '@/stores/admin/UserStore20'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField, ItsMenuButton, ItsGridBox, FileUpload },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.tutoringUserStore = useTutoringUserStore()
        this.userStore = useUserStore()
        await this.tutoringUserStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            tutoringUserStore: null,
            userStore: null,

            is_valid: false,
            upload_file: null,
            is_uploading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTutoringUserStore, ['users', 'meta', 'selected_users', 'search_string', 'data', 'answer', 'role']),
    },

    watch: {},

    methods: {
        async toggleIsActive(user_id) {
            await this.userStore.toggleIsActive(user_id)
            await this.tutoringUserStore.index(this.meta.current_page)
        },
        selectedUser(user) {
            return this.users.find((s) => s.id === user)
        },

        async saveUser(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (data.id) {
                if (!(await this.tutoringUserStore.update(data))) return
                await this.tutoringUserStore.index(this.meta.current_page)
            } else {
                if (!(await this.tutoringUserStore.store(data))) return
                await this.tutoringUserStore.index(this.meta.current_page)
            }
            this.data = {}
            this.action = ''
        },

        createUser() {
            this.data = {}
            this.action = 'create_user'
        },

        deleteUser() {
            this.action = 'delete_user'
        },

        async doDeleteUsers(data) {
            if (!(await this.tutoringUserStore.deleteUsers(data))) return
            this.selected_users = []

            await this.tutoringUserStore.index()
            this.action = ''
        },

        editUser(user_id) {
            const user = this.users.find((s) => s.id === user_id)
            this.data = JSON.parse(JSON.stringify(user))
            this.action = 'edit_user'
        },
        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_users = this.users.map((item) => item.id)
        },
        unselectAll() {
            this.selected_users = []
        },
    },
}
</script>
