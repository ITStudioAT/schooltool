<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Navigation Drawer -->
        <StudentNavigationDrawer v-model="showDrawer" current-route="course" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/student/overview')">Zurück zur Übersicht</v-btn>
                    <div class="chip-brand">Fach</div>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">{{ course?.title || 'Fach wird geladen...' }}</h1>
                <p class="hero-subtitle">Details zum Fach ansehen.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">{{ user.first_name }} {{ user.last_name }}</span>
                    <span v-if="user?.schoolclass" class="hero-badge dark">{{ user.schoolclass }}</span>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <!-- Course Details Card -->
            <div class="content-card">
                <div class="content-head">
                    <v-icon size="26">mdi-book-open-variant</v-icon>
                    <h2>Fach-Details</h2>
                    <v-btn class="ml-auto" variant="text" icon="mdi-close" @click="$router.push('/student/overview')" />
                </div>
                <!-- Loading State -->
                <div v-if="loading" class="courses-loading">
                    <v-progress-circular indeterminate color="#fd802e" />
                    <p>Lade Fach-Details...</p>
                </div>

                <!-- Course Tabs -->
                <div v-else-if="course">
                    <v-tabs v-model="currentTab" bg-color="transparent" color="#fd802e" grow>
                        <v-tab value="overview">
                            <v-icon start>mdi-information-outline</v-icon>
                            Übersicht
                        </v-tab>
                        <v-tab value="entries">
                            <v-icon start>mdi-notebook-outline</v-icon>
                            Einträge
                        </v-tab>
                        <v-tab value="grades">
                            <v-icon start>mdi-chart-line</v-icon>
                            Noten
                        </v-tab>
                        <v-tab value="materials">
                            <v-icon start>mdi-folder-outline</v-icon>
                            Materialien
                        </v-tab>
                    </v-tabs>

                    <v-tabs-window v-model="currentTab" style="margin-top: 20px;">
                        <!-- Übersicht Tab -->
                        <v-tabs-window-item value="overview">
                            <div class="profile-section">
                                <h3 class="profile-section-title">
                                    <v-icon size="20">mdi-information</v-icon>
                                    Allgemeine Informationen
                                </h3>
                                <div class="profile-fields">
                                    <div class="profile-field">
                                        <label>Fachbezeichnung</label>
                                        <div class="profile-value">{{ course.title }}</div>
                                    </div>
                                    <div class="profile-field" v-if="course.teacher">
                                        <label>Lehrkraft</label>
                                        <div class="profile-value">{{ course.teacher }}</div>
                                    </div>
                                    <div class="profile-field" v-if="course.teacher_email">
                                        <label>E-Mail Lehrkraft</label>
                                        <div class="profile-value">{{ course.teacher_email }}</div>
                                    </div>
                                    <div class="profile-field" v-if="course.classes && course.classes.length > 0">
                                        <label>Klassen</label>
                                        <div class="profile-value">{{ course.classes.join(', ') }}</div>
                                    </div>
                                    <div class="profile-field">
                                        <label>Anzahl Schüler</label>
                                        <div class="profile-value">{{ course.students_count }}</div>
                                    </div>
                                </div>
                            </div>
                        </v-tabs-window-item>

                        <!-- Einträge Tab -->
                        <v-tabs-window-item value="entries">
                            <!-- Loading State -->
                            <div v-if="loadingEntries" class="courses-loading">
                                <v-progress-circular indeterminate color="#fd802e" />
                                <p>Lade Einträge...</p>
                            </div>

                            <div v-else class="entries-section">
                                <div class="entries-toolbar">
                                    <div class="entries-toolbar-title">
                                        <v-icon size="18">mdi-clipboard-text</v-icon>
                                        <span>Einträge</span>
                                        <v-chip v-if="sortedEntries.length" size="x-small" color="primary" variant="tonal">
                                            {{ sortedEntries.length }}
                                        </v-chip>
                                    </div>
                                    <v-btn size="small" variant="tonal" color="primary" @click="toggleSortByType">
                                        {{ sortByType ? 'Sort: Typ' : 'Sort: Datum' }}
                                    </v-btn>
                                </div>

                                <div class="entries-semester-filter">
                                    <v-btn-toggle v-model="selectedSemester" mandatory density="compact" color="primary">
                                        <v-btn :value="1" size="small">1. Sem</v-btn>
                                        <v-btn :value="2" size="small">2. Sem</v-btn>
                                        <v-btn :value="3" size="small">1+2</v-btn>
                                    </v-btn-toggle>
                                </div>

                                <v-card v-if="sortedEntries.length > 0" variant="outlined">
                                    <v-card-text class="pa-0">
                                        <v-list density="comfortable">
                                            <template v-for="item in groupedEntries" :key="item.key">
                                                <v-list-item v-if="item.kind === 'header'">
                                                    <div class="entry-divider-row">
                                                        <v-divider />
                                                        <span class="entry-divider-label">{{ item.label }}</span>
                                                        <v-divider />
                                                    </div>
                                                </v-list-item>
                                                <v-list-item v-else>
                                                    <div class="entry-row" :class="item.stripe % 2 === 1 ? 'entry-row--alt' : 'entry-row--base'">
                                                        <v-icon size="22" :color="getEntryColor(item.entry.type)">{{ getEntryIcon(item.entry.type) }}</v-icon>
                                                        <v-chip v-if="item.entry.date" size="small" variant="tonal" color="primary">
                                                            {{ formatDate(item.entry.date) }}
                                                        </v-chip>
                                                        <v-chip v-if="item.entry.type" size="small" variant="outlined">
                                                            {{ entryTypeChipLabel(item.entry.type) }}
                                                        </v-chip>
                                                        <div class="entry-main text-caption">
                                                            <div v-if="entryTitle(item.entry)" class="entry-title">{{ entryTitle(item.entry) }}</div>
                                                            <div v-if="entryDescription(item.entry)" class="entry-description">
                                                                {{ entryDescription(item.entry) }}
                                                            </div>
                                                        </div>
                                                        <v-chip v-if="item.entry.grade" class="entry-grade" size="small" variant="tonal" color="success">
                                                            {{ item.entry.grade }}
                                                        </v-chip>
                                                    </div>
                                                </v-list-item>
                                            </template>
                                        </v-list>
                                    </v-card-text>
                                </v-card>

                                <!-- Empty State -->
                                <div v-else class="profile-info-box">
                                    <v-icon color="#fd802e" size="24">mdi-notebook-outline</v-icon>
                                    <div>
                                        <strong>Keine Einträge:</strong> Für dieses Fach sind noch keine Einträge vorhanden.
                                    </div>
                                </div>
                            </div>
                        </v-tabs-window-item>

                        <!-- Noten Tab -->
                        <v-tabs-window-item value="grades">
                            <div class="profile-info-box">
                                <v-icon color="#fd802e" size="24">mdi-chart-line</v-icon>
                                <div>
                                    <strong>Noten:</strong> Hier werden deine Noten und Leistungsübersicht angezeigt.
                                </div>
                            </div>
                        </v-tabs-window-item>

                        <!-- Materialien Tab -->
                        <v-tabs-window-item value="materials">
                            <div class="profile-info-box">
                                <v-icon color="#fd802e" size="24">mdi-folder-outline</v-icon>
                                <div>
                                    <strong>Materialien:</strong> Hier werden Dokumente, Links und Ressourcen angezeigt.
                                </div>
                            </div>
                        </v-tabs-window-item>
                    </v-tabs-window>
                </div>

                <!-- Empty State -->
                <div v-else class="courses-empty">
                    <v-icon size="64" color="#fd802e">mdi-alert-circle-outline</v-icon>
                    <h3>Fach nicht gefunden</h3>
                    <p>Das angeforderte Fach konnte nicht geladen werden.</p>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'
