<template>
    <v-container fluid class="materials-page ma-0 w-100 pa-2">
        <template v-if="isWorkspaceCheckLoading">
            <v-row class="w-100" dense>
                <v-col cols="12" lg="10" xl="9" class="mx-auto">
                    <v-card class="materials-shell pa-6" rounded="xl" elevation="0">
                        <v-skeleton-loader type="article" />
                    </v-card>
                </v-col>
            </v-row>
        </template>

        <template v-else-if="!hasWorkspace">
            <v-row class="w-100" dense>
                <v-col cols="12" lg="8" xl="7" class="mx-auto">
                    <v-card class="materials-shell pa-6" rounded="xl" elevation="0">
                        <div class="text-h6 font-weight-bold mb-2">Kein Workspace vorhanden</div>
                        <div class="text-body-1 mb-5">
                            Für die Materialverwaltung ist zuerst ein Workspace nötig.
                        </div>
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-briefcase-plus-outline"
                            :loading="isSavingWorkspace"
                            :disabled="isSavingWorkspace"
                            @click="openCreateWorkspaceDialog">
                            Workspace erstellen
                        </v-btn>
                    </v-card>
                </v-col>
            </v-row>
        </template>

        <template v-else>
            <v-card tile flat color="transparent" class="workspace-bar d-flex align-center justify-space-between flex-wrap ga-2 w-100 mb-3">
                <div class="workspace-bar__label">
                    Workspace: <span class="workspace-bar__name">{{ activeWorkspaceName }}</span>
                </div>
            </v-card>

            <MaterialsMenu v-model="main_action" :disabled="isMenuLocked || adminStore?.is_struktur_modus" />
            <v-row class="w-100" dense>
                <v-col cols="12" lg="10" xl="9">
                    <MaterialsOverviewView v-if="main_action === 'overview'" :disable-sharing-features="true" />
                    <MaterialsFreigabeView v-if="main_action === 'shared'" />
                    <MaterialsPermissionsView v-if="main_action === 'permissions'" />
                    <MaterialsNewView v-if="main_action === 'new_material'" @menu-lock-change="setMenuLocked" />
                </v-col>
            </v-row>
        </template>

        <v-dialog v-model="workspaceDialogOpen" persistent max-width="520">
            <v-card>
                <v-card-title>
                    {{ workspaceDialogMode === 'rename' ? 'Workspace umbenennen' : 'Workspace erstellen' }}
                </v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="workspaceDialogName"
                        label="Workspace-Name"
                        :disabled="isSavingWorkspace"
                        maxlength="255"
                        autofocus
                        hide-details="auto" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="isSavingWorkspace" @click="closeWorkspaceDialog">Abbrechen</v-btn>
                    <v-btn color="primary" :loading="isSavingWorkspace" @click="submitWorkspaceDialog">
                        {{ workspaceDialogMode === 'rename' ? 'Speichern' : 'Anlegen' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script>
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'
import MaterialsMenu from './components/navigation/MaterialsMenu.vue'
import MaterialsFreigabeView from './components/views/MaterialsFreigabeView.vue'
import MaterialsOverviewView from './components/views/MaterialsOverviewView.vue'
import MaterialsNewView from './components/views/MaterialsNewView.vue'
import MaterialsPermissionsView from './components/views/MaterialsPermissionsView.vue'

export default {
    name: 'Materials',
    components: {
        MaterialsMenu,
        MaterialsFreigabeView,
        MaterialsOverviewView,
        MaterialsNewView,
        MaterialsPermissionsView,
    },
    data() {
        return {
            adminStore: null,
            materialCardStore: null,
            main_action: 'overview',
            isMenuLocked: false,
            isWorkspaceCheckLoading: true,
            workspaceDialogOpen: false,
            workspaceDialogName: 'Workspace',
            workspaceDialogMode: 'create',
            isSavingWorkspace: false,
        }
    },
    async beforeMount() {
        this.adminStore = useAdminStore()
        this.materialCardStore = useMaterialCardStore()
        this.applyRouteSelection()
        this.setMenuLocked(false)
        await this.materialCardStore.loadConfig()
        this.isWorkspaceCheckLoading = false
    },
    unmounted() {
        this.setMenuLocked(false)
    },
    watch: {
        '$route.query': {
            deep: true,
            handler() {
                this.applyRouteSelection()
            },
        },
        main_action(value) {
            if (value !== 'new_material') {
                this.setMenuLocked(false)
            }

            this.syncRouteMainAction(value)
        },
    },
    computed: {
        hasWorkspace() {
            return Number(this.materialCardStore?.config?.workspace?.id || 0) > 0
        },
        activeWorkspaceId() {
            return Number(this.materialCardStore?.config?.workspace?.id || 0)
        },
        activeWorkspaceName() {
            const name = String(this.materialCardStore?.config?.workspace?.name || '').trim()
            return name || 'Workspace'
        },
        initialSettingsAction() {
            const value = String(this.$route?.query?.settings_action || '').trim()
            return value || null
        },
        initialSubjectAction() {
            const value = String(this.$route?.query?.subject_action || '').trim()
            return value || null
        },
    },
    methods: {
        async openCreateWorkspaceDialog() {
            if (this.isSavingWorkspace) {
                return
            }

            this.isSavingWorkspace = true
            try {
                await this.materialCardStore.createWorkspace('Workspace')
            } finally {
                this.isSavingWorkspace = false
            }
        },
        openRenameWorkspaceDialog() {
            if (!this.hasWorkspace) {
                return
            }

            this.workspaceDialogMode = 'rename'
            this.workspaceDialogName = this.activeWorkspaceName
            this.workspaceDialogOpen = true
        },
        closeWorkspaceDialog() {
            if (this.isSavingWorkspace) {
                return
            }

            this.workspaceDialogOpen = false
        },
        async submitWorkspaceDialog() {
            if (this.isSavingWorkspace) {
                return
            }

            const normalizedName = String(this.workspaceDialogName || '').trim().slice(0, 255)
            if (!normalizedName) {
                return
            }

            this.isSavingWorkspace = true
            try {
                const response = this.workspaceDialogMode === 'rename'
                    ? await this.materialCardStore.renameWorkspace(this.activeWorkspaceId, normalizedName)
                    : await this.materialCardStore.createWorkspace(normalizedName)

                if (response) {
                    this.workspaceDialogOpen = false
                }
            } finally {
                this.isSavingWorkspace = false
            }
        },
        applyRouteSelection() {
            if (this.$route?.path !== '/admin/materials') return
            const queryValue = String(this.$route?.query?.main_action || '').trim()
            const normalizedQueryValue = queryValue === 'teilen' ? 'shared' : queryValue
            if (normalizedQueryValue === 'settings') {
                this.redirectLegacySettingsRoute()
                return
            }

            const allowed = ['overview', 'shared', 'permissions', 'new_material']
            if (allowed.includes(normalizedQueryValue)) {
                this.main_action = normalizedQueryValue
                if (queryValue !== normalizedQueryValue) {
                    this.syncRouteMainAction(normalizedQueryValue)
                }
            } else {
                this.main_action = 'overview'
            }
        },
        syncRouteMainAction(value) {
            if (this.$route?.path !== '/admin/materials') return

            const allowed = ['overview', 'shared', 'permissions', 'new_material']
            const normalized = allowed.includes(String(value || '').trim()) ? String(value || '').trim() : 'overview'
            const current = String(this.$route?.query?.main_action || '').trim()
            if (current === normalized) return

            const nextQuery = {
                ...(this.$route?.query || {}),
                main_action: normalized,
            }

            const navigation = this.$router?.replace?.({
                path: '/admin/materials',
                query: nextQuery,
            })

            if (navigation && typeof navigation.catch === 'function') {
                navigation.catch(() => {})
            }
        },
        redirectLegacySettingsRoute() {
            const nextQuery = {
                tab: 'materials',
                panel: 'material_settings',
            }

            const settingsAction = String(this.$route?.query?.settings_action || '').trim()
            if (settingsAction) {
                nextQuery.settings_action = settingsAction
            }

            const subjectAction = String(this.$route?.query?.subject_action || '').trim()
            if (subjectAction) {
                nextQuery.subject_action = subjectAction
            }

            const navigation = this.$router?.replace?.({
                path: '/admin/settings',
                query: nextQuery,
            })

            if (navigation && typeof navigation.catch === 'function') {
                navigation.catch(() => {})
            }
        },
        setMenuLocked(value) {
            const locked = !!value
            this.isMenuLocked = locked
            if (this.adminStore) {
                this.adminStore.is_navigation_locked = locked
            }
        },
    },
}
</script>

<style scoped>
.materials-page {
    --pumpkin: #fd802e;
    --charcoal: #233d4c;
    --cream: #f8efe7;
    --pumpkin-light: #ff8f42;
    min-height: 100%;
    position: relative;
    overflow: hidden;
    background: linear-gradient(150deg, #182a35 0%, var(--charcoal) 46%, #182a35 100%);
}

.materials-page::before,
.materials-page::after {
    content: '';
    position: absolute;
    border-radius: 999px;
    filter: blur(90px);
    opacity: 0.35;
    pointer-events: none;
}

.materials-page::before {
    width: 340px;
    height: 340px;
    top: -80px;
    right: -120px;
    background: var(--pumpkin);
}

.materials-page::after {
    width: 340px;
    height: 340px;
    bottom: -120px;
    left: -130px;
    background: #ff9f5e;
}

:deep(.workspace-bar) {
    position: relative;
    z-index: 1;
}

.workspace-bar__label {
    color: #f8efe7;
    font-weight: 700;
}

.workspace-bar__name {
    color: #ffd4b2;
}

:deep(.materials-shell) {
    position: relative;
    z-index: 1;
    border: 1px solid rgba(253, 128, 46, 0.35);
    background: rgba(248, 239, 231, 0.96);
    box-shadow: 0 14px 38px rgba(0, 0, 0, 0.2);
    color: var(--charcoal);
}

:deep(.subline) {
    color: #314d5d;
}

</style>
