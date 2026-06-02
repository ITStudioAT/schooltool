<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <StudentTimetablesNavigationDrawer v-model="showDrawer" current-route="overview" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/')">Zur Startseite</v-btn>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">Schülerstundenpläne</h1>
                <p class="hero-subtitle">Ihr Stundenplanbereich.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                    <span v-if="user?.schoolclass" class="hero-badge dark">{{ user.schoolclass }}</span>
                </div>

                <div class="hero-logout-row">
                    <v-btn class="logout-btn" variant="text" prepend-icon="mdi-logout" @click="handleLogout">Abmelden</v-btn>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <div class="content-card">
                <div class="content-head">
                    <v-icon size="26">mdi-calendar-clock-outline</v-icon>
                    <h2>Ihre Daten</h2>
                </div>

                <div class="selection-grid">
                    <div v-for="item in selectionItems" :key="item.key" class="selection-item">
                        <span>{{ item.label }}</span>
                        <strong>{{ item.value || '-' }}</strong>
                    </div>
                </div>

                <div v-if="!showEvaluationSettings" class="timetable-actions">
                    <v-btn
                        class="timetable-actions__automatic"
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-auto-fix"
                        rounded="pill"
                        @click="showEvaluationSettings = true">
                        <span>Automatischer Stundenplan</span>
                        <span class="timetable-actions__stars" aria-hidden="true">
                            <v-icon icon="mdi-star-four-points" size="10" class="timetable-star timetable-star--1" />
                            <v-icon icon="mdi-star-four-points" size="14" class="timetable-star timetable-star--2" />
                            <v-icon icon="mdi-star-four-points" size="8" class="timetable-star timetable-star--3" />
                        </span>
                    </v-btn>
                    <v-btn color="secondary" variant="outlined" prepend-icon="mdi-calendar-edit" rounded="pill">
                        Manueller Stundenplan
                    </v-btn>
                </div>

                <StudentTimetableEvaluationSettings
                    v-if="showEvaluationSettings"
                    @close="showEvaluationSettings = false" />

                <v-expansion-panels v-model="expandedCourseSections" class="summary-grid summary-panels" multiple flat>
                    <v-expansion-panel v-for="section in courseSections" :key="section.key" :value="section.key" class="summary-panel">
                        <v-expansion-panel-title class="summary-head">
                            <v-icon size="22">{{ section.icon }}</v-icon>
                            <h3>{{ section.title }}</h3>
                            <v-chip size="small" variant="flat" :color="section.color">{{ section.items.length }}</v-chip>
                        </v-expansion-panel-title>

                        <v-expansion-panel-text>
                            <div v-if="section.items.length" class="course-list">
                                <div v-for="course in section.items" :key="courseKey(section.key, course)" class="course-row">
                                    <div>
                                        <strong>{{ course.code || course.name || '-' }}</strong>
                                        <span v-if="course.name && course.name !== course.code">{{ course.name }}</span>
                                    </div>
                                    <div class="course-meta">
                                        <v-chip v-if="courseHoursLabel(course)" size="x-small" color="primary" variant="tonal">
                                            {{ courseHoursLabel(course) }}
                                        </v-chip>
                                        <v-chip v-if="course.semester" size="x-small" variant="tonal">S{{ course.semester }}</v-chip>
                                        <v-chip v-if="course.grade" size="x-small" color="success" variant="tonal">{{ course.grade }}</v-chip>
                                        <v-chip v-if="course.branch && course.branch !== 'common'" size="x-small" variant="tonal">{{ course.branch }}</v-chip>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="empty-state">
                                <v-icon size="20">mdi-information-outline</v-icon>
                                <span>{{ section.empty }}</span>
                            </div>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>
            </div>
        </section>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentTimetablesUserStore } from '@/stores/studentsTimetables/StudentTimetablesUserStore'
import StudentTimetableEvaluationSettings from '../components/StudentTimetableEvaluationSettings.vue'
import StudentTimetablesNavigationDrawer from '../components/StudentTimetablesNavigationDrawer.vue'
import '../../../../../css/student.css'

