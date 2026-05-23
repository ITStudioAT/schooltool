<template>
    <v-container fluid class="students-timetables-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Stundenpläne"
            title="Schülerstundenpläne"
            :active-section="activeSection"
            :chips="headerChips">
            <template #chips>
                <v-menu>
                    <template #activator="{ props }">
                        <v-chip
                            v-bind="props"
                            size="small"
                            variant="flat"
                            color="light-blue-lighten-3"
                            prepend-icon="mdi-calendar-month-outline"
                            append-icon="mdi-menu-down"
                            style="cursor: pointer">
                            {{ selectedSchoolyearLabel }}
                        </v-chip>
                    </template>
                    <v-list density="compact" max-height="300">
                        <v-list-item
                            v-for="sy in schoolyears"
                            :key="sy.id"
                            :active="sy.id === config?.selected_schoolyear?.id"
                            @click="switchSchoolyear(sy.id)">
                            <v-list-item-title>{{ sy.name }}</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
            </template>
        </AdminSectionHero>

        <v-sheet rounded="xl" class="st-nav mb-2">
            <div class="st-nav__buttons">
                <v-btn
                    v-for="item in navigationItems"
                    :key="item.key"
                    rounded="xl"
                    :color="activeNavigationKey === item.key ? 'primary' : 'secondary'"
                    :variant="activeNavigationKey === item.key ? 'flat' : 'tonal'"
                    class="st-nav__button"
                    :class="activeNavigationKey === item.key ? 'st-nav__button--active' : 'st-nav__button--idle'"
                    @click="handleNavigation(item.key)">
                    <v-icon size="18" :icon="item.icon" class="mr-2" />
                    <span class="st-nav__button-copy">
                        <span class="st-nav__button-title">{{ item.label }}</span>
                        <span class="st-nav__button-meta">{{ item.meta }}</span>
                    </span>
                </v-btn>
            </div>
        </v-sheet>

        <v-row class="w-100" dense>
            <Timetable v-if="main_action === 'timetable'" />
            <Import v-if="main_action === 'import'" />
            <SubjectsOverview v-if="main_action === 'subjects-overview'" />
        </v-row>
    </v-container>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'

const Timetable = defineAsyncComponent(() => import('./timetable/Timetable.vue'))
const Import = defineAsyncComponent(() => import('./import/Import.vue'))
const SubjectsOverview = defineAsyncComponent(() => import('./subjectsOverview/SubjectsOverview.vue'))

const mainSectionKeys = ['timetable', 'subjects-overview', 'import']

export default {
    components: {
        AdminSectionHero,
        Timetable,
        Import,
        SubjectsOverview,
    },
    data() {
        return {
            main_action: 'timetable',
        }
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        selectedSchoolyearLabel() {
            return this.config?.selected_schoolyear?.name || 'Kein Schuljahr gewählt'
        },
        schoolyears() {
            return this.schoolyearStore?.schoolyears || []
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
                    key: 'timetable',
                    label: 'Stundenplan',
                    meta: 'Center',
                    icon: 'mdi-calendar-clock-outline',
                    roles: ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'],
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
            return sections[this.activeNavigationKey] || sections.timetable
        },
    },
    created() {
        this.schoolyearStore = useSchoolyearStore()
        this.schoolyearStore.index()
        const section = this.$route.params.section
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
            if (this.redirectLegacySection(section)) {
                return
            }

            if (section && mainSectionKeys.includes(section)) {
                this.main_action = section

                return
            }

            this.main_action = 'timetable'
            this.redirectUnauthorizedSection()
        },
        '$route.params.subsection'() {
            this.redirectUnauthorizedSection()
        },
    },
    methods: {
        hasAnyRole(roleNames) {
            return roleNames.some(roleName => this.configuredRoleNames.includes(roleName))
        },
        canAccessNavigationItem(item) {
            return this.hasAnyRole(item.roles || [])
        },
        redirectUnauthorizedSection() {
            if (this.$route.params.section === 'timetable' && this.$route.params.subsection === 'imports' && !this.canManageStudentsTimetables) {
                this.main_action = 'timetable'
                this.$router.replace({ path: '/admin/students-timetables/timetable/overview' })
            }
        },
        redirectLegacySection(section) {
            if (section === 'overview') {
                this.main_action = 'timetable'
                this.$router.replace({ path: '/admin/students-timetables/timetable/overview' })

                return true
            }

            if (section === 'robot') {
                this.main_action = 'timetable'
                this.$router.replace({ path: '/admin/students-timetables/timetable/robot' })

                return true
            }

            return false
        },
        handleNavigation(key) {
            this.main_action = key === 'imports' ? 'timetable' : key
            const paths = {
                timetable: '/admin/students-timetables/timetable/overview',
                imports: '/admin/students-timetables/timetable/imports',
                import: '/admin/students-timetables/import/overview',
                'subjects-overview': '/admin/students-timetables/subjects-overview/subject-plan',
            }
            const path = paths[key] || `/admin/students-timetables/${key}`

            this.$router.replace({ path })
        },
        async switchSchoolyear(id) {
            await this.schoolyearStore.setActiveSchoolyear(id)
            const adminStore = useAdminStore()
            await adminStore.loadConfig()
        },
    },
}
</script>

<style scoped>
.students-timetables-page {
    background: linear-gradient(180deg, #f1f6fd 0%, #e8f1fb 100%);
    min-height: 100vh;
}

.st-nav {
    border: 1px solid rgba(37, 99, 235, 0.16);
    background: rgba(255, 255, 255, 0.86);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.07);
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.st-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    flex: 1;
}

.st-nav__button {
    height: 40px !important;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
    border: 1px solid transparent !important;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease !important;
}

.st-nav__button--idle {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(219, 234, 254, 0.92) 100%) !important;
    color: #1e3a8a !important;
    border-color: rgba(37, 99, 235, 0.2) !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.86), 0 6px 14px rgba(148, 163, 184, 0.12) !important;
}

.st-nav__button--idle:hover {
    background: linear-gradient(180deg, rgba(239, 246, 255, 1) 0%, rgba(191, 219, 254, 0.98) 100%) !important;
    color: #1d4ed8 !important;
    border-color: rgba(37, 99, 235, 0.28) !important;
}

.st-nav__button--active {
    background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%) !important;
    color: #ffffff !important;
    border-color: rgba(30, 64, 175, 0.5) !important;
    box-shadow: 0 12px 22px rgba(37, 99, 235, 0.24) !important;
}

.st-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.st-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.st-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.9;
}

.st-nav__button--idle .st-nav__button-meta {
    color: rgba(30, 64, 175, 0.9);
}

.st-nav__button--active .st-nav__button-meta {
    color: rgba(255, 255, 255, 0.92);
}
</style>
