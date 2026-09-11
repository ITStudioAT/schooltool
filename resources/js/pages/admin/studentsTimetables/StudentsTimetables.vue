<template>
    <v-container fluid class="students-timetables-page ma-0 w-100 pa-2">
        <div class="students-timetables-shell">
            <AdminSectionHero
                class="students-timetables-hero"
                eyebrow="Stundenpläne"
                title="SEPP"
                primary-color="#1e2433"
                secondary-color="#1e2433"
                :active-section="activeSection"
                :chips="headerChips" />

            <v-sheet v-if="!automaticTimetableRouteActive" class="st-nav">
                <div class="st-nav__buttons">
                    <v-btn
                        v-for="item in navigationItems"
                        :key="item.key"
                        variant="text"
                        class="st-nav__button"
                        :class="activeNavigationKey === item.key ? 'st-nav__button--active' : 'st-nav__button--idle'"
                        @click="handleNavigation(item.key)">
                        <span class="st-nav__button-copy">
                            <span class="st-nav__button-title">{{ item.label }}</span>
                        </span>
                    </v-btn>
                </div>
            </v-sheet>

            <v-row :key="subjectPlanRevision" class="students-timetables-content w-100" dense>
                <v-col v-if="shouldOfferSubjectPlanCarryForward" cols="12">
                    <v-alert
                        type="warning"
                        variant="tonal"
                        class="subject-plan-carry-forward-warning">
                        <div class="subject-plan-carry-forward-warning__content">
                            <div class="subject-plan-carry-forward-warning__copy">
                                <strong>Der Soll-/Fachplan für {{ personalSchoolyearLabel }} fehlt.</strong>
                                <span>
                                    Ohne diesen Plan können Stundenplan v3, Tests, TT-Einträge und
                                    Fächerdaten unvollständig sein. Aus {{ subjectPlanPreviousSchoolyear.name }}
                                    können {{ subjectPlanPreviousSchoolyear.subject_rows_count }} Fachzeilen
                                    ({{ subjectPlanPreviousSchoolyear.normal_subject_rows_count }} Normalstudium,
                                    {{ subjectPlanPreviousSchoolyear.compact_subject_rows_count }} Kompaktstudium)
                                    und {{ subjectPlanPreviousSchoolyear.mappings_count }} Zuordnungen übernommen werden.
                                </span>
                            </div>
                            <v-btn
                                v-if="canManageStudentsTimetables"
                                color="warning"
                                variant="flat"
                                size="large"
                                prepend-icon="mdi-content-copy"
                                @click="openSubjectPlanCarryForwardDialog">
                                Soll-/Fachplan übernehmen
                            </v-btn>
                            <strong v-else class="subject-plan-carry-forward-warning__admin-note">
                                Bitte wenden Sie sich für die Übernahme an einen Administrator.
                            </strong>
                        </div>
                    </v-alert>
                </v-col>

                <v-col v-if="subjectPlanCarryForwardMessage" cols="12">
                    <v-alert type="success" variant="tonal">
                        {{ subjectPlanCarryForwardMessage }}
                    </v-alert>
                </v-col>

                <v-col
                    v-if="subjectPlanStatusError || (subjectPlanCarryForwardError && !subjectPlanCarryForwardDialog)"
                    cols="12">
                    <v-alert type="error" variant="tonal">
                        {{ subjectPlanStatusError || subjectPlanCarryForwardError }}
                    </v-alert>
                </v-col>

                <Timetable v-if="main_action === 'timetable'" />
                <v-col v-if="main_action === 'timetable-v3'" cols="12">
                    <TimetableV3 />
                </v-col>
                <v-col v-if="main_action === 'tt-entries'" cols="12">
                    <TtEntries />
                </v-col>
                <TestsV3 v-if="main_action === 'tests-v3'" />
                <Import v-if="main_action === 'import'" />
                <SubjectsOverview v-if="main_action === 'subjects-overview'" />
                <StudentsTimetablesTeachers v-if="main_action === 'teachers'" />
            </v-row>

            <v-dialog
                v-model="subjectPlanCarryForwardDialog"
                persistent
                max-width="760">
                <v-card rounded="lg">
                    <v-card-title class="d-flex align-center ga-3 text-wrap">
                        <v-icon icon="mdi-alert-circle-outline" color="warning" />
                        Soll-/Fachplan für {{ personalSchoolyearLabel }} übernehmen?
                    </v-card-title>
                    <v-card-text class="d-grid ga-4">
                        <p class="ma-0">
                            Für das persönliche Schuljahr <strong>{{ personalSchoolyearLabel }}</strong>
                            gibt es noch keinen Soll-/Fachplan. Er wird in mehreren Bereichen von
                            SEPP benötigt.
                        </p>
                        <v-alert type="info" variant="tonal">
                            Aus <strong>{{ subjectPlanPreviousSchoolyear?.name }}</strong> werden gemeinsam
                            <strong>{{ subjectPlanPreviousSchoolyear?.subject_rows_count }} Fachzeilen</strong>
                            ({{ subjectPlanPreviousSchoolyear?.normal_subject_rows_count }} Normalstudium,
                            {{ subjectPlanPreviousSchoolyear?.compact_subject_rows_count }} Kompaktstudium) und
                            <strong>{{ subjectPlanPreviousSchoolyear?.mappings_count }} Zuordnungen</strong> übernommen.
                        </v-alert>
                        <v-alert v-if="subjectPlanCarryForwardError" type="error" variant="tonal">
                            {{ subjectPlanCarryForwardError }}
                        </v-alert>
                    </v-card-text>
                    <v-card-actions class="px-6 pb-5">
                        <v-spacer />
                        <v-btn
                            variant="text"
                            :disabled="subjectPlanCarryForwardLoading"
                            @click="dismissSubjectPlanCarryForwardDialog">
                            Später
                        </v-btn>
                        <v-btn
                            color="primary"
                            variant="flat"
                            prepend-icon="mdi-content-copy"
                            :loading="subjectPlanCarryForwardLoading"
                            @click="carryForwardSubjectPlan">
                            Jetzt übernehmen
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    </v-container>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import {
    carryForwardSubjectPlan as carryForwardSubjectPlanRoute,
    settings as subjectSettingsRoute,
} from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/SubjectOverviewJsonUploadController'

