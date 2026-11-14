<template>
    <v-app-bar
        v-if="isVisible"
        flat
        class="admin-app-bar"
        :height="$vuetify?.display.xs ? 104 : 64"
        :color="shellColor"
        :style="shellTextColor ? { color: shellTextColor } : undefined">
        <template #prepend>
            <v-btn icon="mdi-menu-open" v-if="!isDrawerOpen" @click="isDrawerOpen = true" />
            <img
                :src="`${selectedSchoolLogoSrc}?t=${Date.now()}`"
                alt="Logo"
                height="60px"
                class="admin-app-bar__logo pl-2"
                v-if="selectedSchoolLogoSrc" />
        </template>
        <template #title>
            <span class="d-none d-sm-inline">{{ title }}</span>
        </template>
        <template #append>
            <div class="admin-app-bar__schoolyears d-flex align-center ga-1 ga-sm-2 pr-2 pr-sm-4">
                <v-btn
                    v-if="schoolwideActiveSchoolyearLabel"
                    :aria-label="`Schulweit aktives Schuljahr ${schoolwideActiveSchoolyearLabel}`"
                    :title="schoolwideSchoolyearButtonTitle"
                    :disabled="!canManageSchoolwideSchoolyear"
                    append-icon="mdi-chevron-down"
                    color="white"
                    prepend-icon="mdi-calendar-month-outline"
                    size="small"
                    variant="outlined"
                    class="text-none font-weight-bold"
                    @click="openSchoolyearDialog('schoolwide')">
                    <span class="d-none d-md-inline">Schulweit:&nbsp;</span>
                    <span>{{ schoolwideActiveSchoolyearLabel }}</span>
                </v-btn>
                <v-btn
                    v-else
                    aria-label="Kein schulweites Schuljahr festgelegt"
                    :title="schoolwideSchoolyearButtonTitle"
                    :disabled="!canManageSchoolwideSchoolyear"
                    append-icon="mdi-chevron-down"
                    color="error"
                    prepend-icon="mdi-calendar-alert-outline"
                    size="small"
                    variant="flat"
                    class="text-none font-weight-bold"
                    @click="openSchoolyearDialog('schoolwide')">
                    <span class="d-none d-sm-inline">Kein schulweites Schuljahr</span>
                    <span class="d-sm-none">Kein Schuljahr</span>
                </v-btn>
                <v-btn
                    :aria-label="personalSchoolyearButtonAriaLabel"
                    :title="personalSchoolyearButtonTitle"
                    append-icon="mdi-chevron-down"
                    :color="hasDifferentSelectedSchoolyear || !selectedSchoolyearLabel ? 'warning' : 'white'"
                    :prepend-icon="selectedSchoolyearLabel ? 'mdi-eye-outline' : 'mdi-eye-alert-outline'"
                    size="small"
                    :variant="hasDifferentSelectedSchoolyear || !selectedSchoolyearLabel ? 'flat' : 'outlined'"
                    class="text-none font-weight-bold"
                    @click="openSchoolyearDialog('personal')">
                    <template v-if="selectedSchoolyearLabel">
                        <span class="d-none d-md-inline">Persönlich:&nbsp;</span>
                        <span>{{ selectedSchoolyearLabel }}</span>
                    </template>
                    <template v-else>
                        <span class="d-none d-sm-inline">Kein persönliches Schuljahr</span>
                        <span class="d-sm-none">Kein persönliches Jahr</span>
                    </template>
                </v-btn>
            </div>
        </template>
    </v-app-bar>

    <v-dialog
        v-model="schoolyearDialogOpen"
        persistent
        max-width="560"
        aria-labelledby="schoolyear-dialog-title">
        <v-card rounded="lg" class="schoolyear-dialog">
            <v-card-title id="schoolyear-dialog-title" class="d-flex align-center ga-2">
                <v-icon :icon="schoolyearDialogIcon" color="primary" />
                {{ schoolyearDialogTitle }}
            </v-card-title>

            <v-divider />

            <v-card-text>
                <p class="text-body-2 text-medium-emphasis mb-4">
                    {{ schoolyearDialogDescription }}
                </p>

                <div v-if="schoolyearsLoading" class="d-flex justify-center py-6">
                    <v-progress-circular indeterminate color="primary" aria-label="Schuljahre werden geladen" />
                </div>

                <div v-else-if="availableSchoolyears.length" class="d-flex flex-column ga-2">
                    <v-btn
                        v-for="schoolyear in availableSchoolyears"
                        :key="schoolyear.id"
                        block
                        :color="isDialogSchoolyearActive(schoolyear) ? 'primary' : undefined"
                        :prepend-icon="isDialogSchoolyearActive(schoolyear) ? 'mdi-check-circle' : 'mdi-calendar-outline'"
                        :variant="isDialogSchoolyearActive(schoolyear) ? 'flat' : 'tonal'"
                        :loading="schoolyearSaving && Number(schoolyear.id) === savingSchoolyearId"
                        :disabled="schoolyearSaving"
                        class="schoolyear-option text-none justify-start"
                        @click="selectSchoolyear(schoolyear)">
                        <span class="text-left">
                            <span class="d-block font-weight-bold">{{ schoolyearLabel(schoolyear) }}</span>
                            <span
                                v-if="schoolyearName(schoolyear) !== schoolyearLabel(schoolyear)"
                                class="d-block text-caption">
                                {{ schoolyearName(schoolyear) }}
                            </span>
                        </span>
                    </v-btn>
                </div>

                <v-alert v-else type="warning" variant="tonal" density="comfortable">
                    Es sind keine Schuljahre verfügbar.
                </v-alert>
            </v-card-text>

            <v-divider />

            <v-card-actions>
                <v-spacer />
                <v-btn
                    variant="text"
                    :disabled="schoolyearSaving"
                    @click="closeSchoolyearDialog">
                    Abbrechen
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'

