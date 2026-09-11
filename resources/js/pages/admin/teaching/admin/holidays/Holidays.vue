<template>
    <v-col cols="12" class="teaching-admin-holidays-col">
        <ItsGridBox variant="overview" color="primary" title="Ferien" subtitle="Freie Tage erfassen und bereinigen" icon="mdi-beach" class="w-100" :disabled="isSavingHolidays">
            <div class="d-flex flex-wrap ga-2 mb-3">
                <v-btn color="primary" variant="tonal" prepend-icon="mdi-download" :loading="holidays_save_action === 'export'" :disabled="isSavingHolidays" @click="exportHolidays">
                    Ferien exportieren
                </v-btn>
                <v-btn color="primary" variant="tonal" prepend-icon="mdi-upload" :disabled="isSavingHolidays" @click="show_import_form = !show_import_form">
                    Ferien importieren
                </v-btn>
            </div>
            <v-expand-transition>
                <div v-if="show_import_form" class="mb-4 text-start">
                    <p class="text-body-2 mb-3">
                        Eine Ferien-Exportdatei (JSON, maximal 2 MB) in die aktuelle Schule und das ausgewählte Schuljahr importieren.
                        Die Datumsangaben werden unverändert übernommen. Bei bereits erfassten Tagen wird nur der Grund aktualisiert
                        (auch ein leerer Grund wird übernommen). Neue Tage werden ergänzt; andere Termine bleiben erhalten.
                    </p>
                    <v-file-input v-model="import_file" label="Ferien-Datei auswählen" accept=".json,application/json" :disabled="isSavingHolidays" show-size hide-details="auto" @update:model-value="clearImportFeedback" />
                    <v-alert v-if="import_errors.length" type="error" variant="tonal" class="mt-3" role="alert">
                        <ul class="pl-4"><li v-for="(error, index) in import_errors" :key="index">{{ error }}</li></ul>
                    </v-alert>
                    <v-alert v-if="import_result" type="success" variant="tonal" class="mt-3" role="status">
                        Import abgeschlossen: {{ import_result.created }} freie Tage ergänzt, {{ import_result.updated }} aktualisiert, {{ import_result.unchanged }} unverändert.
                    </v-alert>
                    <v-btn color="success" class="mt-3" :loading="holidays_save_action === 'import'" :disabled="!selectedImportFile || isSavingHolidays" @click="importHolidays">
                        Datei importieren
                    </v-btn>
                </div>
            </v-expand-transition>
            <div class="d-flex align-center justify-space-between ga-2">
                <div class="text-subtitle-2">Freie Tage erstellen</div>
                <v-btn
                    :icon="show_create_form ? 'mdi-close' : 'mdi-plus'"
                    size="small"
                    color="primary"
                    variant="tonal"
                    :disabled="isSavingHolidays"
                    @click="toggleCreateForm" />
            </div>

            <v-expand-transition>
                <div v-if="show_create_form" class="mt-3 holidays-create-form-wrap">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="createHolidays">
                        <v-date-input v-model="data.date_from" label="Datum von" class="flex-grow-1" />
                        <v-date-input v-model="data.date_until" label="Datum bis (optional)" class="mt-3 flex-grow-1" />

                        <v-text-field v-model="data.reason" label="Grund" class="mt-3" :rules="[maxLength(255)]" hide-details="auto" />

                        <div class="d-flex flex-row align-center justify-space-between mt-3">
                            <v-btn color="warning" flat tile :disabled="isSavingHolidays" @click="resetForm">Zurücksetzen</v-btn>
                            <v-btn color="success" flat tile type="submit" :loading="holidays_save_action === 'create'" :disabled="!data.date_from || isSavingHolidays">Freie Tage erstellen</v-btn>
                        </div>
                    </v-form>
                </div>
            </v-expand-transition>

            <v-divider class="my-4" />

            <div class="free-days-overview">
                <div class="text-subtitle-2 mb-2 text-start">Erfasste freie Tage</div>
                <div class="d-flex align-center justify-start ga-2 mb-2">
                    <v-checkbox
                        v-model="allSelected"
                        hide-details
                        density="compact"
                        color="primary"
                        label="Alle auswählen" />
                    <v-btn
                        color="error"
                        variant="flat"
                        size="small"
                        prepend-icon="mdi-delete"
                        :loading="holidays_save_action === 'delete-selected'"
                        :disabled="!selected_holiday_ids.length || isSavingHolidays"
                        @click="deleteSelectedHolidays">
                        Löschen ({{ selected_holiday_ids.length }})
                    </v-btn>
                </div>
                <v-list class="holidays-list pa-0">
                    <v-list-item
                        v-for="holiday in holidays"
                        :key="holiday.id"
                        class="holidays-list-item"
                        :class="{ 'holidays-list-item--selected': selected_holiday_ids.includes(holiday.id) }">
                        <div class="holiday-row">
                            <v-checkbox-btn
                                v-model="selected_holiday_ids"
                                :value="holiday.id"
                                color="primary"
                                density="compact"
                                class="holiday-checkbox" />
                            <div class="holiday-date-block">
                                <v-avatar color="primary" variant="tonal" size="30" class="holiday-date-icon">
                                    <v-icon icon="mdi-calendar-blank-outline" size="16" />
                                </v-avatar>
                                <div class="holiday-date-text">
                                    <div class="holiday-weekday">{{ getWeekday(holiday.date) }}</div>
                                    <div class="holiday-date">{{ formatDate(holiday.date) }}</div>
                                </div>
                            </div>
                            <div class="holiday-reason">
                                {{ holiday.reason || 'Kein Grund angegeben' }}
                            </div>
                        </div>
                    </v-list-item>
                    <v-list-item v-if="!holidays?.length">
                        <v-list-item-title class="text-caption text-medium-emphasis text-start">Keine freien Tage erfasst.</v-list-item-title>
                    </v-list-item>
                </v-list>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useHolidayStore } from '@/stores/admin/teaching/HolidayStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.holidayStore = useHolidayStore()
        await this.holidayStore.index()
    },

    data() {
        return {
            adminStore: null,
            courseStore: null,
            holidayStore: null,
            is_valid: false,
            show_create_form: false,
            show_import_form: false,
            import_file: null,
            import_result: null,
            selected_holiday_ids: [],
            data: {
                date_from: '',
                date_until: '',
                reason: '',
            },
            holidays_save_action: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        ...mapWritableState(useHolidayStore, ['holidays', 'import_errors']),
        selectedImportFile() {
            return Array.isArray(this.import_file) ? this.import_file[0] : this.import_file
        },
        isSavingHolidays() {
            return this.holidays_save_action !== null
        },
        allSelected: {
            get() {
                const total = this.holidays?.length || 0
                return total > 0 && this.selected_holiday_ids.length === total
            },
            set(value) {
                if (value) {
                    this.selected_holiday_ids = (this.holidays || []).map((holiday) => holiday.id)
                    return
                }
                this.selected_holiday_ids = []
            },
        },
    },

    watch: {
        'data.date_from'(val) {
            if (val && val instanceof Date) {
                this.data.date_from = this.toDateString(val)
            }
        },
        'data.date_until'(val) {
            if (val && val instanceof Date) {
                this.data.date_until = this.toDateString(val)
            }
        },
        holidays: {
            handler(newHolidays) {
                const availableIds = new Set((newHolidays || []).map((holiday) => holiday.id))
                this.selected_holiday_ids = this.selected_holiday_ids.filter((id) => availableIds.has(id))
            },
            immediate: true,
        },
    },

    methods: {
        clearImportFeedback() {
            this.import_errors = []
            this.import_result = null
        },
        async exportHolidays() {
            await this.runHolidayMutation('export', async () => {
                const blob = await this.holidayStore.exportHolidays()
                if (!blob) return

                const objectUrl = URL.createObjectURL(blob)
                const link = document.createElement('a')
                link.href = objectUrl
                link.download = 'ferien.json'
                document.body.appendChild(link)
                link.click()
                link.remove()
                setTimeout(() => URL.revokeObjectURL(objectUrl), 1000)
            })
        },
        async importHolidays() {
            if (!this.selectedImportFile) return
            await this.runHolidayMutation('import', async () => {
                this.clearImportFeedback()
                const result = await this.holidayStore.importHolidays(this.selectedImportFile)
                if (!result) return

                this.import_file = null
                this.import_result = result
                await this.holidayStore.index()
                await this.courseStore.index()
                this.selected_holiday_ids = []
            })
        },
        async runHolidayMutation(action, callback) {
            if (this.holidays_save_action) {
                return false
            }

            this.holidays_save_action = action
            await this.$nextTick()

            try {
                return await callback()
            } finally {
                this.holidays_save_action = null
            }
        },
        toDateString(date) {
            const parsed = parseLocalDate(date)
            const year = parsed.getFullYear()
            const month = String(parsed.getMonth() + 1).padStart(2, '0')
            const day = String(parsed.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        formatDate(date) {
            if (!date) return ''
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) return ''
            return parsed.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        getWeekday(date) {
            if (!date) return ''
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) return ''
            return parsed.toLocaleDateString('de-DE', { weekday: 'long' })
        },
        resetForm() {
            this.data = {
                date_from: '',
                date_until: '',
                reason: '',
            }
        },
        toggleCreateForm() {
            if (this.isSavingHolidays) {
                return
            }
            this.show_create_form = !this.show_create_form
            if (!this.show_create_form) {
                this.resetForm()
            }
        },
        async createHolidays() {
            if (!this.data.date_from) return
            await this.runHolidayMutation('create', async () => {
                const payload = {
                    date_from: this.data.date_from,
                    date_until: this.data.date_until || null,
                    reason: this.data.reason?.trim() || null,
                }

                const ok = await this.holidayStore.store(payload)
                if (!ok) return

                await this.holidayStore.index()
                await this.courseStore.index()
                this.selected_holiday_ids = []
                this.show_create_form = false
                this.resetForm()
            })
        },
        async deleteSelectedHolidays() {
            if (!this.selected_holiday_ids.length) return
            await this.runHolidayMutation('delete-selected', async () => {
                const ids = [...this.selected_holiday_ids]
                const result = await this.holidayStore.destroyMany(ids)
                if (!result || result.deleted <= 0) return

                await this.holidayStore.index()
                await this.courseStore.index()
                this.selected_holiday_ids = []
            })
        },
    },
}
</script>

<style scoped>
.holidays-list {
    background: transparent;
    text-align: left;
}

.holidays-create-form-wrap {
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    background: rgba(255, 255, 255, 0.72);
    padding: 10px;
}

.free-days-overview {
    text-align: left;
}

.holidays-list-item {
    min-height: 48px;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
    border-radius: 10px;
    background: rgba(var(--v-theme-surface), 0.92);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
    margin-bottom: 4px;
    padding: 0 6px;
    transition:
        border-color 160ms ease,
        box-shadow 160ms ease,
        background-color 160ms ease;
}

.holidays-list-item:hover {
    border-color: rgba(var(--v-theme-primary), 0.28);
    box-shadow: 0 3px 9px rgba(15, 23, 42, 0.07);
}

.holidays-list-item--selected {
    border-color: rgba(var(--v-theme-primary), 0.42);
    background: rgba(var(--v-theme-primary), 0.06);
}

.holiday-row {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}

.holidays-list :deep(.v-list-item__content) {
    text-align: left;
    overflow: visible;
}

.holiday-checkbox {
    flex: 0 0 auto !important;
    align-self: center;
    margin: 0 !important;
}

.holiday-checkbox :deep(.v-selection-control) {
    flex: 0 0 auto !important;
    min-height: 30px;
}

.holiday-date-block {
    display: flex;
    flex: 0 0 180px;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.holiday-date-icon {
    flex: 0 0 auto;
}

.holiday-date-text {
    min-width: 0;
    line-height: 1.15;
}

.holiday-weekday {
    color: rgb(var(--v-theme-primary));
    font-size: 1rem;
    font-weight: 700;
}

.holiday-date {
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.875rem;
    font-weight: 500;
    margin-top: 1px;
}

.holiday-reason {
    flex: 1 1 auto;
    min-width: 0;
    color: rgb(var(--v-theme-on-surface));
    font-size: 1.05rem;
    font-weight: 600;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

@media (max-width: 600px) {
    .holidays-list-item {
        padding: 0 6px;
    }

    .holiday-row {
        gap: 6px;
    }

    .holiday-date-block {
        flex-basis: 150px;
    }
}
</style>