const Timetable = defineAsyncComponent(() => import('./timetable/Timetable.vue'))
const TimetableV3 = defineAsyncComponent(() => import('./timetableV3/TimetableV3.vue'))
const TestsV3 = defineAsyncComponent(() => import('./testsV3/TestsV3.vue'))
const TtEntries = defineAsyncComponent(() => import('./ttEntries/TtEntries.vue'))
const Import = defineAsyncComponent(() => import('./import/Import.vue'))
const SubjectsOverview = defineAsyncComponent(() => import('./subjectsOverview/SubjectsOverview.vue'))
const StudentsTimetablesTeachers = defineAsyncComponent(
    () => import('@/pages/admin/settings/components/StudentsTimetablesTeachers.vue'),
)

const TIMETABLE_OVERVIEW_PATH = '/admin/students-timetables/timetable/overview'
const TIMETABLE_V3_OVERVIEW_PATH = '/admin/students-timetables/timetable-v3/overview'
const TT_ENTRIES_OVERVIEW_PATH = '/admin/students-timetables/tt-entries/overview'
const TESTS_V3_STUDENTS_PATH = '/admin/students-timetables/tests-v3/students'
const TEACHERS_OVERVIEW_PATH = '/admin/students-timetables/teachers/overview'
const AUTOMATIC_TIMETABLE_OVERVIEW_PATH = `${TIMETABLE_OVERVIEW_PATH}/automatic`
const PERSONAL_SCHOOLYEAR_SCOPE = 'personal'
const mainSectionKeys = [
    'timetable',
    'timetable-v3',
    'tt-entries',
    'tests-v3',
    'subjects-overview',
    'teachers',
    'import',
]