export default {
    props: {
        modelValue: {
            type: Boolean,
            required: true,
        },
        isVisible: {
            type: Boolean,
            required: true,
        },
        selectedSchoolLogoSrc: {
            type: String,
            default: null,
        },
        shellColor: {
            type: String,
            default: 'primary',
        },
        shellTextColor: {
            type: String,
            default: null,
        },
        schoolwideActiveSchoolyear: {
            type: Object,
            default: null,
        },
        selectedSchoolyear: {
            type: Object,
            default: null,
        },
        canManageSchoolwideSchoolyear: {
            type: Boolean,
            default: false,
        },
        title: {
            type: String,
            default: '',
        },
    },

    emits: ['update:modelValue'],

    data() {
        return {
            schoolyearDialogOpen: false,
            schoolyearDialogScope: 'personal',
            schoolyearsLoading: false,
            schoolyearSaving: false,
            savingSchoolyearId: null,
        }
    },

    computed: {
        isDrawerOpen: {
            get() {
                return this.modelValue
            },
            set(value) {
                this.$emit('update:modelValue', value)
            },
        },
        schoolwideActiveSchoolyearLabel() {
            return this.schoolyearLabel(this.schoolwideActiveSchoolyear)
        },
        schoolwideActiveSchoolyearName() {
            return this.schoolyearName(this.schoolwideActiveSchoolyear)
        },
        selectedSchoolyearLabel() {
            return this.schoolyearLabel(this.selectedSchoolyear)
        },
        selectedSchoolyearName() {
            return this.schoolyearName(this.selectedSchoolyear)
        },
        availableSchoolyears() {
            return useSchoolyearStore().schoolyears
        },
        schoolwideSchoolyearButtonTitle() {
            if (!this.canManageSchoolwideSchoolyear) {
                return 'Das schulweit aktive Schuljahr kann nur von Administratoren geändert werden.'
            }

            if (!this.schoolwideActiveSchoolyearName) {
                return 'Schulweites Schuljahr festlegen'
            }

            return `Schulweit aktives Schuljahr ändern: ${this.schoolwideActiveSchoolyearName}`
        },
        personalSchoolyearButtonAriaLabel() {
            return this.selectedSchoolyearLabel
                ? `Persönliches Ansichtsjahr ${this.selectedSchoolyearLabel}`
                : 'Kein persönliches Ansichtsjahr festgelegt'
        },
        personalSchoolyearButtonTitle() {
            return this.selectedSchoolyearName
                ? `Persönliche Ansicht ändern: ${this.selectedSchoolyearName}`
                : 'Persönliches Ansichtsjahr festlegen'
        },
        schoolyearDialogTitle() {
            return this.schoolyearDialogScope === 'schoolwide'
                ? 'Schulweites Schuljahr wählen'
                : 'Persönliches Ansichtsjahr wählen'
        },
        schoolyearDialogDescription() {
            return this.schoolyearDialogScope === 'schoolwide'
                ? 'Diese Auswahl gilt für die gesamte Schule. Wählen Sie das neue schulweit aktive Schuljahr.'
                : 'Diese Auswahl gilt nur für Ihre persönliche Ansicht. Wählen Sie das gewünschte Schuljahr.'
        },
        schoolyearDialogIcon() {
            return this.schoolyearDialogScope === 'schoolwide'
                ? 'mdi-calendar-sync-outline'
                : 'mdi-eye-outline'
        },
        dialogActiveSchoolyearId() {
            const schoolyear = this.schoolyearDialogScope === 'schoolwide'
                ? this.schoolwideActiveSchoolyear
                : this.selectedSchoolyear

            return Number(schoolyear?.id || 0)
        },
        hasDifferentSelectedSchoolyear() {
            if (!this.selectedSchoolyearLabel) return false

            const schoolwideId = Number(this.schoolwideActiveSchoolyear?.id || 0)
            const selectedId = Number(this.selectedSchoolyear?.id || 0)

            return schoolwideId === 0 || selectedId === 0 || schoolwideId !== selectedId
        },
    },

    methods: {
        schoolyearLabel(schoolyear) {
            const concerns = String(schoolyear?.concerns || '').trim()
            if (concerns) return concerns

            return String(schoolyear?.name || '').trim() || null
        },
        schoolyearName(schoolyear) {
            return String(schoolyear?.name || '').trim() || this.schoolyearLabel(schoolyear)
        },
        async openSchoolyearDialog(scope) {
            if (scope === 'schoolwide' && !this.canManageSchoolwideSchoolyear) return

            this.schoolyearDialogScope = scope
            this.schoolyearDialogOpen = true
            this.schoolyearsLoading = true

            try {
                await useSchoolyearStore().index()
            } finally {
                this.schoolyearsLoading = false
            }
        },
        closeSchoolyearDialog() {
            if (this.schoolyearSaving) return

            this.schoolyearDialogOpen = false
        },
        isDialogSchoolyearActive(schoolyear) {
            return Number(schoolyear?.id || 0) === this.dialogActiveSchoolyearId
        },
        async selectSchoolyear(schoolyear) {
            const schoolyearId = Number(schoolyear?.id || 0)
            if (!schoolyearId || this.schoolyearSaving) return

            if (schoolyearId === this.dialogActiveSchoolyearId) {
                this.closeSchoolyearDialog()
                return
            }

            const schoolyearStore = useSchoolyearStore()
            const adminStore = useAdminStore()
            this.schoolyearSaving = true
            this.savingSchoolyearId = schoolyearId

            try {
                const wasSaved = this.schoolyearDialogScope === 'schoolwide'
                    ? await schoolyearStore.setActiveSchoolyearInSchoolTool(schoolyearId)
                    : await schoolyearStore.setActiveSchoolyear(schoolyearId)

                if (!wasSaved) return

                await adminStore.loadConfig()
                this.schoolyearDialogOpen = false
            } finally {
                this.schoolyearSaving = false
                this.savingSchoolyearId = null
            }
        },
    },
}
</script>

