<template>
    <ItsGridBox variant="overview" color="primary" title="Eigene freie Tage" icon="mdi-account-clock" class="w-100">
        <v-form ref="form" v-model="is_valid" @submit.prevent="createHolidays">
            <v-date-input v-model="data.date_from" label="Datum von" class="flex-grow-1" />
            <v-date-input v-model="data.date_until" label="Datum bis (optional)" class="mt-3 flex-grow-1" />

            <v-text-field v-model="data.reason" label="Grund" class="mt-3" :rules="[maxLength(255)]" hide-details="auto" />

            <div class="d-flex flex-row align-center justify-space-between mt-3">
                <v-btn color="warning" flat tile @click="resetForm">Zurücksetzen</v-btn>
                <v-btn color="success" flat tile type="submit" :disabled="!data.date_from">Freie Tage erstellen</v-btn>
            </div>
        </v-form>

        <v-divider class="my-4" />

        <div class="text-subtitle-2 mb-2">Freie Tage (allgemein + eigene)</div>
        <v-list density="compact" class="holidays-list">
            <v-list-item v-for="holiday in my_holidays" :key="holiday.id">
                <div class="d-flex align-center ga-2 w-100">
                    <v-chip size="x-small" color="primary" variant="tonal">{{ getWeekday(holiday.date) }}</v-chip>
                    <v-chip size="x-small" color="primary" variant="outlined">{{ formatDate(holiday.date) }}</v-chip>
                    <v-chip
                        size="x-small"
                        :color="holiday.scope === 'school' ? 'info' : 'secondary'"
                        variant="flat">
                        {{ holiday.scope === 'school' ? 'Allgemein' : 'Eigener Tag' }}
                    </v-chip>
                    <div class="text-caption flex-grow-1">
                        {{ holiday.reason || 'Kein Grund angegeben' }}
                    </div>
                    <v-btn
                        v-if="holiday.scope !== 'school'"
                        icon="mdi-delete"
                        size="x-small"
                        color="error"
                        variant="flat"
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
        }
    },

    computed: {
        ...mapWritableState(useHolidayStore, ['my_holidays']),
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
        },
        async deleteHoliday(holiday) {
            const ok = await this.holidayStore.destroyMine(holiday.id)
            if (!ok) return

            await this.holidayStore.indexMine()
            await this.courseStore.index()
        },
    },
}
</script>

<style scoped>
.holidays-list {
    background: transparent;
}
</style>