export default {
    components: {
        AdminSectionHero,
        Timetable,
        TimetableV3,
        TestsV3,
        TtEntries,
        Import,
        SubjectsOverview,
        StudentsTimetablesTeachers,
    },
    data() {
        return {
            main_action: 'timetable-v3',
            subjectPlanCarryForwardDialog: false,
            subjectPlanCarryForwardDismissed: false,
            subjectPlanCarryForwardError: '',
            subjectPlanCarryForwardLoading: false,
            subjectPlanCarryForwardMessage: '',
            subjectPlanPreviousSchoolyear: null,
            subjectPlanRevision: 0,
            subjectPlanStatusError: '',
            subjectPlanStatusLoading: false,
        }
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        automaticTimetableRouteActive() {
            return this.$route.path === AUTOMATIC_TIMETABLE_OVERVIEW_PATH
                || this.$route.path.startsWith(`${AUTOMATIC_TIMETABLE_OVERVIEW_PATH}/`)
        },
        headerChips() {
            const chips = []
            const section = this.navigationItems.find((item) => item.key === this.activeNavigationKey)
            if (section?.roles) {
                section.roles.forEach((role) => {
                    chips.push({ key: `role-${role}`, text: role, icon: 'mdi-shield-account-outline' })
                })
            }
            return chips
        },
        navigationItems() {
            return this.allNavigationItems.filter(item => this.canAccessNavigationItem(item))
        },
        allNavigationItems() {
            return [
                {
                    key: 'timetable-v3',
                    label: 'Stundenplan',
                    meta: 'Entwicklung',
                    icon: 'mdi-flask-outline',
                    roles: ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'],
                },
                {
                    key: 'tests-v3',
                    label: 'Tests',
                    meta: 'Stundenplan v3',
                    icon: 'mdi-test-tube',
                    roles: ['super_admin', 'admin', 'studentstimetables_admin'],
                },
                {
                    key: 'tt-entries',
                    label: 'TT-Einträge',
                    meta: 'Kurse',
                    icon: 'mdi-format-list-bulleted-square',
                    roles: ['super_admin', 'admin', 'studentstimetables_admin'],
                },
                {
                    key: 'imports',
                    label: 'Importe',
                    meta: 'Stundenplan',
                    icon: 'mdi-import',
                    roles: ['super_admin', 'admin', 'studentstimetables_admin'],
                },
                {
                    key: 'subjects-overview',
                    label: 'Fächer',
                    meta: 'Überblick',
                    icon: 'mdi-book-open-page-variant-outline',
                    roles: ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'],
                },
                {
                    key: 'teachers',
                    label: 'Lehrer:innen',
                    meta: 'Verwaltung',
                    icon: 'mdi-account-tie-outline',
                    roles: ['super_admin', 'admin', 'studentstimetables_admin'],
                },
            ]
        },
        activeNavigationKey() {
            if (
                this.canManageStudentsTimetables
                && this.$route.params.section === 'timetable'
                && this.$route.params.subsection === 'imports'
            ) {
                return 'imports'
            }

            return this.main_action
        },
        configuredRoleNames() {
            return Array.isArray(this.config?.roles) ? this.config.roles : []
        },
        activeTimetableVersion() {
            return 'v3'
        },
        activeTimetableKey() {
            return `timetable-${this.activeTimetableVersion}`
        },
        activeTimetablePath() {
            return TIMETABLE_V3_OVERVIEW_PATH
        },
        canManageStudentsTimetables() {
            return this.hasAnyRole(['super_admin', 'admin', 'studentstimetables_admin'])
        },
        personalSchoolyearLabel() {
            return this.config?.selected_schoolyear?.concerns
                || this.config?.selected_schoolyear?.name
                || 'nicht festgelegt'
        },
        personalSchoolyearRouteOptions() {
            return {
                query: {
                    schoolyear_scope: PERSONAL_SCHOOLYEAR_SCOPE,
                },
            }
        },
        shouldOfferSubjectPlanCarryForward() {
            return !this.subjectPlanStatusLoading && Boolean(this.subjectPlanPreviousSchoolyear)
        },
        activeSection() {
            const sections = {
                timetable: {
                    label: 'Stundenplan',
                    icon: 'mdi-calendar-clock-outline',
                    note: 'Stundenplan Center.',
                },
                imports: {
                    label: 'Importe',
                    icon: 'mdi-import',
                    note: 'Stundenplan-Importe.',
                },
                'timetable-v3': {
                    label: 'Stundenplan',
                    icon: 'mdi-flask-outline',
                    note: 'Unabhängiger Entwicklungsbereich.',
                },
                'tests-v3': {
                    label: 'Tests',
                    icon: 'mdi-test-tube',
                    note: 'Tests für Stundenplan Version 3.',
                },
                'tt-entries': {
                    label: 'TT-Einträge',
                    icon: 'mdi-format-list-bulleted-square',
                    note: 'Meta-Kurse und TT-Einträge.',
                },
                import: {
                    label: 'Stundenplan',
                    icon: 'mdi-upload',
                    note: 'Stundenplan importieren.',
                },
                'subjects-overview': {
                    label: 'Fächer',
                    icon: 'mdi-book-open-page-variant-outline',
                    note: 'Fächer, Import und Zuordnung.',
                },
                teachers: {
                    label: 'Lehrer:innen',
                    icon: 'mdi-account-tie-outline',
                    note: 'Lehrerliste verwalten.',
                },
            }
            return sections[this.activeNavigationKey] || sections[this.activeTimetableKey]
        },
    },
    created() {
        const section = this.$route.params.section
        if (this.redirectMissingSection()) {
            return
        }

        if (this.redirectLegacySection(section)) {
            return
        }

        if (section && mainSectionKeys.includes(section)) {
            this.main_action = section
        }
        this.redirectUnauthorizedSection()
    },
    mounted() {
        this.loadSubjectPlanCarryForwardStatus()
    },
    watch: {
        'config.selected_schoolyear.id'() {
            this.resetSubjectPlanCarryForwardPrompt()
            this.loadSubjectPlanCarryForwardStatus()
        },
        '$route.params.section'(section) {
            if (this.redirectMissingSection()) {
                return
            }

            if (this.redirectLegacySection(section)) {
                return
            }

            if (section && mainSectionKeys.includes(section)) {
                this.main_action = section
                this.redirectUnauthorizedSection()

                return
            }

            this.main_action = this.activeTimetableKey
            this.redirectUnauthorizedSection()
        },
        '$route.params.subsection'() {
            this.redirectUnauthorizedSection()
        },
    },
    methods: {
        async loadSubjectPlanCarryForwardStatus() {
            this.subjectPlanStatusLoading = true
            this.subjectPlanStatusError = ''

            try {
                const response = await axios.get(subjectSettingsRoute.url(
                    undefined,
                    this.personalSchoolyearRouteOptions,
                ))

                this.subjectPlanPreviousSchoolyear = response.data?.data?.previous_schoolyear || null
            } catch {
                this.subjectPlanPreviousSchoolyear = null
                this.subjectPlanStatusError = 'Es konnte nicht geprüft werden, ob der Soll-/Fachplan übernommen werden muss.'
            } finally {
                this.subjectPlanStatusLoading = false

                if (
                    this.shouldOfferSubjectPlanCarryForward
                    && this.canManageStudentsTimetables
                    && !this.subjectPlanCarryForwardDismissed
                ) {
                    this.subjectPlanCarryForwardDialog = true
                }
            }
        },
        openSubjectPlanCarryForwardDialog() {
            this.subjectPlanCarryForwardError = ''
            this.subjectPlanCarryForwardDialog = true
        },
        dismissSubjectPlanCarryForwardDialog() {
            this.subjectPlanCarryForwardDismissed = true
            this.subjectPlanCarryForwardDialog = false
        },
        resetSubjectPlanCarryForwardPrompt() {
            this.subjectPlanCarryForwardDialog = false
            this.subjectPlanCarryForwardDismissed = false
            this.subjectPlanCarryForwardError = ''
            this.subjectPlanCarryForwardMessage = ''
            this.subjectPlanPreviousSchoolyear = null
            this.subjectPlanStatusError = ''
        },
        async carryForwardSubjectPlan() {
            if (
                !this.subjectPlanPreviousSchoolyear
                || this.subjectPlanCarryForwardLoading
                || !this.canManageStudentsTimetables
            ) {
                return
            }

            this.subjectPlanCarryForwardLoading = true
            this.subjectPlanCarryForwardError = ''
            this.subjectPlanCarryForwardMessage = ''

            try {
                const response = await axios.post(carryForwardSubjectPlanRoute.url(
                    this.personalSchoolyearRouteOptions,
                ))

                this.subjectPlanCarryForwardDialog = false
                this.subjectPlanPreviousSchoolyear = null
                this.subjectPlanCarryForwardMessage = response.data.message
                    || 'Der Soll-/Fachplan aus dem Vorjahr wurde übernommen.'
                this.subjectPlanRevision++
                await this.loadSubjectPlanCarryForwardStatus()
            } catch (error) {
                this.subjectPlanCarryForwardError = error?.response?.data?.message
                    || 'Der Soll-/Fachplan konnte nicht übernommen werden.'
            } finally {
                this.subjectPlanCarryForwardLoading = false
            }
        },
        hasAnyRole(roleNames) {
            return roleNames.some(roleName => this.configuredRoleNames.includes(roleName))
        },
        canAccessNavigationItem(item) {
            return this.hasAnyRole(item.roles || [])
        },
        redirectMissingSection() {
            if (this.$route.params.section) return false

            this.main_action = this.activeTimetableKey
            this.$router.replace({ path: this.activeTimetablePath })

            return true
        },
        redirectUnauthorizedSection() {
            if (
                (
                    (
                        this.$route.params.section === 'timetable'
                        && this.$route.params.subsection === 'imports'
                    )
                    || this.$route.params.section === 'tt-entries'
                    || this.$route.params.section === 'tests-v3'
                    || this.$route.params.section === 'teachers'
                )
                && !this.canManageStudentsTimetables
            ) {
                this.main_action = this.activeTimetableKey
                this.$router.replace({ path: this.activeTimetablePath })
            }
        },
        redirectLegacySection(section) {
            if (section === 'timetable-v2') {
                this.main_action = 'timetable-v3'
                this.$router.replace({ path: TIMETABLE_V3_OVERVIEW_PATH })

                return true
            }

            if (section === 'overview') {
                this.main_action = 'timetable'
                this.$router.replace({ path: TIMETABLE_OVERVIEW_PATH })

                return true
            }

            if (section === 'robot') {
                this.main_action = 'timetable'
                this.$router.replace({ path: AUTOMATIC_TIMETABLE_OVERVIEW_PATH })

                return true
            }

            return false
        },
        handleNavigation(key) {
            this.main_action = ['automatic-timetable', 'imports'].includes(key) ? 'timetable' : key
            const paths = {
                timetable: TIMETABLE_OVERVIEW_PATH,
                'timetable-v3': TIMETABLE_V3_OVERVIEW_PATH,
                'tt-entries': TT_ENTRIES_OVERVIEW_PATH,
                'tests-v3': TESTS_V3_STUDENTS_PATH,
                teachers: TEACHERS_OVERVIEW_PATH,
                'automatic-timetable': AUTOMATIC_TIMETABLE_OVERVIEW_PATH,
                imports: '/admin/students-timetables/timetable/imports',
                import: '/admin/students-timetables/import/overview',
                'subjects-overview': '/admin/students-timetables/subjects-overview/subject-plan-v2',
            }
            const path = paths[key] || `/admin/students-timetables/${key}`

            this.$router.push({ path })
        },
    },
}
</script>