import { useCourseStore } from '@/stores/student/CourseStore'
import { parseLocalDate } from '@/helpers/date'
import StudentNavigationDrawer from '../../components/StudentNavigationDrawer.vue'
import '../../../../../../css/student.css'

export default {
    components: {
        StudentNavigationDrawer,
    },

    async beforeMount() {
        this.studentStore = useStudentStore()
        this.courseStore = useCourseStore()

        // Check authentication
        const isAuthenticated = await this.studentStore.getCurrentUser()
        if (!isAuthenticated || !this.user) {
            this.$router.push('/student')
            return
        }

        // Load course details
        await this.loadCourse()
    },

    data() {
        return {
            studentStore: null,
            courseStore: null,
            showDrawer: false,
            loading: false,
            course: null,
            currentTab: 'overview', // Start with Übersicht as default
            entries: [],
            loadingEntries: false,
            selectedSemester: 3, // 1 = Semester 1, 2 = Semester 2, 3 = Both
            sortByType: false,
        }
    },

    computed: {
        ...mapWritableState(useStudentStore, ['user']),

        courseId() {
            return this.$route.params.id
        },

        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },

        schemaWorkTypeLabels() {
            const schemas = Array.isArray(this.user?.teaching_schemas) ? this.user.teaching_schemas : []
            if (!schemas.length) return {}

            const courseSchemaId = this.course?.teaching_schema_id
            let schema = null

            if (courseSchemaId !== null && courseSchemaId !== undefined) {
                schema = schemas.find((item) => String(item?.id) === String(courseSchemaId)) || null
            }

            if (!schema) return {}

            const works = Array.isArray(schema.works) ? schema.works : []
            return works.reduce((result, work) => {
                if (work?.short_name) {
                    result[String(work.short_name)] = work?.name || String(work.short_name)
                }
                return result
            }, {})
        },

        // Get semester boundary (middle date between first and last entry, or Feb 1st)
        semesterBoundary() {
            if (this.entries.length < 2) {
                // Default to February 1st as semester boundary
                const now = new Date()
                const year = now.getMonth() >= 8 ? now.getFullYear() + 1 : now.getFullYear()
                return `${year}-02-01`
            }

            const dates = this.entries.map(e => this.normalizeDateKey(e.date)).filter(Boolean).sort((a, b) => a.localeCompare(b))
            if (dates.length < 2) {
                const now = new Date()
                const year = now.getMonth() >= 8 ? now.getFullYear() + 1 : now.getFullYear()
                return `${year}-02-01`
            }

            const first = parseLocalDate(dates[0])
            const last = parseLocalDate(dates[dates.length - 1])
            const middle = new Date((first.getTime() + last.getTime()) / 2)
            return this.normalizeDateKey(middle)
        },

        // Filter entries by selected semester
        filteredEntries() {
            if (this.selectedSemester === 3) {
                return this.entries
            }

            const boundary = this.normalizeDateKey(this.semesterBoundary)
            if (!boundary) return this.entries

            return this.entries.filter(entry => {
                if (!entry.date) return true
                const date = this.normalizeDateKey(entry.date)
                if (!date) return true
                if (this.selectedSemester === 1) {
                    return date < boundary
                } else if (this.selectedSemester === 2) {
                    return date >= boundary
                }
                return true
            })
        },

        sortedEntries() {
            const list = this.filteredEntries || []
            if (!this.sortByType) return list
            return [...list].sort((a, b) => {
                const typeA = (a.type || '').toString()
                const typeB = (b.type || '').toString()
                const typeCompare = typeA.localeCompare(typeB, 'de', { sensitivity: 'base' })
                if (typeCompare !== 0) return typeCompare
                const dateA = a.date ? parseLocalDate(a.date).getTime() : 0
                const dateB = b.date ? parseLocalDate(b.date).getTime() : 0
                return dateB - dateA
            })
        },

        groupedEntries() {
            const entries = this.sortedEntries
            if (this.selectedSemester !== 3) {
                return entries.map((entry, index) => ({
                    kind: 'entry',
                    key: `entry-${entry.id || index}`,
                    entry,
                    stripe: index,
                }))
            }

            const boundary = this.normalizeDateKey(this.semesterBoundary)
            if (!boundary) {
                return entries.map((entry, index) => ({
                    kind: 'entry',
                    key: `entry-${entry.id || index}`,
                    entry,
                    stripe: index,
                }))
            }

            const sem1 = entries.filter((entry) => {
                if (!entry.date) return true
                const date = this.normalizeDateKey(entry.date)
                return !date || date < boundary
            })
            const sem2 = entries.filter((entry) => {
                if (!entry.date) return false
                const date = this.normalizeDateKey(entry.date)
                return !!date && date >= boundary
            })

            const grouped = []
            let stripeIndex = 0
            grouped.push({ kind: 'header', key: 'header-sem2', label: '2. Semester' })
            sem2.forEach((entry, index) => {
                grouped.push({
                    kind: 'entry',
                    key: `sem2-entry-${entry.id || index}`,
                    entry,
                    stripe: stripeIndex,
                })
                stripeIndex += 1
            })
            grouped.push({ kind: 'header', key: 'header-sem1', label: '1. Semester' })
            sem1.forEach((entry, index) => {
                grouped.push({
                    kind: 'entry',
                    key: `sem1-entry-${entry.id || index}`,
                    entry,
                    stripe: stripeIndex,
                })
                stripeIndex += 1
            })
            return grouped
        },
    },

    watch: {
        currentTab(newTab) {
            // Load entries when switching to Einträge tab
            if (newTab === 'entries' && this.entries.length === 0 && !this.loadingEntries) {
                this.loadEntries()
            }
        },
    },

    methods: {
        toggleSortByType() {
            this.sortByType = !this.sortByType
        },

        normalizeDateKey(date) {
            if (!date) return ''
            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}/.test(date)) {
                return date.slice(0, 10)
            }
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) return ''
            const year = parsed.getFullYear()
            const month = String(parsed.getMonth() + 1).padStart(2, '0')
            const day = String(parsed.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },

        async loadCourse() {
            this.loading = true
            try {
                // Get courses from store
                await this.courseStore.getCourses()

                // Find the specific course by ID
                const courses = this.courseStore.courses || []
                this.course = courses.find(c => c.id === parseInt(this.courseId))

                if (!this.course) {
                    console.error('Course not found:', this.courseId)
                }
            } catch (error) {
                console.error('Error loading course:', error)
            } finally {
                this.loading = false
            }
        },

        async loadEntries() {
            if (this.loadingEntries) return
            this.loadingEntries = true
            try {
                const success = await this.courseStore.getCourseEntries(this.courseId)
                if (success) {
                    this.entries = this.courseStore.entries || []
                }
            } catch (error) {
                console.error('Error loading entries:', error)
            } finally {
                this.loadingEntries = false
            }
        },

        getEntryIcon(type) {
            const icons = {
                homework: 'mdi-pencil-box-outline',
                note: 'mdi-notebook-outline',
                activity: 'mdi-calendar-star',
                exam: 'mdi-file-document-outline',
                info: 'mdi-information-outline',
                TW: 'mdi-typewriter',
                A: 'mdi-clipboard-text',
                MA: 'mdi-calculator',
                PÜ: 'mdi-file-document-check',
            }
            return icons[type] || 'mdi-circle-small'
        },

        getEntryTypeLabel(type) {
            const apiLabel = this.courseStore?.typeLabels?.[type]
            if (apiLabel && apiLabel !== type) {
                return apiLabel
            }

            const schemaLabel = this.schemaWorkTypeLabels?.[type]
            if (schemaLabel) {
                return schemaLabel
            }

            if (apiLabel) {
                return apiLabel
            }

            // Fallback for generic types not in schema
            const fallbackLabels = {
                homework: 'Hausaufgabe',
                note: 'Notiz',
                activity: 'Aktivität',
                exam: 'Prüfung',
                info: 'Information',
            }
            return fallbackLabels[type] || type
        },

        entryTypeChipLabel(type) {
            if (!type) return ''
            return `${type} - ${this.getEntryTypeLabel(type)}`
        },

        normalizeText(value) {
            return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase()
        },

        entryTitle(entry) {
            const title = String(entry?.title || '').trim()
            if (!title) return ''

            const description = String(entry?.description || '').trim()
            const type = String(entry?.type || '').trim()
            const typeLabel = String(this.getEntryTypeLabel(entry?.type) || '').trim()
            const chipLabel = type && typeLabel ? `${type} - ${typeLabel}` : ''
            const normalizedTitle = this.normalizeText(title)

            const isTypeOnly =
                normalizedTitle === this.normalizeText(type) ||
                normalizedTitle === this.normalizeText(typeLabel) ||
                normalizedTitle === this.normalizeText(chipLabel)

            if (!description && isTypeOnly) {
                return ''
            }

            return title
        },

        entryDescription(entry) {
            const description = String(entry?.description || '').trim()
            if (!description) return ''
            if (this.normalizeText(description) === this.normalizeText(this.entryTitle(entry))) {
                return ''
            }
            return description
        },

        getEntryColor(type) {
            const colors = {
                homework: 'primary',
                TW: 'primary',
                exam: 'warning',
                PÜ: 'warning',
                A: 'info',
                MA: 'success',
                activity: 'purple',
                note: 'grey',
                info: 'grey',
            }
            return colors[type] || 'grey'
        },

        formatDate(dateString) {
            if (!dateString) return '—'
            try {
                return new Intl.DateTimeFormat('de-AT', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                }).format(parseLocalDate(dateString))
            } catch (error) {
                return dateString
            }
        },
    },
}
</script>

<style scoped>
.entries-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.entries-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}

.entries-toolbar-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #314d5d;
}

.entries-semester-filter {
    display: flex;
    justify-content: flex-start;
}

.entry-divider-row {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.entry-divider-label {
    color: rgba(0, 0, 0, 0.6);
    font-weight: 700;
    font-size: 12px;
    white-space: nowrap;
}

.entry-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 10px;
    width: 100%;
    padding: 12px;
    border-radius: 8px;
}

.entry-row--base {
    background-color: #ffffff;
}

.entry-row--alt {
    background-color: #e9edf5;
}

.entry-main {
    min-width: 120px;
    flex: 1 1 260px;
    color: #314d5d;
}

.entry-grade {
    margin-left: auto;
    align-self: center;
}

.entry-title {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.35;
}

.entry-description {
    margin-top: 6px;
    font-size: 0.95rem;
    line-height: 1.45;
    color: #55626c;
}

@media (max-width: 700px) {
    .entry-main {
        flex-basis: 100%;
    }

    .entry-grade {
        margin-left: auto;
    }
}
</style>
