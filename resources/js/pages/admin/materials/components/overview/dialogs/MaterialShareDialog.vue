<template>
    <v-dialog :model-value="modelValue" max-width="760" persistent @update:modelValue="$emit('update:modelValue', $event)">
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2 py-4">
                <v-avatar color="primary" variant="tonal" size="32">
                    <v-icon icon="mdi-share-variant-outline" />
                </v-avatar>
                <span class="text-h6">Freigabe</span>
                <v-spacer />
                <v-btn icon="mdi-close" variant="text" @click="$emit('update:modelValue', false)" />
            </v-card-title>

            <v-card-text class="pt-2">
                <div class="share-dialog-shell">
                    <section class="share-dialog-panel">
                        <div class="text-caption text-medium-emphasis mb-2">Ausgewählt zum Freigeben</div>
                        <div v-if="target?.level" class="d-flex flex-wrap ga-2 mb-2">
                            <v-chip color="primary" variant="flat" size="small">
                                Ebene: {{ levelLabel(target.level) }}
                            </v-chip>
                        </div>
                        <div class="text-body-1 font-weight-medium">{{ target?.label || '-' }}</div>
                        <div v-if="showMaterialMeta(target)" class="d-flex flex-wrap ga-2 mt-2">
                            <v-chip v-if="target?.kindLabel" size="x-small" variant="outlined" :color="target?.kindColor || 'primary'">Art: {{ target.kindLabel }}</v-chip>
                            <v-chip v-if="target?.statusLabel" size="x-small" variant="flat" :color="target?.statusColor || 'primary'">Status: {{ target.statusLabel }}</v-chip>
                            <v-chip v-if="hasAttachmentCount(target)" size="x-small" variant="flat" color="primary" prepend-icon="mdi-paperclip">Anhänge: {{ Number(target.attachmentsCount || 0) }}</v-chip>
                        </div>
                        <div v-if="target?.parentLabel" class="text-caption text-medium-emphasis mt-1">Kontext: {{ target.parentLabel }}</div>
                    </section>

                    <section class="share-dialog-panel">
                        <div class="text-subtitle-2 mb-2">Freigeben an</div>
                        <v-expansion-panels v-model="shareTargetPanel" variant="accordion" class="share-target-panels">
                            <v-expansion-panel>
                                <v-expansion-panel-title>
                                    <div class="d-flex align-center ga-2 flex-wrap">
                                        <v-icon icon="mdi-earth" size="18" />
                                        <span class="font-weight-medium">1. Jeder</span>
                                        <v-chip size="x-small" variant="flat" color="success">{{ isEveryoneScopeAssigned(shareEveryoneScope) ? shareEveryoneScopeLabel : 'nicht aktiv' }}</v-chip>
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <div class="d-grid ga-3">
                                        <v-btn-toggle v-model="shareEveryoneScope" mandatory color="primary" variant="outlined" class="share-everyone-scope-toggle">
                                            <v-btn value="global" class="share-everyone-scope-btn">Jeder (auch schulfremd)</v-btn>
                                            <v-btn value="school" class="share-everyone-scope-btn">Nur Schulweit</v-btn>
                                        </v-btn-toggle>
                                        <div class="d-flex flex-wrap align-center ga-2">
                                            <template v-if="isEveryoneScopeAssigned(shareEveryoneScope)">
                                                <v-chip size="x-small" color="success" variant="flat">zugeordnet</v-chip>
                                                <v-chip size="x-small" :color="permissionChipColor(assignedEveryoneTarget(shareEveryoneScope)?.permission)" variant="tonal">
                                                    {{ assignedEveryoneTarget(shareEveryoneScope)?.permission_label || '-' }}
                                                </v-chip>
                                                <v-btn color="warning" variant="flat" prepend-icon="mdi-account-remove-outline" :loading="actionTargetIdBusy(assignedEveryoneTarget(shareEveryoneScope)?.id)" @click="removeAssignedTarget(assignedEveryoneTarget(shareEveryoneScope))">
                                                    Entfernen
                                                </v-btn>
                                            </template>
                                            <v-btn v-else color="primary" variant="flat" prepend-icon="mdi-arrow-right" :loading="actionTargetKeyBusy(`everyone:${shareEveryoneScope}`)" @click="stageEveryoneShare()">
                                                Auswählen
                                            </v-btn>
                                            <div class="text-caption text-medium-emphasis">{{ shareEveryoneScope === 'global' ? 'Für alle Personen freigeben (auch schulfremd).' : 'Nur schulweit freigeben.' }}</div>
                                        </div>
                                    </div>
                                </v-expansion-panel-text>
                            </v-expansion-panel>

                            <v-expansion-panel>
                                <v-expansion-panel-title>
                                    <div class="d-flex align-center ga-2 flex-wrap">
                                        <v-icon icon="mdi-account-search-outline" size="18" />
                                        <span class="font-weight-medium">2. Person suchen</span>
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <div class="d-flex flex-wrap ga-2 align-start">
                                        <v-text-field v-model="sharePersonSearch" clearable label="Person suchen" placeholder="Name oder E-Mail" variant="outlined" density="comfortable" hide-details="auto" class="flex-grow-1" @keyup.enter="searchPeople" @click:clear="clearPeopleSearch" />
                                        <v-btn color="primary" variant="flat" prepend-icon="mdi-magnify" :loading="peopleSearchLoading" :disabled="normalizedPeopleSearch.length < 2" @click="searchPeople">Suchen</v-btn>
                                    </div>
                                    <div v-if="peopleSearchError" class="mt-2"><v-alert type="warning" variant="tonal" density="compact" class="mb-0">{{ peopleSearchError }}</v-alert></div>
                                    <div v-else-if="normalizedPeopleSearch.length >= 2 && !peopleSearchLoading && !peopleSearchResults.length" class="text-caption text-medium-emphasis mt-2">Keine Personen gefunden.</div>
                                    <div v-if="peopleSearchResults.length" class="share-target-search-results mt-3">
                                        <div v-for="person in peopleSearchResults" :key="`share-person-result-${person.id}`" class="share-target-search-row">
                                            <div class="d-flex align-center justify-space-between ga-2 flex-wrap">
                                                <div>
                                                    <div class="font-weight-medium">{{ person.label }}</div>
                                                    <div class="text-caption text-medium-emphasis">{{ person.email || '-' }}</div>
                                                </div>
                                                <div class="d-flex flex-wrap ga-2 align-center">
                                                    <template v-if="assignedUserTarget(person.id)">
                                                        <v-chip size="x-small" color="success" variant="flat">zugeordnet</v-chip>
                                                        <v-chip size="x-small" :color="permissionChipColor(assignedUserTarget(person.id)?.permission)" variant="tonal">{{ assignedUserTarget(person.id)?.permission_label || '-' }}</v-chip>
                                                        <v-btn size="small" color="warning" variant="flat" prepend-icon="mdi-account-remove-outline" :loading="actionTargetIdBusy(assignedUserTarget(person.id)?.id)" @click="removeAssignedTarget(assignedUserTarget(person.id))">Entfernen</v-btn>
                                                    </template>
                                                    <v-btn v-else size="small" color="primary" variant="flat" prepend-icon="mdi-arrow-right" :loading="actionTargetKeyBusy(`user:${person.id}`)" @click="stageUserShare(person)">Auswählen</v-btn>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </v-expansion-panel-text>
                            </v-expansion-panel>
                            <v-expansion-panel>
                                <v-expansion-panel-title>
                                    <div class="d-flex align-center ga-2 flex-wrap">
                                        <v-icon icon="mdi-school-outline" size="18" />
                                        <span class="font-weight-medium">3. Person aus anderer Schule</span>
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <v-select
                                        v-model="selectedExternalSchoolId"
                                        :items="externalSchools"
                                        item-title="label"
                                        item-value="id"
                                        label="Schule wählen"
                                        variant="outlined"
                                        density="comfortable"
                                        clearable
                                        hide-details="auto"
                                        :loading="externalSchoolsLoading"
                                        @update:menu="onExternalSchoolsMenuOpen" />
                                    <v-text-field
                                        v-model="externalUserEmail"
                                        class="mt-2"
                                        label="E-Mail-Adresse (bekannt)"
                                        placeholder="user@example.com"
                                        variant="outlined"
                                        density="comfortable"
                                        clearable
                                        hide-details="auto"
                                        type="email" />
                                    <v-alert v-if="externalSchoolsError" type="warning" variant="tonal" density="compact" class="mt-2 mb-0">{{ externalSchoolsError }}</v-alert>
                                    <v-alert v-if="externalUserLookupError" type="warning" variant="tonal" density="compact" class="mt-2 mb-0">{{ externalUserLookupError }}</v-alert>
                                    <div class="d-flex flex-wrap ga-2 mt-2 align-center">
                                        <v-chip v-if="selectedExternalSchoolLabel" size="small" variant="tonal" color="primary">{{ selectedExternalSchoolLabel }}</v-chip>
                                        <v-chip v-if="normalizedExternalUserEmail" size="small" variant="outlined" color="secondary">{{ normalizedExternalUserEmail }}</v-chip>
                                        <template v-if="assignedExternalUserTarget">
                                            <v-chip size="x-small" color="success" variant="flat">zugeordnet</v-chip>
                                            <v-chip size="x-small" :color="permissionChipColor(assignedExternalUserTarget?.permission)" variant="tonal">{{ assignedExternalUserTarget?.permission_label || '-' }}</v-chip>
                                            <v-btn size="small" color="warning" variant="flat" prepend-icon="mdi-account-remove-outline" :loading="actionTargetIdBusy(assignedExternalUserTarget?.id)" @click="removeAssignedTarget(assignedExternalUserTarget)">Entfernen</v-btn>
                                        </template>
                                        <v-btn v-else size="small" color="primary" variant="flat" prepend-icon="mdi-arrow-right" :disabled="!canStageExternalUserShare" :loading="actionTargetKeyBusy(`external-user:${selectedExternalSchoolId || 0}:${normalizedExternalUserEmail}`)" @click="stageExternalUserShare()">Auswählen</v-btn>
                                    </div>
                                </v-expansion-panel-text>
                            </v-expansion-panel>

                            <v-expansion-panel>
                                <v-expansion-panel-title>
                                    <div class="d-flex align-center ga-2 flex-wrap">
                                        <v-icon icon="mdi-account-group-outline" size="18" />
                                        <span class="font-weight-medium">4. Gruppe</span>
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <div class="text-caption text-medium-emphasis mb-2">Mastergruppen</div>
                                    <div class="d-flex flex-wrap ga-2">
                                        <v-btn
                                            v-for="groupMaster in groupMasterOptions"
                                            :key="`group-master-${groupMaster.value}`"
                                            size="small"
                                            variant="outlined"
                                            color="primary">
                                            {{ groupMaster.label }}
                                        </v-btn>
                                    </div>
                                </v-expansion-panel-text>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </section>

                    <section v-if="pendingShareTarget" class="share-dialog-panel share-dialog-panel--soft">
                        <div class="text-subtitle-2 mb-2">Zugriff festlegen</div>
                        <div class="d-flex flex-wrap align-center ga-2 mb-3">
                            <v-chip size="small" color="primary" variant="flat">{{ pendingShareTarget.label }}</v-chip>
                            <v-chip v-if="pendingShareTarget.metaLabel" size="x-small" variant="outlined" color="secondary">{{ pendingShareTarget.metaLabel }}</v-chip>
                            <v-chip size="x-small" variant="tonal" color="secondary">{{ pendingShareTarget.typeLabel }}</v-chip>
                        </div>
                        <div class="d-flex align-center flex-wrap ga-2 mb-2">
                            <div class="text-subtitle-2">Freigabeart</div>
                            <v-chip size="x-small" color="primary" variant="flat">{{ shareModeLabel(shareMode) }}</v-chip>
                        </div>
                        <v-btn-toggle
                            v-model="shareMode"
                            mandatory
                            color="primary"
                            variant="outlined"
                            class="share-dialog-mode-toggle"
                            :style="{ gridTemplateColumns: `repeat(${Math.max(availableShareModes.length, 1)}, minmax(0, 1fr))` }">
                            <v-btn
                                v-for="mode in availableShareModes"
                                :key="`share-mode-${mode.value}`"
                                :value="mode.value"
                                class="share-dialog-mode-btn">
                                {{ mode.label }}
                            </v-btn>
                        </v-btn-toggle>
                        <div class="d-flex justify-end flex-wrap ga-2 mt-3">
                            <v-btn variant="text" @click="clearPendingShareTarget">Abbrechen</v-btn>
                            <v-btn color="primary" variant="flat" prepend-icon="mdi-share-variant-outline" :loading="confirmingPendingShareTarget" @click="confirmPendingShareTarget">
                                Freigabe speichern
                            </v-btn>
                        </div>
                        <v-alert v-if="lastActionError" type="warning" variant="tonal" density="compact" class="mt-3 mb-0">{{ lastActionError }}</v-alert>
                    </section>

                    <section class="share-dialog-panel">
                        <div class="text-subtitle-2 mb-2">Bereits freigegeben an</div>
                        <div v-if="loading" class="text-body-2 text-medium-emphasis">Lade vorhandene Freigaben ...</div>
                        <v-alert v-else-if="error" type="warning" variant="tonal" density="compact" class="mb-0">{{ error }}</v-alert>
                        <div v-else-if="!assignments.length" class="text-body-2 text-medium-emphasis">Noch keine Freigabe für dieses Objekt vorhanden.</div>
                        <div v-else class="d-grid ga-2">
                            <div v-for="assignment in assignments" :key="`share-assignment-${assignment.id}`" class="share-dialog-assignment">
                                <div class="d-flex align-center flex-wrap ga-2 mb-2">
                                    <v-chip :color="assignment.is_active ? 'success' : 'secondary'" variant="flat" size="x-small">{{ assignment.is_active ? 'aktiv' : 'inaktiv' }}</v-chip>
                                    <span class="text-caption text-medium-emphasis">von {{ assignment.created_by_label || 'Unbekannt' }}</span>
                                </div>
                                <div class="d-grid ga-2">
                                    <div v-for="shareTarget in assignment.targets || []" :key="`share-target-row-${assignment.id}-${shareTarget.id}`" class="share-assignment-target-row">
                                        <div class="d-flex flex-wrap align-center ga-2">
                                            <v-chip :color="targetChipColor(shareTarget)" variant="flat" size="x-small">{{ shareTarget.label }}</v-chip>
                                            <v-chip v-if="shareTarget.permission_label" :color="permissionChipColor(shareTarget.permission)" variant="tonal" size="x-small">{{ shareTarget.permission_label }}</v-chip>
                                            <v-chip v-if="shareTarget.meta?.group_type_label" variant="outlined" size="x-small" color="secondary">{{ shareTarget.meta.group_type_label }}</v-chip>
                                            <v-chip v-if="shareTarget.meta?.school_label" variant="outlined" size="x-small" color="secondary">{{ shareTarget.meta.school_label }}</v-chip>
                                            <v-chip v-if="shareTarget.meta?.email" variant="outlined" size="x-small" color="secondary">{{ shareTarget.meta.email }}</v-chip>
                                        </div>
                                        <v-btn icon="mdi-delete-outline" size="small" color="warning" variant="text" :loading="actionTargetIdBusy(shareTarget.id)" @click="removeAssignedTarget(shareTarget)" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </v-card-text>

            <v-card-actions class="px-6 pb-5 pt-1 d-flex justify-end ga-2">
                <v-btn variant="text" @click="$emit('update:modelValue', false)">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import axios from 'axios'

