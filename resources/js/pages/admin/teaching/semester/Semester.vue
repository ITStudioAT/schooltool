<template>
    <!-- Semester -->
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Semester" icon="mdi-calendar-month" class="w-100">
            <div class="d-flex flex-column ga-3">
                <div class="d-flex flex-wrap flex-row align-center ga-2">
                    <ItsMenuButton :title="'1. Semester'" :color="activeSemester === 1 ? 'success' : 'primary'" @click="setActiveSemester(1)" />
                    <ItsMenuButton v-if="hasTwoSemesters" :title="'2. Semester'" :color="activeSemester === 2 ? 'success' : 'primary'" @click="setActiveSemester(2)" />
                    <ItsMenuButton v-if="hasTwoSemesters" :title="'1+2 Semester'" :color="activeSemester === 3 ? 'success' : 'primary'" @click="setActiveSemester(3)" />
                </div>

                <div v-if="activeSemester === 1" class="d-flex flex-column ga-1">
                    <v-date-input
                        v-model="semester2DateInput"
                        label="Datum, ab wann Noten für Semester 2 gelten"
                        class="flex-grow-1"
                        density="compact" />
                    <div class="d-flex flex-wrap ga-1 mt-1">
                        <v-chip size="x-small" variant="outlined" class="cursor-pointer" @click="setSemester2Date(new Date())">
                            Heute: {{ formatDate(new Date()) }}
                        </v-chip>
                        <v-chip
                            size="x-small"
                            variant="outlined"
                            class="cursor-pointer"
                            @click="setSemester2Date(semester2DateFallback)"
                            v-if="semester2DateFallback">
                            2. Sem (Schuljahr): {{ formatDate(semester2DateFallback) }}
                        </v-chip>
                    </div>
                </div>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapWritableState, mapState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import { parseLocalDate } from '@/helpers/date'

export default {
    components: { ItsGridBox, ItsMenuButton },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.activeSemester = this.config?.user?.teaching_active_semester || 1
        this.syncSemester2DateInput()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            activeSemester: 1,
            semester2DateInput: '',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapState(useTeachingStore, ['hasTwoSemesters']),
        semester2DateFallback() {
            return this.config?.selected_schoolyear?.sem_2_start || ''
        },
    },

    watch: {
        'config.user.teaching_active_semester'(value) {
            if (value) this.activeSemester = value
        },
        'config.user.teaching_count_for_semester_2_date'() {
            this.syncSemester2DateInput()
        },
        'config.selected_schoolyear.sem_2_start'() {
            this.syncSemester2DateInput()
        },
        semester2DateInput(val) {
            if (val && val instanceof Date) {
                this.semester2DateInput = this.toDateString(val)
                return
            }
            this.persistSemester2Date(val)
        },
    },

    methods: {
        setActiveSemester(semester) {
            this.activeSemester = semester
            if (this.config?.user) this.config.user.teaching_active_semester = semester
        },
        syncSemester2DateInput() {
            if (!this.config?.user) return
            const override = this.config.user.teaching_count_for_semester_2_date
            this.semester2DateInput = override || this.semester2DateFallback || ''
        },
        persistSemester2Date(value) {
            if (!this.config?.user) return
            if (!value) {
                this.config.user.teaching_count_for_semester_2_date = null
                this.semester2DateInput = this.semester2DateFallback || ''
                return
            }
            if (this.semester2DateFallback && value === this.semester2DateFallback) {
                this.config.user.teaching_count_for_semester_2_date = null
                return
            }
            this.config.user.teaching_count_for_semester_2_date = value
        },
        setSemester2Date(dateStr) {
            if (!dateStr) return
            this.semester2DateInput = this.toDateString(dateStr)
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        toDateString(date) {
            const d = parseLocalDate(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
    },
}
</script>
