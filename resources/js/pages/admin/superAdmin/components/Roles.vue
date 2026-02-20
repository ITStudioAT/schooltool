<template>
    <v-col cols="12" md="6" xl="4">
        <its-grid-box color="primary" title="Rollen" class="w-100" :disabled="action != ''">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <SearchField :store="roleStore" selected_field="selected_roles" />

                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="selectAll">
                                Alle auswählen [{{ roles.length - selected_roles.length }}]
                            </v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="unselectAll">Alle abwählen [{{ selected_roles.length }}]</v-btn>
                        </v-card>

                        <v-list dense variant="elevated" select-strategy="leaf" v-model:selected="selected_roles" color="success-lighten-2">
                            <v-list-item v-for="item in roles" :key="item.id" :value="item.id">
                                <template v-slot:title>
                                    <div class="text-body-1">
                                        {{ item.name }}
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>

                        <Pagination :meta="meta" :store="roleStore" selected_field="selected_roles" />
                    </v-card-text>
                </v-card>

                <v-card tile flat color="transparent" style="width: 150px" class="d-flex flex-column ga-2">
                    <div class="d-flex flex-column ga-2">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-plus" @click="createRole">Hinzufügen</v-btn>
                    </div>
                    <div class="d-flex flex-column ga-2" v-if="selected_roles.length == 1">
                        <v-btn block tile flat color="primary" class="text-caption" prepend-icon="mdi-pencil" @click="editRole(selected_roles[0])">Ändern</v-btn>
                    </div>
                    <div class="d-flex flex-column ga-2" v-if="selected_roles.length >= 1">
                        <v-btn block tile flat color="warning" class="text-caption" prepend-icon="mdi-delete" @click="deleteRole">Löschen</v-btn>
                    </div>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>

    <v-col cols="12" md="6" xl="4" v-if="action == 'create_role' || action == 'edit_role'">
        <its-grid-box color="primary" :title="data.id ? 'Rolle ändern' : 'Neue Rolle'" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="saveRole(data)" class="mb-4">
                <v-row dense>
                    <v-col cols="12">
                        <v-text-field autofocus v-model="data.name" label="Rollenname" :rules="[required(), maxLength(255)]" />
                    </v-col>
                </v-row>
                <v-row>
                    <v-col cols="12">
                        <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between">
                            <v-btn color="warning" flat tile @click="abort">Abbruch</v-btn>
                            <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                        </v-card>
                    </v-col>
                </v-row>
            </v-form>
        </its-grid-box>
    </v-col>

    <v-col cols="12" md="6" xl="4" v-if="action == 'delete_role'">
        <its-grid-box color="primary" title="Löschen" class="w-100">
            <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteRoles(selected_roles)">
                <v-card tile flat color="transparent" class="text-body-1">
                    <div v-if="selected_roles.length == 1">Es soll eine Rolle gelöscht werden. Sind Sie sicher, dass Sie die markierte Rolle löschen möchten?</div>
                    <div v-if="selected_roles.length > 1">
                        Es sollen {{ selected_roles.length }} Rollen gelöscht werden. Sind Sie sicher, dass Sie die markierten Rollen löschen möchten?
                    </div>
                    <div class="text-caption mt-2">Hinweis: Zugeordnete Rollen und die Rolle <code>super_admin</code> können nicht gelöscht werden.</div>
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
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'
import { useSuperAdminRoleStore } from '@/stores/admin/SuperAdminRoleStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.roleStore = useSuperAdminRoleStore()
        await this.roleStore.index()
    },

    data() {
        return {
            adminStore: null,
            roleStore: null,
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useSuperAdminRoleStore, ['roles', 'meta', 'selected_roles', 'search_string', 'data']),
    },

    methods: {
        deleteRole() {
            this.action = 'delete_role'
        },

        async doDeleteRoles(selected_roles) {
            if (!(await this.roleStore.deleteRoles(selected_roles))) return
            this.selected_roles = []
            await this.roleStore.index()
            this.action = ''
        },

        async saveRole(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (data.id) {
                if (!(await this.roleStore.update(data))) return
            } else {
                if (!(await this.roleStore.store(data))) return
            }
            this.data = {}
            this.action = ''
        },

        createRole() {
            this.data = {}
            this.action = 'create_role'
        },

        editRole(role_id) {
            const role = this.roles.find((item) => item.id === role_id)
            this.data = JSON.parse(JSON.stringify(role))
            this.action = 'edit_role'
        },

        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_roles = this.roles.map((item) => item.id)
        },

        unselectAll() {
            this.selected_roles = []
        },
    },
}
</script>
