<template>
    <v-col cols="12" v-if="config.roles.includes('super_admin') && !isImpersonating">
        <div class="sa-overview-grid">
            <section class="sa-card sa-card-school">
                <div class="sa-card-head">
                    <div>
                        <div class="sa-card-eyebrow">Benutzer</div>
                        <h3 class="sa-card-title">Benutzer wechseln</h3>
                    </div>
                </div>

                <div class="sa-switch-shell">
                    <v-select
                        class="mb-2"
                        v-model="selected_impersonation_school_id"
                        :items="impersonatable_schools"
                        item-title="display_name"
                        item-value="id"
                        label="Schule auswählen"
                        variant="outlined"
                        no-data-text="Keine Schulen gefunden"
                        @update:model-value="onImpersonationSchoolChange" />

                    <v-form @submit.prevent="searchImpersonationUsers">
                        <div class="d-flex flex-row align-start">
                            <v-text-field
                                clearable
                                v-model="impersonation_user_search_string"
                                label="Benutzer suchen"
                                variant="outlined"
                                :disabled="!selected_impersonation_school_id"
                                @click:clear="searchImpersonationUsers" />
                            <v-btn
                                flat
                                tile
                                class="mt-1 ml-2"
                                color="primary"
                                variant="outlined"
                                icon="mdi-magnify"
                                type="submit"
                                :disabled="!selected_impersonation_school_id"
                                @click="searchImpersonationUsers" />
                        </div>
                    </v-form>

                    <v-list
                        dense
                        variant="elevated"
                        select-strategy="leaf"
                        class="mt-2"
                        v-model:selected="selected_impersonation_users"
                        @update:selected="onSelectedImpersonationUsersUpdate"
                        color="success-lighten-2"
                        v-if="impersonatable_users.length >= 1">
                        <v-list-item v-for="item in impersonatable_users" :key="`impersonation-user-${item.id}`" :value="item.id">
                            <template #title>
                                <div class="d-flex flex-column ga-1 py-1">
                                    <div class="text-body-1">{{ item.last_name }} {{ item.first_name }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ item.school_name || '-' }}</div>
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                    <div class="text-caption text-medium-emphasis mt-2 mb-3" v-else>Keine passenden Benutzer gefunden.</div>

                    <v-card tile flat color="transparent" v-if="impersonatable_users_meta && impersonatable_users_meta.total >= 1">
                        <div class="text-caption d-flex flex-row align-center justify-space-between">
                            <div>{{ impersonationMetaInfoText }}</div>
                            <div>{{ impersonationMetaPageText }}</div>
                        </div>
                        <div class="d-flex flex-row align-center justify-space-between">
                            <v-btn
                                flat tile class="mt-1 ml-2" color="primary" variant="outlined" icon="mdi-page-first"
                                @click="firstImpersonationUsersPage"
                                :disabled="impersonatable_users_meta.current_page <= 1" />
                            <v-btn
                                flat tile class="mt-1 ml-2" color="primary" variant="outlined" icon="mdi-page-previous-outline"
                                @click="prevImpersonationUsersPage"
                                :disabled="impersonatable_users_meta.current_page <= 1" />
                            <v-btn
                                flat tile class="mt-1 ml-2" color="primary" variant="outlined" icon="mdi-page-next-outline"
                                @click="nextImpersonationUsersPage"
                                :disabled="impersonatable_users_meta.current_page >= impersonatable_users_meta.last_page" />
                            <v-btn
                                flat tile class="mt-1 ml-2" color="primary" variant="outlined" icon="mdi-page-last"
                                @click="lastImpersonationUsersPage"
                                :disabled="impersonatable_users_meta.current_page >= impersonatable_users_meta.last_page" />
                        </div>
                    </v-card>

                    <div class="d-flex flex-row align-center justify-end mt-4">
                        <v-btn
                            color="primary"
                            variant="flat"
                            rounded="lg"
                            prepend-icon="mdi-account-switch"
                            :disabled="!selected_impersonation_school_id || !selectedImpersonationUserId"
                            @click="startImpersonation">
                            Wechseln
                        </v-btn>
                    </div>
                </div>
            </section>
        </div>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    async beforeMount() {
        this.adminStore = useAdminStore()

        if (this.config.roles.includes('super_admin') && !this.isImpersonating) {
            const schools = await this.adminStore.loadImpersonatableSchools()
            if (!schools || !Array.isArray(schools) || !schools.length) return

            const currentSchoolId = this.config?.selected_school?.id || null
            const schoolExists = schools.some((school) => Number(school.id) === Number(currentSchoolId))
            this.selected_impersonation_school_id = schoolExists ? currentSchoolId : schools[0].id
            await this.adminStore.loadImpersonatableUsers('', this.selected_impersonation_school_id, 1)
        }
    },

    data() {
        return {
            adminStore: null,
            selected_impersonation_school_id: null,
            impersonation_user_search_string: '',
            selected_impersonation_users: [],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'impersonatable_schools', 'impersonatable_users', 'impersonatable_users_meta']),
        isImpersonating() {
            return !!this.config?.impersonation?.is_impersonating
        },
        selectedImpersonationUserId() {
            return Array.isArray(this.selected_impersonation_users) && this.selected_impersonation_users.length >= 1
                ? this.selected_impersonation_users[0]
                : null
        },
        selectedImpersonationUser() {
            const selectedId = Number(this.selectedImpersonationUserId || 0)
            if (!selectedId) return null
            return (Array.isArray(this.impersonatable_users) ? this.impersonatable_users : [])
                .find((item) => Number(item?.id || 0) === selectedId) || null
        },
        impersonationMetaInfoText() {
            const meta = this.impersonatable_users_meta || {}
            return `${meta.from || 0} - ${meta.to || 0} von ${meta.total || 0}`
        },
        impersonationMetaPageText() {
            const meta = this.impersonatable_users_meta || {}
            return `Seite ${meta.current_page || 1} von ${meta.last_page || 1}`
        },
    },

    methods: {
        async onImpersonationSchoolChange() {
            this.selected_impersonation_users = []
            this.impersonation_user_search_string = ''
            if (!this.selected_impersonation_school_id) {
                this.impersonatable_users = []
                this.impersonatable_users_meta = []
                return
            }
            await this.adminStore.loadImpersonatableUsers('', this.selected_impersonation_school_id, 1)
        },
        async searchImpersonationUsers() {
            if (!this.selected_impersonation_school_id) return
            this.selected_impersonation_users = []
            await this.adminStore.loadImpersonatableUsers(this.impersonation_user_search_string || '', this.selected_impersonation_school_id, 1)
        },
        onSelectedImpersonationUsersUpdate(value) {
            if (!Array.isArray(value)) {
                this.selected_impersonation_users = []
                return
            }
            if (value.length <= 1) {
                this.selected_impersonation_users = value
                return
            }
            this.selected_impersonation_users = [value[value.length - 1]]
        },
        async firstImpersonationUsersPage() {
            if (!this.selected_impersonation_school_id) return
            this.selected_impersonation_users = []
            await this.adminStore.loadImpersonatableUsers(this.impersonation_user_search_string || '', this.selected_impersonation_school_id, 1)
        },
        async prevImpersonationUsersPage() {
            if (!this.selected_impersonation_school_id) return
            const currentPage = Number(this.impersonatable_users_meta?.current_page || 1)
            this.selected_impersonation_users = []
            await this.adminStore.loadImpersonatableUsers(this.impersonation_user_search_string || '', this.selected_impersonation_school_id, Math.max(1, currentPage - 1))
        },
        async nextImpersonationUsersPage() {
            if (!this.selected_impersonation_school_id) return
            const currentPage = Number(this.impersonatable_users_meta?.current_page || 1)
            const lastPage = Number(this.impersonatable_users_meta?.last_page || 1)
            this.selected_impersonation_users = []
            await this.adminStore.loadImpersonatableUsers(this.impersonation_user_search_string || '', this.selected_impersonation_school_id, Math.min(lastPage, currentPage + 1))
        },
        async lastImpersonationUsersPage() {
            if (!this.selected_impersonation_school_id) return
            const lastPage = Number(this.impersonatable_users_meta?.last_page || 1)
            this.selected_impersonation_users = []
            await this.adminStore.loadImpersonatableUsers(this.impersonation_user_search_string || '', this.selected_impersonation_school_id, Math.max(1, lastPage))
        },
        adminAccessRoles() {
            return ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'teacher']
        },
        targetCanAccessAdmin(user) {
            const userRoles = Array.isArray(user?.roles) ? user.roles : []
            return userRoles.length && userRoles.some((role) => this.adminAccessRoles().includes(String(role)))
        },
        impersonationTargetPath(user) {
            if (!user) return '/admin'
            return this.targetCanAccessAdmin(user) ? '/admin' : '/'
        },
        async startImpersonation() {
            if (!this.selectedImpersonationUserId) return
            const selectedUser = this.selectedImpersonationUser
            if (!(await this.adminStore.startImpersonation(this.selectedImpersonationUserId))) return
            const redirectTarget = this.impersonationTargetPath(selectedUser)
            this.action = ''
            window.location.href = redirectTarget
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-overview-card-foundation.css"></style>
<style scoped src="../../../../../css/admin-superadmin-overview-cards.css"></style>
