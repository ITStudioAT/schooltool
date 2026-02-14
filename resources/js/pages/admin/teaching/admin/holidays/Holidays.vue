<template>
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Ferien" icon="mdi-beach" class="w-100">
            <div class="d-flex align-center justify-space-between ga-2">
                <div class="text-subtitle-2">Freie Tage erstellen</div>
                <v-btn
                    :icon="show_create_form ? 'mdi-close' : 'mdi-plus'"
                    size="small"
                    color="primary"
                    variant="tonal"
                    @click="toggleCreateForm" />
            </div>

            <v-expand-transition>
                <div v-if="show_create_form" class="mt-3">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="createHolidays">
                        <v-date-input v-model="data.date_from" label="Datum von" class="flex-grow-1" />
                        <v-date-input v-model="data.date_until" label="Datum bis (optional)" class="mt-3 flex-grow-1" />

                        <v-text-field v-model="data.reason" label="Grund" class="mt-3" :rules="[maxLength(255)]" hide-details="auto" />

                        <div class="d-flex flex-row align-center justify-space-between mt-3">
                            <v-btn color="warning" flat tile @click="resetForm">Zurücksetzen</v-btn>
                            <v-btn color="success" flat tile type="submit" :disabled="!data.date_from">Freie Tage erstellen</v-btn>
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
                    :disabled="!selected_holiday_ids.length"
                    @click="deleteSelectedHolidays">
                    Löschen ({{ selected_holiday_ids.length }})
                </v-btn>
                </div>
                <v-list density="compact" class="holidays-list">
                    <v-list-item v-for="holiday in holidays" :key="holiday.id" class="px-0">
                        <div class="d-flex align-center justify-start ga-2 w-100 holiday-row">
                            <v-checkbox-btn
                                v-model="selected_holiday_ids"
                                :value="holiday.id"
                                color="primary"
                                density="compact"
                                class="holiday-checkbox" />
                            <v-chip size="x-small" color="primary" variant="tonal">{{ getWeekday(holiday.date) }}</v-chip>
                            <v-chip size="x-small" color="primary" variant="outlined">{{ formatDate(holiday.date) }}</v-chip>
                            <div class="text-caption flex-grow-1 text-start">
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
            selected_holiday_ids: [],
            data: {
                date_from: '',
                date_until: '',
                reason: '',
            },
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        ...mapWritableState(useHolidayStore, ['holidays']),
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
            this.show_create_form = !this.show_create_form
            if (!this.show_create_form) {
                this.resetForm()
            }
        },
        async createHolidays() {
            if (!this.data.date_from) return
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
        },
        async deleteSelectedHolidays() {
            if (!this.selected_holiday_ids.length) return
            const ids = [...this.selected_holiday_ids]
            const result = await this.holidayStore.destroyMany(ids)
            if (!result || result.deleted <= 0) return

            await this.holidayStore.index()
            await this.courseStore.index()
            this.selected_holiday_ids = []
        },
    },
}
</script>

<style scoped>
.holidays-list {
    background: transparent;
    text-align: left;
}

.free-days-overview {
    text-align: left;
}

.holiday-row {
    justify-content: flex-start;
    align-items: center;
    flex-wrap: nowrap;
}

.holidays-list :deep(.v-list-item__content) {
    text-align: left;
}

.holiday-checkbox {
    flex: 0 0 auto !important;
    margin: 0 !important;
}

.holiday-checkbox :deep(.v-selection-control) {
    flex: 0 0 auto !important;
}
</style>
