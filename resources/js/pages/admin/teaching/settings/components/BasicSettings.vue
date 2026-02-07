<template>
    <div class="d-flex flex-column ga-3">
        <div class="d-flex flex-row align-center ga-2">
            <v-date-input
                v-model="semester2DateInput"
                label="Datum, ab wann Noten für Semester 2 gelten"
                :readonly="!is_editing"
                class="flex-grow-1" />
            <v-btn v-if="!is_editing" icon="mdi-pencil" size="x-small" color="primary" variant="tonal" @click="is_editing = true" />
            <template v-else>
                <v-btn icon="mdi-check" size="x-small" color="success" variant="flat" @click="save" />
                <v-btn icon="mdi-close" size="x-small" color="warning" variant="flat" @click="cancel" />
            </template>
        </div>
        <div v-if="is_editing" class="d-flex flex-wrap ga-1">
            <v-chip size="x-small" variant="outlined" class="cursor-pointer" @click="semester2DateInput = new Date()">
                Heute: {{ formatDate(new Date()) }}
            </v-chip>
            <v-chip
                v-if="semester2DateFallback"
                size="x-small"
                variant="outlined"
                class="cursor-pointer"
                @click="semester2DateInput = toDate(semester2DateFallback)">
                2. Sem (Schuljahr): {{ formatDate(semester2DateFallback) }}
            </v-chip>
        </div>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { parseLocalDate } from '@/helpers/date'

export default {
    beforeMount() {
        this.syncInput()
    },

    data() {
        return {
            is_editing: false,
            semester2DateInput: null,
            originalValue: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        semester2DateFallback() {
            return this.config?.selected_schoolyear?.sem_2_start || ''
        },
    },

    watch: {
        'config.user.teaching_count_for_semester_2_date'() {
            this.syncInput()
        },
    },

    methods: {
        syncInput() {
            const override = this.config?.user?.teaching_count_for_semester_2_date
            const dateStr = override || this.semester2DateFallback || ''
            this.semester2DateInput = dateStr ? this.toDate(dateStr) : null
            this.originalValue = this.semester2DateInput
        },
        async save() {
            const value = this.semester2DateInput ? this.toDateString(this.semester2DateInput) : null
            await useTeachingStore().saveSemester2Date(value)
            this.is_editing = false
            this.originalValue = this.semester2DateInput
        },
        cancel() {
            this.semester2DateInput = this.originalValue
            this.is_editing = false
        },
        toDate(str) {
            return parseLocalDate(str)
        },
        toDateString(date) {
            const d = parseLocalDate(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
    },
}
</script>