<style scoped>
.students-timetables-page {
    --schedule-accent: #4f46e5;
    --schedule-accent-dark: #4338ca;
    --schedule-border: #e7e9ef;
    --schedule-heading: #1e2433;
    --schedule-muted: #8991a3;
    background: #eef0f3;
    min-height: 100vh;
    font-family: inherit;
}

.students-timetables-shell {
    max-width: 1760px;
    margin: 0 auto;
    overflow: hidden;
    border: 1px solid rgba(30, 36, 51, 0.06);
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(20, 20, 40, 0.08), 0 18px 48px rgba(30, 36, 51, 0.08);
}

.students-timetables-hero {
    border: 0 !important;
    border-radius: 0 !important;
    background: #1e2433 !important;
}

.students-timetables-hero :deep(.admin-section-hero__bg-orb) {
    display: none;
}

.students-timetables-hero :deep(.admin-section-hero__eyebrow) {
    color: #9aa3b5;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    opacity: 1;
}

.students-timetables-hero :deep(.admin-section-hero__title) {
    margin-top: 6px;
    font-size: clamp(1.55rem, 2.2vw, 1.75rem);
    font-weight: 800;
}

.students-timetables-hero :deep(.admin-section-hero__chips) {
    margin-top: 14px;
}

.students-timetables-hero :deep(.admin-section-hero__focus-card) {
    border-color: rgba(255, 255, 255, 0.12);
    border-radius: 12px !important;
    background: rgba(255, 255, 255, 0.06) !important;
    box-shadow: none !important;
}

