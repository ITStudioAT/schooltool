<template>
    <v-dialog :model-value="modelValue" max-width="860" persistent @update:modelValue="$emit('update:modelValue', $event)">
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2 py-4">
                <v-avatar color="primary" variant="tonal" size="32">
                    <v-icon icon="mdi-share-variant-outline" />
                </v-avatar>
                <span class="text-h6">Teilen vorbereiten</span>
                <v-spacer />
                <v-btn icon="mdi-close" variant="text" @click="$emit('update:modelValue', false)" />
            </v-card-title>

            <v-card-text class="pt-2">
                <div class="share-draft-shell">
                    <section class="share-draft-panel">
                        <div class="text-caption text-medium-emphasis mb-2">Ausgewählt zum Teilen</div>
                        <div class="text-body-1 font-weight-medium">{{ target?.label || target?.parentLabel || 'Workspace' }}</div>
                        <div v-if="target?.parentLabel" class="text-caption text-medium-emphasis mt-1">Kontext: {{ target.parentLabel }}</div>
                    </section>

                    <section class="share-draft-panel">
                        <div class="text-subtitle-2 mb-3">1. Empfänger auswählen</div>
                        <v-btn-toggle v-model="recipientMode" mandatory color="primary" variant="outlined" class="share-draft-mode-toggle mb-3">
                            <v-btn value="same_school_person" class="share-draft-mode-btn">Person (gleiche Schule)</v-btn>
                            <v-btn value="group" class="share-draft-mode-btn">Gruppe</v-btn>
                            <v-btn value="external_person" class="share-draft-mode-btn">Person (andere Schule)</v-btn>
                        </v-btn-toggle>

                        <template v-if="recipientMode === 'same_school_person'">
                            <div class="d-flex flex-wrap ga-2 align-start">
                                <v-text-field
                                    v-model="sameSchoolSearch"
                                    clearable
                                    label="Person suchen"
                                    placeholder="Name, Kürzel oder E-Mail"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    class="flex-grow-1"
                                    @keyup.enter="searchSameSchoolUsers"
                                    @click:clear="clearSameSchoolSearch" />
                                <v-btn
                                    color="primary"
                                    variant="flat"
                                    prepend-icon="mdi-magnify"
                                    :disabled="normalizedSameSchoolSearch.length < 2"
                                    :loading="sameSchoolSearchLoading"
                                    @click="searchSameSchoolUsers">
                                    Suchen
                                </v-btn>
                            </div>

                            <v-alert v-if="sameSchoolSearchError" type="warning" variant="tonal" density="compact" class="mt-2 mb-0">
                                {{ sameSchoolSearchError }}
                            </v-alert>

                            <div v-if="sameSchoolSearchResults.length" class="share-draft-result-list mt-3">
                                <div
                                    v-for="user in sameSchoolSearchResults"
                                    :key="`share-draft-user-${user.id}`"
                                    class="share-draft-result-row">
                                    <div class="d-flex flex-column">
                                        <div class="font-weight-medium">{{ user.label }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ user.short || '-' }} · {{ user.email || '-' }}</div>
                                    </div>
                                    <v-btn
                                        size="small"
                                        variant="flat"
                                        color="primary"
                                        :prepend-icon="selectedSameSchoolUserId === Number(user.id) ? 'mdi-check' : 'mdi-arrow-right'"
                                        @click="selectSameSchoolUser(user)">
                                        {{ selectedSameSchoolUserId === Number(user.id) ? 'Ausgewählt' : 'Auswählen' }}
                                    </v-btn>
                                </div>
                            </div>
                        </template>

                        <template v-else-if="recipientMode === 'group'">
                            <v-select
                                v-model="selectedGroupId"
                                :items="groupOptions"
                                item-title="label"
                                item-value="id"
                                label="Gruppe auswählen"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                hide-details="auto"
                                :loading="groupsLoading"
                                @update:menu="onGroupsMenuOpen" />

                            <v-alert v-if="groupsError" type="warning" variant="tonal" density="compact" class="mt-2 mb-0">
                                {{ groupsError }}
                            </v-alert>
                        </template>

                        <template v-else>
                            <v-select
                                v-model="selectedExternalSchoolId"
                                :items="externalSchools"
                                item-title="label"
                                item-value="id"
                                label="Schule auswählen"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                hide-details="auto"
                                :loading="externalSchoolsLoading"
                                @update:menu="onExternalSchoolsMenuOpen" />
                            <v-text-field
                                v-model="externalUserEmail"
                                class="mt-2"
                                label="E-Mail-Adresse"
                                placeholder="user@example.com"
                                type="email"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                hide-details="auto" />
                            <div class="d-flex flex-wrap ga-2 mt-2">
                                <v-btn
                                    color="primary"
                                    variant="flat"
                                    prepend-icon="mdi-account-search-outline"
                                    :disabled="!canCheckExternalUser"
                                    :loading="externalUserLookupLoading"
                                    @click="checkExternalUser">
                                    E-Mail prüfen
                                </v-btn>
                                <v-chip v-if="externalLookupResult" size="small" color="success" variant="flat">Gefunden</v-chip>
                            </div>
                            <v-alert v-if="externalSchoolsError" type="warning" variant="tonal" density="compact" class="mt-2 mb-0">
                                {{ externalSchoolsError }}
                            </v-alert>
                            <v-alert v-if="externalUserLookupError" type="warning" variant="tonal" density="compact" class="mt-2 mb-0">
                                {{ externalUserLookupError }}
                            </v-alert>
                        </template>

                        <div class="mt-3">
                            <v-chip v-if="selectedRecipient" size="small" color="primary" variant="flat">{{ selectedRecipient.label }}</v-chip>
                            <v-chip v-if="selectedRecipient?.metaLabel" size="small" color="secondary" variant="tonal" class="ml-2">{{ selectedRecipient.metaLabel }}</v-chip>
                        </div>
                    </section>

                    <section class="share-draft-panel">
                        <div class="text-subtitle-2 mb-2">2. Berechtigung auswählen</div>
                        <div class="text-caption text-medium-emphasis mb-2">
                            {{ hasRecipientSelection ? 'Berechtigung für den ausgewählten Empfänger festlegen.' : 'Zuerst einen Empfänger auswählen.' }}
                        </div>
                        <v-btn-toggle v-model="shareMode" mandatory color="primary" variant="outlined" class="share-draft-permission-toggle" :disabled="!hasRecipientSelection">
                            <v-btn value="read_only" class="share-draft-mode-btn">NUR LESEN</v-btn>
                            <v-btn value="read_write" class="share-draft-mode-btn">LESEN/SCHREIBEN</v-btn>
                            <v-btn value="full_access" class="share-draft-mode-btn">VOLLZUGRIFF</v-btn>
                        </v-btn-toggle>
                    </section>

                    <section class="share-draft-panel share-draft-panel--soft">
                        <div class="text-subtitle-2 mb-2">Vorschau (kein Speichern)</div>
                        <div class="text-body-2">
                            <div><strong>Empfänger:</strong> {{ selectedRecipient?.label || '-' }}</div>
                            <div><strong>Typ:</strong> {{ selectedRecipient?.typeLabel || '-' }}</div>
                            <div v-if="selectedRecipient?.type === 'external_person'"><strong>Schule:</strong> {{ selectedRecipient?.metaLabel || '-' }}</div>
                            <div><strong>Berechtigung:</strong> {{ shareModeLabel(shareMode) }}</div>
                        </div>
                        <v-alert type="info" variant="tonal" density="compact" class="mt-3 mb-0">
                            Diese Auswahl ist aktuell nur vorbereitet und wird noch nicht gespeichert.
                        </v-alert>
                    </section>
                </div>
            </v-card-text>

            <v-card-actions class="px-6 pb-5 pt-1 d-flex justify-end ga-2">
                <v-btn variant="text" @click="$emit('update:modelValue', false)">Schließen</v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-check"
                    :disabled="!hasRecipientSelection"
                    @click="applySelection">
                    Auswahl übernehmen (ohne Speichern)
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import axios from 'axios'