<style scoped>
.admin-app-bar__logo {
    max-width: 180px;
    object-fit: contain;
}

.schoolyear-dialog .v-card-title {
    white-space: normal;
    overflow-wrap: anywhere;
}

.schoolyear-option {
    height: auto;
    min-height: 44px;
    padding-block: 8px;
}

.schoolyear-option :deep(.v-btn__content) {
    min-width: 0;
    white-space: normal;
    overflow-wrap: anywhere;
}

@media (max-width: 599px) {
    .admin-app-bar__logo {
        max-width: 48px;
        height: 44px;
    }

    .admin-app-bar :deep(.v-toolbar-title) {
        display: none;
    }

    .admin-app-bar :deep(.v-toolbar__append) {
        min-width: 0;
        flex: 1 1 0;
    }

    .admin-app-bar__schoolyears {
        flex-direction: column;
        width: 100%;
        min-width: 0;
        padding-left: 8px;
    }

    .admin-app-bar__schoolyears > .v-btn {
        width: 100%;
        min-width: 0;
        height: 44px;
        padding-inline: 8px;
    }

    .admin-app-bar__schoolyears :deep(.v-btn__content) {
        min-width: 0;
        white-space: normal;
        overflow-wrap: anywhere;
        line-height: 1.2;
    }
}
</style>
