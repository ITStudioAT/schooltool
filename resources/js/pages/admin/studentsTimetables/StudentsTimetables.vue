<template>
    <v-container fluid class="students-timetables-page ma-0 w-100 pa-2">
        <div class="students-timetables-shell">
            <AdminSectionHero
                class="students-timetables-hero"
                eyebrow="Stundenpläne"
                title="Schülerstundenpläne"
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
                        :class="[
                            activeNavigationKey === item.key ? 'st-nav__button--active' : 'st-nav__button--idle',
                            { 'st-nav__button--legacy': item.key === 'timetable-v2' },
                        ]"
                        @click="handleNavigation(item.key)">
                        <span class="st-nav__button-copy">
                            <span class="st-nav__button-title">{{ item.label }}</span>
                        </span>
                    </v-btn>
                </div>

                <v-btn
                    class="st-nav__settings-button"
                    icon="mdi-cog"
                    size="small"
                    variant="text"
                    title="Einstellungen"
                    aria-label="Einstellungen"
                    to="/admin/settings?tab=students_timetables" />
            </v-sheet>

            <v-row class="students-timetables-content w-100" dense>
                <Timetable v-if="main_action === 'timetable'" />
                <v-col v-if="main_action === 'timetable-v2'" cols="12">
                    <TimetableV2 />
                </v-col>
                <v-col v-if="main_action === 'timetable-v3'" cols="12">
                    <TimetableV3 />
                </v-col>
                <v-col v-if="main_action === 'tt-entries'" cols="12">
                    <TtEntries />
                </v-col>
                <Import v-if="main_action === 'import'" />
                <SubjectsOverview v-if="main_action === 'subjects-overview'" />
            </v-row>
        </div>
    </v-container>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'

const Timetable = defineAsyncComponent(() => import('./timetable/Timetable.vue'))
const TimetableV2 = defineAsyncComponent(() => import('./timetableV2/TimetableV2.vue'))
const TimetableV3 = defineAsyncComponent(() => import('./timetableV3/TimetableV3.vue'))
const TtEntries = defineAsyncComponent(() => import('./ttEntries/TtEntries.vue'))
const Import = defineAsyncComponent(() => import('./import/Import.vue'))
const SubjectsOverview = defineAsyncComponent(() => import('./subjectsOverview/SubjectsOverview.vue'))

const TIMETABLE_OVERVIEW_PATH = '/admin/students-timetables/timetable/overview'
const TIMETABLE_V2_OVERVIEW_PATH = '/admin/students-timetables/timetable-v2/overview'
const TIMETABLE_V3_OVERVIEW_PATH = '/admin/students-timetables/timetable-v3/overview'
const TT_ENTRIES_OVERVIEW_PATH = '/admin/students-timetables/tt-entries/overview'
const AUTOMATIC_TIMETABLE_OVERVIEW_PATH = `${TIMETABLE_OVERVIEW_PATH}/automatic`
const mainSectionKeys = ['timetable', 'timetable-v2', 'timetable-v3', 'tt-entries', 'subjects-overview', 'import']

export default {
    components: {
        AdminSectionHero,
        Timetable,
        TimetableV2,
        TimetableV3,
        TtEntries,
        Import,
        SubjectsOverview,
    },
    data() {
        return {
            main_action: 'timetable-v3',
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
                    label: 'Stundenplan v3',
                    meta: 'Entwicklung',
                    icon: 'mdi-flask-outline',
                    roles: ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'],
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
                    key: 'timetable-v2',
                    label: 'Stundenplan v2',
                    meta: 'Stabil',
                    icon: 'mdi-calendar-edit-outline',
                    roles: ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'],
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
            return this.config?.students_timetables?.admin_version === 'v2' ? 'v2' : 'v3'
        },
        activeTimetableKey() {
            return `timetable-${this.activeTimetableVersion}`
        },
        activeTimetablePath() {
            return this.activeTimetableVersion === 'v3'
                ? TIMETABLE_V3_OVERVIEW_PATH
                : TIMETABLE_V2_OVERVIEW_PATH
        },
        canManageStudentsTimetables() {
            return this.hasAnyRole(['super_admin', 'admin', 'studentstimetables_admin'])
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
                'timetable-v2': {
                    label: 'Stundenplan v2',
                    icon: 'mdi-calendar-edit-outline',
                    note: 'Neue Stundenplan-Version.',
                },
                'timetable-v3': {
                    label: 'Stundenplan v3',
                    icon: 'mdi-flask-outline',
                    note: 'Unabhängiger Entwicklungsbereich.',
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
    watch: {
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
        activeTimetableVersion() {
            if (!this.$route.params.section) {
                this.redirectMissingSection()
            }
        },
    },
    methods: {
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
                )
                && !this.canManageStudentsTimetables
            ) {
                this.main_action = this.activeTimetableKey
                this.$router.replace({ path: this.activeTimetablePath })
            }
        },
        redirectLegacySection(section) {
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
                'timetable-v2': TIMETABLE_V2_OVERVIEW_PATH,
                'timetable-v3': TIMETABLE_V3_OVERVIEW_PATH,
                'tt-entries': TT_ENTRIES_OVERVIEW_PATH,
                'automatic-timetable': AUTOMATIC_TIMETABLE_OVERVIEW_PATH,
                imports: '/admin/students-timetables/timetable/imports',
                import: '/admin/students-timetables/import/overview',
                'subjects-overview': '/admin/students-timetables/subjects-overview/subject-plan',
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

.st-nav__settings-button {
    flex: 0 0 auto;
    margin-bottom: 7px;
    color: #5b6472 !important;
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

.st-nav__button--legacy {
    margin-left: auto;
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

    .st-nav__button--legacy {
        margin-left: 0;
    }

    .st-nav__settings-button {
        align-self: flex-start;
    }

    .st-nav__button-title {
        font-size: 0.84rem;
    }

    .students-timetables-content {
        padding: 12px 6px 22px;
    }
}
</style>