export default {
    name: 'MaterialShareDraftDialog',
    props: {
        modelValue: { type: Boolean, required: true },
        target: {
            type: Object,
            default: () => ({
                level: '',
                id: null,
                label: '',
                parentLabel: '',
            }),
        },
    },
    emits: ['update:modelValue', 'dummy-selected'],
    data() {
        return {
            recipientMode: 'same_school_person',
            shareMode: 'read_only',
            sameSchoolSearch: '',
            sameSchoolSearchLoading: false,
            sameSchoolSearchError: '',
            sameSchoolSearchResults: [],
            selectedSameSchoolUserId: null,
            groupsLoading: false,
            groupsLoaded: false,
            groupsError: '',
            groupOptions: [],
            selectedGroupId: null,
            externalSchoolsLoading: false,
            externalSchoolsLoaded: false,
            externalSchoolsError: '',
            externalSchools: [],
            selectedExternalSchoolId: null,
            externalUserEmail: '',
            externalUserLookupLoading: false,
            externalUserLookupError: '',
            externalLookupResult: null,
        }
    },
    computed: {
        normalizedSameSchoolSearch() {
            return String(this.sameSchoolSearch || '').trim()
        },
        normalizedExternalUserEmail() {
            return String(this.externalUserEmail || '').trim().toLowerCase()
        },
        selectedSameSchoolUser() {
            const userId = Number(this.selectedSameSchoolUserId || 0)
            if (userId <= 0) {
                return null
            }

            return this.sameSchoolSearchResults.find((user) => Number(user?.id || 0) === userId) || null
        },
        selectedGroup() {
            const groupId = Number(this.selectedGroupId || 0)
            if (groupId <= 0) {
                return null
            }

            return this.groupOptions.find((group) => Number(group?.id || 0) === groupId) || null
        },
        selectedExternalSchoolLabel() {
            const schoolId = Number(this.selectedExternalSchoolId || 0)
            if (schoolId <= 0) {
                return ''
            }

            return this.externalSchools.find((school) => Number(school?.id || 0) === schoolId)?.label || ''
        },
        canCheckExternalUser() {
            return Number(this.selectedExternalSchoolId || 0) > 0 && this.normalizedExternalUserEmail !== ''
        },
        selectedRecipient() {
            if (this.recipientMode === 'same_school_person') {
                if (!this.selectedSameSchoolUser) {
                    return null
                }

                return {
                    type: 'same_school_person',
                    typeLabel: 'Person (gleiche Schule)',
                    label: String(this.selectedSameSchoolUser.label || '').trim() || String(this.selectedSameSchoolUser.email || '').trim(),
                    metaLabel: [
                        String(this.selectedSameSchoolUser.short || '').trim(),
                        String(this.selectedSameSchoolUser.email || '').trim(),
                    ].filter((entry) => entry !== '').join(' · '),
                    payload: {
                        target_type: 'user',
                        user_id: Number(this.selectedSameSchoolUser.id || 0),
                    },
                }
            }

            if (this.recipientMode === 'group') {
                if (!this.selectedGroup) {
                    return null
                }

                return {
                    type: 'group',
                    typeLabel: 'Gruppe',
                    label: String(this.selectedGroup.label || '').trim() || 'Gruppe',
                    metaLabel: String(this.selectedGroup.type_label || '').trim(),
                    payload: {
                        target_type: 'group',
                        user_group_id: Number(this.selectedGroup.id || 0),
                    },
                }
            }

            if (!this.externalLookupResult) {
                return null
            }

            return {
                type: 'external_person',
                typeLabel: 'Person (andere Schule)',
                label: String(this.externalLookupResult.label || '').trim() || this.normalizedExternalUserEmail,
                metaLabel: String(this.externalLookupResult.school_label || '').trim() || this.selectedExternalSchoolLabel,
                payload: {
                    target_type: 'user',
                    target_school_id: Number(this.selectedExternalSchoolId || 0),
                    user_email: this.normalizedExternalUserEmail,
                },
            }
        },
        hasRecipientSelection() {
            return this.selectedRecipient !== null
        },
    },
    watch: {
        modelValue(isOpen) {
            if (!isOpen) {
                return
            }
            this.initializeDialogState()
        },
        selectedExternalSchoolId() {
            this.externalLookupResult = null
            this.externalUserLookupError = ''
        },
        externalUserEmail() {
            this.externalLookupResult = null
            this.externalUserLookupError = ''
        },
        recipientMode() {
            this.externalUserLookupError = ''
        },
    },
    methods: {
        initializeDialogState() {
            this.recipientMode = 'same_school_person'
            this.shareMode = 'read_only'
            this.sameSchoolSearch = ''
            this.sameSchoolSearchError = ''
            this.sameSchoolSearchResults = []
            this.selectedSameSchoolUserId = null
            this.groupsError = ''
            this.selectedGroupId = null
            this.externalSchoolsError = ''
            this.selectedExternalSchoolId = null
            this.externalUserEmail = ''
            this.externalUserLookupError = ''
            this.externalLookupResult = null

            if (!this.groupsLoaded) {
                this.loadGroupOptions()
            }
            if (!this.externalSchoolsLoaded) {
                this.loadExternalSchools()
            }
        },
        shareModeLabel(mode) {
            return ({ read_only: 'NUR LESEN', read_write: 'LESEN/SCHREIBEN', full_access: 'VOLLZUGRIFF' })[String(mode || '').trim()] || 'NUR LESEN'
        },
        clearSameSchoolSearch() {
            this.sameSchoolSearch = ''
            this.sameSchoolSearchError = ''
            this.sameSchoolSearchResults = []
            this.selectedSameSchoolUserId = null
        },
        selectSameSchoolUser(user) {
            const userId = Number(user?.id || 0)
            if (userId <= 0) {
                return
            }

            this.selectedSameSchoolUserId = userId
        },
        async searchSameSchoolUsers() {
            const search = this.normalizedSameSchoolSearch
            if (search.length < 2) {
                this.sameSchoolSearchResults = []
                this.sameSchoolSearchError = ''
                this.selectedSameSchoolUserId = null
                return
            }

            this.sameSchoolSearchLoading = true
            this.sameSchoolSearchError = ''

            try {
                const response = await axios.get('/api/admin/materials/shares/lookup-users', { params: { search } })
                const rows = Array.isArray(response?.data?.data) ? response.data.data : []
                this.sameSchoolSearchResults = rows.map((row) => ({
                    id: Number(row?.id || 0),
                    label: String(row?.label || '').trim(),
                    short: String(row?.short || '').trim(),
                    email: String(row?.email || '').trim(),
                })).filter((row) => row.id > 0)
            } catch (error) {
                this.sameSchoolSearchResults = []
                this.selectedSameSchoolUserId = null
                this.sameSchoolSearchError = error?.response?.data?.message || 'Personensuche fehlgeschlagen.'
            } finally {
                this.sameSchoolSearchLoading = false
            }
        },
        async loadGroupOptions() {
            if (this.groupsLoading) {
                return
            }

            this.groupsLoading = true
            this.groupsError = ''

            const results = await Promise.allSettled([
                axios.get('/api/admin/materials/shares/lookup-groups', { params: { type: 'materials' } }),
                axios.get('/api/admin/materials/shares/lookup-groups', { params: { type: 'own' } }),
            ])

            const options = []
            let firstError = ''

            for (const result of results) {
                if (result.status === 'fulfilled') {
                    const rows = Array.isArray(result.value?.data?.data) ? result.value.data.data : []
                    for (const row of rows) {
                        const id = Number(row?.id || 0)
                        if (id <= 0) {
                            continue
                        }
                        options.push({
                            id,
                            type: String(row?.type || '').trim(),
                            type_label: String(row?.type_label || '').trim(),
                            label: String(row?.label || row?.name || '').trim() || 'Gruppe',
                        })
                    }
                    continue
                }

                const message = result.reason?.response?.data?.message
                if (firstError === '' && String(message || '').trim() !== '') {
                    firstError = String(message).trim()
                }
            }

            this.groupOptions = options
                .filter((row, index, source) => source.findIndex((entry) => Number(entry.id) === Number(row.id)) === index)
                .sort((left, right) => String(left.label || '').localeCompare(String(right.label || ''), 'de', { sensitivity: 'base' }))

            this.groupsLoaded = firstError === ''
            if (firstError !== '') {
                this.groupsError = firstError
            }
            this.groupsLoading = false
        },
        onGroupsMenuOpen(isOpen) {
            if (!isOpen) {
                return
            }
            if (!this.groupsLoaded) {
                this.loadGroupOptions()
            }
        },
        async loadExternalSchools() {
            if (this.externalSchoolsLoading) {
                return
            }

            this.externalSchoolsLoading = true
            this.externalSchoolsError = ''
            try {
                const response = await axios.get('/api/admin/materials/shares/lookup-schools')
                this.externalSchools = Array.isArray(response?.data?.data) ? response.data.data : []
                this.externalSchoolsLoaded = true
            } catch (error) {
                this.externalSchools = []
                this.externalSchoolsError = error?.response?.data?.message || 'Schulen konnten nicht geladen werden.'
                this.externalSchoolsLoaded = false
            } finally {
                this.externalSchoolsLoading = false
            }
        },
        onExternalSchoolsMenuOpen(isOpen) {
            if (!isOpen) {
                return
            }
            if (!this.externalSchoolsLoaded) {
                this.loadExternalSchools()
            }
        },
        async checkExternalUser() {
            if (!this.canCheckExternalUser) {
                this.externalUserLookupError = 'Bitte zuerst Schule und E-Mail angeben.'
                this.externalLookupResult = null
                return
            }

            this.externalUserLookupLoading = true
            this.externalUserLookupError = ''
            this.externalLookupResult = null

            try {
                const response = await axios.get('/api/admin/materials/shares/lookup-external-user', {
                    params: {
                        target_school_id: Number(this.selectedExternalSchoolId || 0),
                        user_email: this.normalizedExternalUserEmail,
                    },
                })
                const exists = !!response?.data?.data?.exists
                if (!exists) {
                    this.externalUserLookupError = 'Benutzer wurde nicht gefunden.'
                    return
                }

                this.externalLookupResult = {
                    label: String(response?.data?.data?.label || '').trim() || this.normalizedExternalUserEmail,
                    school_label: String(response?.data?.data?.school_label || '').trim() || this.selectedExternalSchoolLabel,
                    email: String(response?.data?.data?.email || '').trim() || this.normalizedExternalUserEmail,
                }
            } catch (error) {
                const fieldErrors = error?.response?.data?.errors || {}
                let firstFieldError = ''
                for (const value of Object.values(fieldErrors)) {
                    if (Array.isArray(value) && value.length > 0) {
                        firstFieldError = String(value[0] || '').trim()
                        if (firstFieldError !== '') {
                            break
                        }
                    }
                }
                this.externalUserLookupError = firstFieldError || error?.response?.data?.message || 'Prüfung der Person fehlgeschlagen.'
                this.externalLookupResult = null
            } finally {
                this.externalUserLookupLoading = false
            }
        },
        applySelection() {
            if (!this.hasRecipientSelection) {
                return
            }

            this.$emit('dummy-selected', {
                target: {
                    level: String(this.target?.level || '').trim(),
                    id: this.target?.id ?? null,
                    label: String(this.target?.label || '').trim(),
                    parentLabel: String(this.target?.parentLabel || '').trim(),
                },
                recipient: this.selectedRecipient,
                permission: this.shareMode,
                permission_label: this.shareModeLabel(this.shareMode),
            })
            this.$emit('update:modelValue', false)
        },
    },
}
</script>

<style scoped>
.share-draft-shell { display: grid; gap: 12px; }
.share-draft-panel { border: 1px solid rgba(35, 61, 76, 0.12); background: rgba(255, 255, 255, 0.74); border-radius: 12px; padding: 12px; }
.share-draft-panel--soft { background: linear-gradient(180deg, rgba(25, 118, 210, 0.05) 0%, rgba(25, 118, 210, 0.02) 100%); border-color: rgba(25, 118, 210, 0.16); }
.share-draft-mode-toggle,
.share-draft-permission-toggle { width: 100%; }
.share-draft-mode-toggle :deep(.v-btn),
.share-draft-permission-toggle :deep(.v-btn) { min-width: 0; }
.share-draft-mode-btn { text-transform: none; }
.share-draft-result-list { display: grid; gap: 8px; }
.share-draft-result-row {
    border: 1px solid rgba(35, 61, 76, 0.12);
    background: rgba(255, 255, 255, 0.7);
    border-radius: 10px;
    padding: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}
@media (max-width: 640px) {
    .share-draft-result-row {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