.students-timetables-hero :deep(.v-chip) {
    background: rgba(255, 255, 255, 0.08) !important;
    color: #c7cdda !important;
    box-shadow: none !important;
}

.st-nav {
    border-bottom: 1px solid var(--schedule-border);
    border-radius: 0 !important;
    background: #ffffff;
    box-shadow: none;
    padding: 16px 32px 0;
    display: flex;
    align-items: flex-end;
    gap: 8px;
}

.st-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    flex: 1;
}

.st-nav__button {
    min-height: 44px !important;
    border-radius: 10px 10px 0 0 !important;
    padding: 0 18px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
    border: 0 !important;
    border-bottom: 3px solid transparent !important;
    color: #5b6472 !important;
    transition: color 0.18s ease, background 0.18s ease, border-color 0.18s ease !important;
}

.st-nav__button--idle {
    background: transparent !important;
    box-shadow: none !important;
}

.st-nav__button--idle:hover {
    background: #f7f8fb !important;
    color: var(--schedule-accent-dark) !important;
}

.st-nav__button--active {
    border-bottom-color: var(--schedule-accent) !important;
    background: #eef1ff !important;
    color: var(--schedule-accent) !important;
    box-shadow: none !important;
}

.st-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.st-nav__button-title {
    font-weight: 700;
    font-size: 0.88rem;
}