export default {
    name: 'MaterialShareDialog',
    props: {
        modelValue: { type: Boolean, required: true },
        target: { type: Object, required: true },
        assignments: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
        error: { type: String, default: '' },
    },
    emits: ['update:modelValue', 'reload-assignments', 'shares-changed'],
    data() {
        return {
            shareMode: 'read_only',
            shareTargetPanel: 0,
            shareEveryoneScope: 'school',
            pendingShareTarget: null,
            lastActionError: '',
            sharePersonSearch: '',
            peopleSearchLoading: false,
            peopleSearchError: '',
            peopleSearchResults: [],
            externalSchoolsLoading: false,
            externalSchoolsLoaded: false,
            externalSchoolsError: '',
            externalUserLookupError: '',
            externalSchools: [],
            selectedExternalSchoolId: null,
            externalUserEmail: '',
            materialsGroupsLoading: false,
            materialsGroupsLoaded: false,
            materialsGroupsError: '',
            materialsGroups: [],
            ownGroupsLoading: false,
            ownGroupsLoaded: false,
            ownGroupsError: '',
            ownGroups: [],
            selectedMaterialsGroupId: null,
            selectedOwnGroupId: null,
            targetActionBusyKeys: [],
            targetActionBusyIds: [],
        }
    },
    computed: {
        availableShareModes() {
            return this.allowedShareModesForScope(this.target?.level)
        },
        normalizedPeopleSearch() {
            return String(this.sharePersonSearch || '').trim()
        },
        normalizedExternalUserEmail() {
            return String(this.externalUserEmail || '').trim().toLowerCase()
        },
        shareEveryoneScopeLabel() {
            return this.shareEveryoneScope === 'global' ? 'Jeder (auch schulfremd)' : 'Nur Schulweit'
        },
        selectedExternalSchoolLabel() {
            const id = Number(this.selectedExternalSchoolId)
            return this.externalSchools.find((school) => Number(school.id) === id)?.label || ''
        },
        selectedMaterialsGroupLabel() {
            const id = Number(this.selectedMaterialsGroupId)
            return this.materialsGroups.find((group) => Number(group.id) === id)?.label || ''
        },
        selectedOwnGroupLabel() {
            const id = Number(this.selectedOwnGroupId)
            return this.ownGroups.find((group) => Number(group.id) === id)?.label || ''
        },
        groupMasterOptions() {
            return [
                { value: 'school', label: 'Schulgruppen' },
                { value: 'materials', label: 'Materialiengruppen' },
                { value: 'own', label: 'Eigene Gruppen' },
            ]
        },
        confirmingPendingShareTarget() {
            const busyKey = String(this.pendingShareTarget?.busyKey || '').trim()
            return busyKey !== '' ? this.actionTargetKeyBusy(busyKey) : false
        },
        canStageExternalUserShare() {
            return Number(this.selectedExternalSchoolId || 0) > 0 && this.normalizedExternalUserEmail !== ''
        },
        assignedExternalUserTarget() {
            const schoolId = Number(this.selectedExternalSchoolId || 0)
            const email = this.normalizedExternalUserEmail
            if (schoolId <= 0 || email === '') return null
            return this.allAssignedTargets.find((target) => {
                if (String(target?.target_type || '') !== 'user') return false
                const targetSchoolId = Number(target?.meta?.school_id || 0)
                const targetEmail = String(target?.meta?.email || '').trim().toLowerCase()
                return targetSchoolId === schoolId && targetEmail === email
            }) || null
        },
        allAssignedTargets() {
            const rows = Array.isArray(this.assignments) ? this.assignments : []
            return rows.flatMap((rule) => {
                const targets = Array.isArray(rule?.targets) ? rule.targets : []
                return targets.map((target) => ({ ...target, _rule_id: rule.id }))
            })
        },
    },
    watch: {
        modelValue(isOpen) {
            if (!isOpen) return
            this.initializeDialogState()
        },
        target() {
            this.ensureShareModeForScope(this.target?.level)
        },
        selectedExternalSchoolId() {
            this.externalUserLookupError = ''
        },
        externalUserEmail() {
            this.externalUserLookupError = ''
        },
    },
    methods: {
        initializeDialogState() {
            this.shareTargetPanel = 0
            this.pendingShareTarget = null
            this.shareMode = 'read_only'
            this.ensureShareModeForScope(this.target?.level)
            this.lastActionError = ''
            this.peopleSearchError = ''
            this.peopleSearchResults = []
            this.externalSchoolsError = ''
            this.externalUserLookupError = ''
            this.selectedExternalSchoolId = null
            this.externalUserEmail = ''
            this.loadExternalSchools()
            this.loadGroups('materials')
            this.loadGroups('own')
        },
        levelLabel(level) {
            return ({ subject: 'Fach', topic: 'Thema', unit: 'Einheit', material: 'Material', all: 'Alles' })[String(level || '').trim()] || String(level || '-')
        },
        normalizeShareMode(mode) {
            const normalized = String(mode || '').trim()
            if (normalized === 'full_access') return 'full_access'
            if (normalized === 'read_write') return 'read_write'
            return 'read_only'
        },
        scopeAllowsFullAccess(scopeType) {
            const normalized = String(scopeType || '').trim()
            return normalized === 'all' || normalized === 'subject'
        },
        allowedShareModesForScope(scopeType) {
            const base = [
                { value: 'read_write', label: 'Lesen/Schreiben' },
                { value: 'read_only', label: 'Nur Lesen' },
            ]
            if (this.scopeAllowsFullAccess(scopeType)) {
                return [{ value: 'full_access', label: 'Vollzugriff' }, ...base]
            }
            return base
        },
        ensureShareModeForScope(scopeType) {
            const allowedModes = this.allowedShareModesForScope(scopeType).map((mode) => mode.value)
            const normalizedMode = this.normalizeShareMode(this.shareMode)
            this.shareMode = allowedModes.includes(normalizedMode) ? normalizedMode : 'read_write'
        },
        shareModeLabel(mode) {
            return ({ full_access: 'Vollzugriff', read_write: 'Lesen/Schreiben', read_only: 'Nur Lesen' })[String(mode || '').trim()] || 'Nur Lesen'
        },
        permissionChipColor(permission) {
            return ({ full_access: 'error', read_write: 'warning', read_only: 'primary' })[String(permission || '').trim()] || 'primary'
        },
        targetChipColor(target) {
            if (target?.target_type === 'everyone') return 'success'
            if (target?.target_type === 'group') return 'primary'
            return 'secondary'
        },
        hasAttachmentCount(target) {
            const value = Number(target?.attachmentsCount)
            return Number.isFinite(value) && value >= 0
        },
        showMaterialMeta(target) {
            if (String(target?.level || '').trim() !== 'material') return false
            return Boolean(String(target?.kindLabel || '').trim() || String(target?.statusLabel || '').trim() || this.hasAttachmentCount(target))
        },
        scopePayload() {
            const scopeType = String(this.target?.level || '').trim()
            const scopeId = Number(this.target?.id || 0)
            if (!scopeType) return null
            if (scopeType !== 'all' && scopeId <= 0) return null
            return { scope_type: scopeType, scope_id: scopeType === 'all' ? null : scopeId }
        },
        emitReloadAssignments() {
            this.$emit('reload-assignments')
        },
        emitSharesChanged() {
            this.$emit('shares-changed')
        },
        actionTargetKeyBusy(key) {
            return this.targetActionBusyKeys.includes(String(key))
        },
        actionTargetIdBusy(id) {
            return this.targetActionBusyIds.includes(Number(id))
        },
        pushBusyKey(key) {
            const normalized = String(key)
            if (!this.targetActionBusyKeys.includes(normalized)) this.targetActionBusyKeys = [...this.targetActionBusyKeys, normalized]
        },
        popBusyKey(key) {
            const normalized = String(key)
            this.targetActionBusyKeys = this.targetActionBusyKeys.filter((entry) => entry !== normalized)
        },
        pushBusyId(id) {
            const normalized = Number(id)
            if (!Number.isFinite(normalized)) return
            if (!this.targetActionBusyIds.includes(normalized)) this.targetActionBusyIds = [...this.targetActionBusyIds, normalized]
        },
        popBusyId(id) {
            const normalized = Number(id)
            this.targetActionBusyIds = this.targetActionBusyIds.filter((entry) => entry !== normalized)
        },
        assignedEveryoneTarget(audienceScope) {
            const scope = String(audienceScope || '').trim()
            return this.allAssignedTargets.find((target) => String(target?.target_type || '') === 'everyone' && String(target?.audience_scope || '') === scope) || null
        },
        isEveryoneScopeAssigned(audienceScope) {
            return Boolean(this.assignedEveryoneTarget(audienceScope))
        },
        assignedUserTarget(userId) {
            const id = Number(userId)
            return this.allAssignedTargets.find((target) => String(target?.target_type || '') === 'user' && Number(target?.user_id || 0) === id) || null
        },
        assignedGroupTarget(groupId) {
            const id = Number(groupId)
            return this.allAssignedTargets.find((target) => String(target?.target_type || '') === 'group' && Number(target?.user_group_id || 0) === id) || null
        },
        async requestStoreTarget(payload, busyKey = '') {
            const scope = this.scopePayload()
            if (!scope) return false
            this.ensureShareModeForScope(scope.scope_type)
            if (busyKey) this.pushBusyKey(busyKey)
            this.lastActionError = ''
            try {
                await axios.post('/api/admin/materials/shares/targets', { ...scope, permission: this.shareMode, ...payload })
                this.emitReloadAssignments()
                this.emitSharesChanged()
                return true
            } catch (error) {
                console.error(error)
                const fieldErrors = error?.response?.data?.errors || {}
                let firstFieldError = ''
                for (const value of Object.values(fieldErrors)) {
                    if (Array.isArray(value) && value.length > 0) {
                        firstFieldError = String(value[0] || '').trim()
                        if (firstFieldError !== '') break
                    }
                }
                this.lastActionError = firstFieldError || error?.response?.data?.message || 'Freigabe konnte nicht gespeichert werden.'
                return false
            } finally {
                if (busyKey) this.popBusyKey(busyKey)
            }
        },
        async removeAssignedTarget(target) {
            const id = Number(target?.id || 0)
            if (id <= 0) return
            this.pushBusyId(id)
            try {
                await axios.delete(`/api/admin/materials/shares/targets/${id}`)
                this.emitReloadAssignments()
                this.emitSharesChanged()
            } catch (error) {
                console.error(error)
            } finally {
                this.popBusyId(id)
            }
        },
        clearPendingShareTarget() {
            this.pendingShareTarget = null
            this.lastActionError = ''
        },
        stageEveryoneShare() {
            this.pendingShareTarget = {
                typeLabel: 'Jeder',
                label: this.shareEveryoneScopeLabel,
                metaLabel: this.shareEveryoneScope === 'global' ? 'auch schulfremd' : 'nur schulweit',
                payload: { target_type: 'everyone', audience_scope: this.shareEveryoneScope },
                busyKey: `everyone:${this.shareEveryoneScope}`,
            }
        },
        stageUserShare(user) {
            const userId = Number(user?.id || 0)
            if (userId <= 0) return
            this.pendingShareTarget = {
                typeLabel: 'Person',
                label: String(user?.label || 'Person').trim() || 'Person',
                metaLabel: String(user?.email || '').trim(),
                payload: { target_type: 'user', user_id: userId },
                busyKey: `user:${userId}`,
            }
            this.lastActionError = ''
        },
        async stageExternalUserShare() {
            const schoolId = Number(this.selectedExternalSchoolId || 0)
            const email = this.normalizedExternalUserEmail
            if (schoolId <= 0 || email === '') return
            const busyKey = `external-user:${schoolId}:${email}`
            if (this.actionTargetKeyBusy(busyKey)) return
            this.pushBusyKey(busyKey)
            this.lastActionError = ''
            this.externalUserLookupError = ''
            try {
                const response = await axios.get('/api/admin/materials/shares/lookup-external-user', {
                    params: {
                        target_school_id: schoolId,
                        user_email: email,
                    },
                })
                const exists = !!response?.data?.data?.exists
                if (!exists) {
                    this.pendingShareTarget = null
                    this.externalUserLookupError = 'Benutzer wurde nicht gefunden.'
                    return
                }
                const externalUserLabel = String(response?.data?.data?.label || '').trim() || email
                const schoolLabel = String(response?.data?.data?.school_label || '').trim() || this.selectedExternalSchoolLabel
                this.pendingShareTarget = {
                    typeLabel: 'Person (andere Schule)',
                    label: externalUserLabel,
                    metaLabel: schoolLabel,
                    payload: { target_type: 'user', target_school_id: schoolId, user_email: email },
                    busyKey,
                }
            } catch (error) {
                const fieldErrors = error?.response?.data?.errors || {}
                let firstFieldError = ''
                for (const value of Object.values(fieldErrors)) {
                    if (Array.isArray(value) && value.length > 0) {
                        firstFieldError = String(value[0] || '').trim()
                        if (firstFieldError !== '') break
                    }
                }
                this.pendingShareTarget = null
                this.externalUserLookupError = firstFieldError || error?.response?.data?.message || 'Prüfung der Person fehlgeschlagen.'
            } finally {
                this.popBusyKey(busyKey)
            }
        },
        stageSelectedGroup(type) {
            const normalizedType = String(type || '').trim()
            const isMaterials = normalizedType === 'materials'
            const groupId = Number(isMaterials ? this.selectedMaterialsGroupId : this.selectedOwnGroupId)
            if (!Number.isFinite(groupId) || groupId <= 0) return
            const label = isMaterials ? this.selectedMaterialsGroupLabel : this.selectedOwnGroupLabel
            this.pendingShareTarget = {
                typeLabel: isMaterials ? 'Materialiengruppe' : 'Eigene Gruppe',
                label: String(label || 'Gruppe').trim() || 'Gruppe',
                metaLabel: '',
                payload: { target_type: 'group', user_group_id: groupId },
                busyKey: `group:${groupId}`,
            }
        },
        async confirmPendingShareTarget() {
            if (!this.pendingShareTarget?.payload) return
            const ok = await this.requestStoreTarget(this.pendingShareTarget.payload, this.pendingShareTarget.busyKey)
            if (ok) this.clearPendingShareTarget()
        },
        clearPeopleSearch() {
            this.sharePersonSearch = ''
            this.peopleSearchError = ''
            this.peopleSearchResults = []
        },
        async searchPeople() {
            const search = this.normalizedPeopleSearch
            if (search.length < 2) {
                this.peopleSearchResults = []
                this.peopleSearchError = ''
                return
            }
            this.peopleSearchLoading = true
            this.peopleSearchError = ''
            try {
                const response = await axios.get('/api/admin/materials/shares/lookup-users', { params: { search } })
                this.peopleSearchResults = Array.isArray(response.data?.data) ? response.data.data : []
            } catch (error) {
                this.peopleSearchResults = []
                this.peopleSearchError = error?.response?.data?.message || 'Personensuche fehlgeschlagen.'
            } finally {
                this.peopleSearchLoading = false
            }
        },
        async loadExternalSchools() {
            if (this.externalSchoolsLoading) return
            this.externalSchoolsLoading = true
            this.externalSchoolsError = ''
            try {
                const response = await axios.get('/api/admin/materials/shares/lookup-schools')
                this.externalSchools = Array.isArray(response.data?.data) ? response.data.data : []
                this.externalSchoolsLoaded = true
            } catch (error) {
                this.externalSchools = []
                this.externalSchoolsError = error?.response?.data?.message || 'Schulen konnten nicht geladen werden.'
            } finally {
                this.externalSchoolsLoading = false
            }
        },
        onExternalSchoolsMenuOpen(isOpen) {
            if (!isOpen) return
            if (!this.externalSchoolsLoaded) this.loadExternalSchools()
        },
        async loadGroups(type) {
            const normalizedType = String(type || '').trim()
            if (!['materials', 'own'].includes(normalizedType)) return
            const loadingKey = normalizedType === 'materials' ? 'materialsGroupsLoading' : 'ownGroupsLoading'
            const loadedKey = normalizedType === 'materials' ? 'materialsGroupsLoaded' : 'ownGroupsLoaded'
            const errorKey = normalizedType === 'materials' ? 'materialsGroupsError' : 'ownGroupsError'
            const listKey = normalizedType === 'materials' ? 'materialsGroups' : 'ownGroups'
            this[loadingKey] = true
            this[errorKey] = ''
            try {
                const response = await axios.get('/api/admin/materials/shares/lookup-groups', { params: { type: normalizedType } })
                this[listKey] = Array.isArray(response.data?.data) ? response.data.data : []
                this[loadedKey] = true
            } catch (error) {
                this[listKey] = []
                this[errorKey] = error?.response?.data?.message || 'Gruppen konnten nicht geladen werden.'
            } finally {
                this[loadingKey] = false
            }
        },
        onGroupsMenuOpen(type, isOpen) {
            if (!isOpen) return
            const loaded = type === 'materials' ? this.materialsGroupsLoaded : this.ownGroupsLoaded
            if (!loaded) this.loadGroups(type)
        },
    },
}
</script>

