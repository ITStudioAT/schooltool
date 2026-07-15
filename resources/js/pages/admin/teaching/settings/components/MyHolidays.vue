<template>
    <ItsGridBox variant="overview" color="primary" title="Eigene freie Tage" icon="mdi-account-clock" class="w-100" :disabled="isSavingMyHolidays">
        <v-form ref="form" v-model="is_valid" @submit.prevent="createHolidays">
            <v-date-input v-model="data.date_from" label="Datum von" class="flex-grow-1" />
            <v-date-input v-model="data.date_until" label="Datum bis (optional)" class="mt-3 flex-grow-1" />

            <v-text-field v-model="data.reason" label="Grund" class="mt-3" :rules="[maxLength(255)]" hide-details="auto" />

            <div class="d-flex flex-row align-center justify-space-between mt-3">
                <v-btn color="warning" flat tile :disabled="isSavingMyHolidays" @click="resetForm">Zurücksetzen</v-btn>
                <v-btn color="success" flat tile type="submit" :loading="my_holidays_save_action === 'create'" :disabled="!data.date_from || isSavingMyHolidays">Freie Tage erstellen</v-btn>
            </div>
        </v-form>

        <v-divider class="my-4" />

        <div class="text-subtitle-2 mb-2">Freie Tage (allgemein + eigene)</div>
        <v-list class="holidays-list pa-0">
            <v-list-item v-for="holiday in my_holidays" :key="holiday.id" class="holiday-list-item rounded-lg mb-2">
                <div class="holiday-row">
                    <div class="holiday-date-block">
                        <v-icon icon="mdi-calendar-blank-outline" color="primary" size="24" class="holiday-date-icon" />
                        <div>
                            <div class="text-subtitle-1 font-weight-bold text-primary">
                                {{ getWeekday(holiday.date) }}
                            </div>
                            <div class="text-body-2 font-weight-medium text-medium-emphasis">
                                {{ formatDate(holiday.date) }}
                            </div>
                        </div>
                    </div>

                    <div class="holiday-details">
                        <v-chip
                            size="small"
                            :prepend-icon="holiday.scope === 'school' ? 'mdi-school-outline' : 'mdi-account-outline'"
                            :color="holiday.scope === 'school' ? 'info' : 'secondary'"
                            variant="tonal">
                            {{ holiday.scope === 'school' ? 'Allgemein' : 'Eigener Tag' }}
                        </v-chip>
                        <div class="holiday-reason text-body-2 font-weight-medium">
                            {{ holiday.reason || 'Kein Grund angegeben' }}
                        </div>
                    </div>

                    <v-btn
                        v-if="holiday.scope !== 'school'"
                        class="holiday-delete"
                        icon="mdi-delete-outline"
                        size="small"
                        color="error"
                        variant="tonal"
                        aria-label="Eigenen freien Tag löschen"
                        title="Eigenen freien Tag löschen"
                        :loading="my_holidays_save_action === `delete:${holiday.id}`"
                        :disabled="isSavingMyHolidays"
                        @click="deleteHoliday(holiday)" />
                </div>
            </v-list-item>
            <v-list-item v-if="!my_holidays?.length">
                <v-list-item-title class="text-caption text-medium-emphasis">Keine eigenen freien Tage erfasst.</v-list-item-title>
            </v-list-item>
        </v-list>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useHolidayStore } from '@/stores/admin/teaching/HolidayStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox },

    async beforeMount() {
        this.courseStore = useCourseStore()
        this.holidayStore = useHolidayStore()
        await this.holidayStore.indexMine()
    },

    data() {
        return {
            courseStore: null,
            holidayStore: null,
            is_valid: false,
            data: {
                date_from: '',
                date_until: '',
                reason: '',
            },
            my_holidays_save_action: null,
        }
    },

    computed: {
        ...mapWritableState(useHolidayStore, ['my_holidays']),
        isSavingMyHolidays() {
            return this.my_holidays_save_action !== null
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
    },

    methods: {
        async runMyHolidayMutation(action, callback) {
            if (this.my_holidays_save_action) {
                return false
            }

            this.my_holidays_save_action = action
            await this.$nextTick()

            try {
                return await callback()
            } finally {
                this.my_holidays_save_action = null
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
        async createHolidays() {
            if (!this.data.date_from) return
            await this.runMyHolidayMutation('create', async () => {
                const payload = {
                    date_from: this.data.date_from,
                    date_until: this.data.date_until || null,
                    reason: this.data.reason?.trim() || null,
                }

                const ok = await this.holidayStore.storeMine(payload)
                if (!ok) return

                await this.holidayStore.indexMine()
                await this.courseStore.index()
                this.resetForm()
            })
        },
        async deleteHoliday(holiday) {
            await this.runMyHolidayMutation(`delete:${holiday.id}`, async () => {
                const ok = await this.holidayStore.destroyMine(holiday.id)
                if (!ok) return

                await this.holidayStore.indexMine()
                await this.courseStore.index()
            })
        },
    },
}
</script>

<style scoped>
.holidays-list {
    background: transparent;
}

.holiday-list-item {
    min-height: 76px;
    border: 1px solid rgba(var(--v-theme-primary), 0.12);
    background: rgba(var(--v-theme-primary), 0.035);
}

.holiday-list-item :deep(.v-list-item__content) {
    overflow: visible;
}

.holiday-row {
    display: grid;
    grid-template-columns: minmax(170px, 0.8fr) minmax(0, 1.5fr) auto;
    align-items: center;
    gap: 16px;
    width: 100%;
    padding: 8px 4px;
}

.holiday-date-block,
.holiday-details {
    display: flex;
    align-items: center;
}

.holiday-date-block {
    gap: 12px;
}

.holiday-date-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    border-radius: 12px;
    background: rgba(var(--v-theme-primary), 0.1);
}

.holiday-details {
    min-width: 0;
    gap: 12px;
}

.holiday-reason {
    min-width: 0;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

@media (max-width: 600px) {
    .holiday-row {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
    }

    .holiday-details {
        grid-column: 1 / -1;
        flex-wrap: wrap;
    }

    .holiday-delete {
        grid-column: 2;
        grid-row: 1;
    }
}
</style>