.students-timetables-content {
    margin: 0 !important;
    padding: 20px 24px 32px;
}

.subject-plan-carry-forward-warning {
    border: 2px solid rgba(245, 158, 11, 0.55);
    box-shadow: 0 10px 24px rgba(120, 53, 15, 0.12);
}

.subject-plan-carry-forward-warning__content {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 4px 2px;
}

.subject-plan-carry-forward-warning__copy {
    display: grid;
    flex: 1 1 560px;
    gap: 6px;
    color: #713f12;
}

.subject-plan-carry-forward-warning__copy strong {
    font-size: 1.08rem;
    font-weight: 900;
}

.subject-plan-carry-forward-warning__admin-note {
    color: #713f12;
}

@media (max-width: 700px) {
    .students-timetables-page {
        padding-inline: 0 !important;
    }

    .students-timetables-shell {
        border-radius: 0;
    }

    .students-timetables-page > :deep(.v-row) {
        margin-inline: 0 !important;
    }

    .students-timetables-page > :deep(.v-row > .v-col) {
        padding-inline: 0 !important;
    }
}

@media (max-width: 640px) {
    .st-nav {
        padding: 10px 12px 0;
    }

    .st-nav__buttons {
        gap: 6px;
    }

    .st-nav__button {
        flex: 1 1 auto;
        min-height: 38px !important;
        padding: 0 12px;
    }

    .st-nav__button-title {
        font-size: 0.84rem;
    }

    .students-timetables-content {
        padding: 12px 6px 22px;
    }
}
</style>