<style scoped>
.share-dialog-shell { display: grid; gap: 12px; }
.share-dialog-panel { border: 1px solid rgba(35, 61, 76, 0.12); background: rgba(255, 255, 255, 0.74); border-radius: 12px; padding: 12px; }
.share-dialog-panel--soft { background: linear-gradient(180deg, rgba(25, 118, 210, 0.05) 0%, rgba(25, 118, 210, 0.02) 100%); border-color: rgba(25, 118, 210, 0.16); }
.share-dialog-mode-toggle { width: 100%; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
.share-dialog-mode-toggle :deep(.v-btn-toggle) { width: 100%; }
.share-dialog-mode-toggle :deep(.v-btn), .share-everyone-scope-toggle :deep(.v-btn) { min-width: 0; }
.share-dialog-mode-btn, .share-everyone-scope-btn { text-transform: none; }
.share-dialog-assignment { border: 1px solid rgba(35, 61, 76, 0.1); background: rgba(255, 255, 255, 0.7); border-radius: 10px; padding: 8px 10px; }
.share-assignment-target-row { border: 1px solid rgba(35, 61, 76, 0.08); border-radius: 8px; padding: 8px; display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
.share-target-panels { background: transparent; }
.share-target-panels :deep(.v-expansion-panel) { background: rgba(255, 255, 255, 0.74); border: 1px solid rgba(35, 61, 76, 0.1); border-radius: 12px !important; margin-top: 8px; overflow: hidden; }
.share-target-panels :deep(.v-expansion-panel:first-child) { margin-top: 0; }
.share-target-panels :deep(.v-expansion-panel-title) { min-height: 56px; padding: 12px 14px; }
.share-target-panels :deep(.v-expansion-panel-text__wrapper) { padding: 0 14px 14px 14px; }
.share-target-search-results { display: grid; gap: 8px; }
.share-target-search-row { border: 1px solid rgba(35, 61, 76, 0.12); background: rgba(255, 255, 255, 0.7); border-radius: 10px; padding: 10px; display: grid; gap: 4px; }
.share-everyone-scope-toggle { width: 100%; }
@media (max-width: 640px) {
    .share-dialog-mode-toggle { grid-template-columns: 1fr; }
    .share-assignment-target-row { flex-direction: column; align-items: stretch; }
}
</style>