export default {
    components: {
        StudentTimetableEvaluationSettings,
        StudentTimetablesNavigationDrawer,
    },

    async beforeMount() {
        this.studentTimetablesStore = useStudentTimetablesUserStore()
        const isAuthenticated = await this.studentTimetablesStore.getCurrentUser()

        if (!isAuthenticated || !this.user) {
            this.$router.push('/homepage/students-timetables')
            return
        }

        await this.studentTimetablesStore.loadOverview()
    },

    data() {
        return {
            studentTimetablesStore: null,
            showDrawer: false,
            showEvaluationSettings: false,
            expandedCourseSections: [],
        }
    },

    computed: {
        ...mapWritableState(useStudentTimetablesUserStore, ['user', 'overview']),

        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },
        selectionItems() {
            const selection = this.overview?.selection || {}

            return [
                { key: 'semester', label: 'Semester', value: selection.semester },
                { key: 'religion', label: 'ETH/Religion', value: selection.religion },
                { key: 'language', label: 'Sprache', value: selection.language },
                { key: 'branch', label: 'Zweig', value: selection.branch },
                { key: 'arts', label: 'ME/BE', value: selection.arts_subject },
            ]
        },
        courseSections() {
            return [
                {
                    key: 'completed',
                    title: 'Abgeschlossene Kurse',
                    icon: 'mdi-check-circle-outline',
                    color: 'success',
                    items: this.overview?.completed_courses || [],
                    empty: 'Keine abgeschlossenen Kurse gefunden.',
                },
                {
                    key: 'missing',
                    title: 'Fehlende Kurse',
                    icon: 'mdi-alert-circle-outline',
                    color: 'warning',
                    items: this.overview?.missing_courses || [],
                    empty: 'Keine fehlenden Kurse erkannt.',
                },
                {
                    key: 'proposed',
                    title: 'Vorgesehene Kurse',
                    icon: 'mdi-format-list-checks',
                    color: 'primary',
                    items: this.overview?.proposed_courses || [],
                    empty: 'Keine vorgesehenen Kurse importiert.',
                },
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    icon: 'mdi-plus-circle-outline',
                    color: 'info',
                    items: this.overview?.additional_courses || [],
                    empty: 'Keine zusätzlichen Kurse erkannt.',
                },
            ]
        },
    },

    methods: {
        async handleLogout() {
            this.showDrawer = false
            await this.studentTimetablesStore.logout()
            this.$router.push('/homepage/students-timetables')
        },
        courseKey(sectionKey, course) {
            return [sectionKey, course.code || '', course.name || '', course.semester || '', course.grade || ''].join('|')
        },
        courseHoursLabel(course) {
            const hours = course.hours ?? course.hours_per_week

            if (hours === null || hours === undefined || hours === '') {
                return null
            }

            const numericHours = Number(hours)

            if (!Number.isFinite(numericHours)) {
                return null
            }

            return `${new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(numericHours)} Std.`
        },
    },
}
</script>

<style scoped>
.logout-btn {
    color: var(--charcoal);
    font-weight: 700;
    border-radius: 999px;
}

.hero-logout-row {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
}

.selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.selection-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px 14px;
    border-radius: 8px;
    background: rgba(16, 38, 58, 0.05);
    color: #243748;
}

.selection-item span {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    color: rgba(36, 55, 72, 0.68);
}

.selection-item strong {
    font-size: 1.05rem;
}

.timetable-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 20px;
}

.timetable-actions .v-btn {
    min-height: 44px;
    flex: 1 1 240px;
}

.timetable-actions__automatic {
    overflow: hidden;
}

.timetable-actions__stars {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: 2px;
}

.timetable-star {
    color: rgba(255, 255, 255, 0.9);
    animation: timetable-star-twinkle 1.8s ease-in-out infinite;
}

.timetable-star--1 {
    animation-delay: 0s;
}

.timetable-star--2 {
    animation-delay: 0.5s;
}

.timetable-star--3 {
    animation-delay: 1.1s;
}

@keyframes timetable-star-twinkle {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.8);
    }

    50% {
        opacity: 1;
        transform: scale(1.2);
    }
}

.summary-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    align-items: start;
}

@media (min-width: 900px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 520px) {
    .timetable-actions .v-btn {
        width: 100%;
    }
}

.summary-panels :deep(.v-expansion-panel) {
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px !important;
    background: rgba(255, 255, 255, 0.84) !important;
    margin-top: 0 !important;
    overflow: hidden;
}

.summary-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.07);
    min-height: 58px;
}

.summary-head h3 {
    flex: 1;
    margin: 0;
    font-size: 1rem;
    color: #10263a;
}

.summary-panels :deep(.v-expansion-panel-text__wrapper) {
    padding: 0;
}

.course-list {
    display: grid;
}

.course-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.06);
}

.course-row:last-child {
    border-bottom: 0;
}

.course-row strong,
.course-row span {
    display: block;
}

.course-row strong {
    color: #172d40;
}

.course-row span {
    margin-top: 2px;
    color: rgba(23, 45, 64, 0.72);
    font-size: 0.86rem;
}

.course-meta {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    gap: 6px;
    flex-wrap: wrap;
    min-width: 72px;
}

.empty-state {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 18px 14px;
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.92rem;
}
</style>
