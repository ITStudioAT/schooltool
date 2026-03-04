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
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" @click="showDrawer = true" />
                </div>

                <h1 class="hero-title">{{ course?.title || 'Fach wird geladen...' }}</h1>
                <p class="hero-subtitle">Details zum Fach ansehen.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="user" class="hero-badge">
                        {{ user.first_name }} {{ user.last_name }}
                        <span v-if="courseStars.length" class="stars-inline">
                            <v-icon v-for="(star, index) in courseStars" :key="index" size="18" color="#fd802e">mdi-star</v-icon>
                        </span>
                    </span>
                    <span v-if="user?.schoolclass" class="hero-badge dark">{{ user.schoolclass }}</span>
                </div>

                <div class="hero-logout-row">
                    <v-btn class="logout-btn" variant="text" prepend-icon="mdi-logout" @click="handleLogout">Abmelden</v-btn>
                </div>

                <div v-if="heroLiveTimerText" class="hero-live-timer">
                    <v-icon size="20" color="primary">mdi-timer-sand</v-icon>
                    <span>{{ heroLiveTimerText }}</span>
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
                    <div class="course-tabs-mobile">
                        <v-btn class="course-tab-mobile-btn" :variant="currentTab === 'overview' ? 'flat' : 'outlined'" :color="currentTab === 'overview' ? 'primary' : undefined" @click="currentTab = 'overview'">
                            <v-icon start>mdi-information-outline</v-icon>
                            Übersicht
                        </v-btn>
                        <v-btn class="course-tab-mobile-btn" :variant="currentTab === 'entries' ? 'flat' : 'outlined'" :color="currentTab === 'entries' ? 'primary' : undefined" @click="currentTab = 'entries'">
                            <v-icon start>mdi-notebook-outline</v-icon>
                            Leistungen
                        </v-btn>
                        <v-btn v-if="showBehaviourEnabled" class="course-tab-mobile-btn" :variant="currentTab === 'behaviour' ? 'flat' : 'outlined'" :color="currentTab === 'behaviour' ? 'primary' : undefined" @click="currentTab = 'behaviour'">
                            <v-icon start>mdi-account-star</v-icon>
                            Verhalten
                        </v-btn>
                        <v-btn class="course-tab-mobile-btn" :variant="currentTab === 'dates' ? 'flat' : 'outlined'" :color="currentTab === 'dates' ? 'primary' : undefined" @click="currentTab = 'dates'">
                            <v-icon start>mdi-calendar-month</v-icon>
                            Termine
                        </v-btn>
                    </div>

                    <v-tabs v-model="currentTab" class="course-tabs course-tabs-desktop" bg-color="transparent" color="#fd802e" grow>
                        <v-tab value="overview">
                            <v-icon start>mdi-information-outline</v-icon>
                            Übersicht
                        </v-tab>
                        <v-tab value="entries">
                            <v-icon start>mdi-notebook-outline</v-icon>
                            Leistungen
                        </v-tab>
                        <v-tab v-if="showBehaviourEnabled" value="behaviour">
                            <v-icon start>mdi-account-star</v-icon>
                            Verhalten
                        </v-tab>
                        <v-tab value="dates">
                            <v-icon start>mdi-calendar-month</v-icon>
                            Termine
                        </v-tab>
                    </v-tabs>

                    <v-tabs-window v-model="currentTab" style="margin-top: 20px">
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

                            <!-- Open Notifications Section (IMPORTANT - directly after general info!) -->
                            <div v-if="openNotifications.length" class="profile-section" style="margin-top: 20px">
                                <h3 class="profile-section-title" style="color: #f44336">
                                    <v-icon size="22" color="#f44336">mdi-bell-alert</v-icon>
                                    Offene Verständigungen
                                </h3>
                                <div class="notifications-list">
                                    <div v-for="notification in openNotifications" :key="notification.id" class="notification-item notification-open">
                                        <div class="notification-icon">
                                            <v-icon size="20" color="#f44336">mdi-bell-alert</v-icon>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-header">
                                                <span v-if="notification.date" class="notification-date">{{ formatDate(notification.date) }}</span>
                                                <span v-if="notification.type" class="notification-type">{{ getNotificationTypeLabel(notification.type) }}</span>
                                            </div>
                                            <div class="notification-description">{{ notification.description }}</div>
                                            <div v-if="notification.due_date" class="notification-dates">
                                                <span class="notification-due-date">Fällig: {{ formatDate(notification.due_date) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stars Section (beautiful!) -->
                            <div v-if="courseStars.length" class="profile-section" style="margin-top: 20px">
                                <h3 class="profile-section-title">
                                    <v-icon size="22" color="#ffa726">mdi-star</v-icon>
                                    <span class="stars-title-text">Sterne</span>
                                    <span class="stars-title-glow">✨</span>
                                </h3>
                                <div class="stars-beautiful-list">
                                    <div v-for="(star, index) in courseStars" :key="star.id || index" class="star-beautiful-item">
                                        <div class="star-beautiful-icon-container">
                                            <div class="star-beautiful-icon">
                                                <v-icon size="32" color="white">mdi-star</v-icon>
                                            </div>
                                        </div>
                                        <div class="star-beautiful-content">
                                            <div v-if="star.date" class="star-beautiful-date">
                                                <v-icon size="16" color="#999">mdi-calendar</v-icon>
                                                {{ formatDate(star.date) }}
                                            </div>
                                            <div class="star-beautiful-comment">{{ star.comment }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Closed Notifications Section -->
                            <div v-if="closedNotifications.length" class="profile-section" style="margin-top: 20px">
                                <h3 class="profile-section-title">
                                    <v-icon size="20" color="#4caf50">mdi-bell-check</v-icon>
                                    Erledigte Verständigungen
                                </h3>
                                <div class="notifications-list">
                                    <div v-for="notification in closedNotifications" :key="notification.id" class="notification-item notification-closed">
                                        <div class="notification-icon">
                                            <v-icon size="20" color="#4caf50">mdi-bell-check</v-icon>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-header">
                                                <span v-if="notification.date" class="notification-date">{{ formatDate(notification.date) }}</span>
                                                <span v-if="notification.type" class="notification-type">{{ getNotificationTypeLabel(notification.type) }}</span>
                                            </div>
                                            <div class="notification-description">{{ notification.description }}</div>
                                            <div v-if="notification.due_date || notification.done_date" class="notification-dates">
                                                <span v-if="notification.due_date" class="notification-due-date">Fällig: {{ formatDate(notification.due_date) }}</span>
                                                <span v-if="notification.done_date" class="notification-done-date">Erledigt: {{ formatDate(notification.done_date) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Grades Section (last) -->
                            <div class="profile-section" style="margin-top: 20px">
                                <h3 class="profile-section-title">
                                    <v-icon size="20">mdi-chart-line</v-icon>
                                    Noten
                                </h3>
                                <v-alert v-if="showMissingRequiredNaHint" type="error" variant="tonal" density="comfortable" icon="mdi-alert-circle" class="grades-required-hint">
                                    <strong>Hinweis:</strong> Es wurden noch nicht alle Leistungen erbracht! (siehe Leistungen)
                                </v-alert>
                                <div class="grades-display">
                                    <template v-if="course?.sem_1_grade || course?.sem_2_grade">
                                        <div class="grade-item" :class="course?.sem_1_grade ? 'grade-set' : 'grade-open'">
                                            <div class="grade-label">1. Semester</div>
                                            <div class="grade-value">{{ course?.sem_1_grade || 'offen' }}</div>
                                            <div
                                                v-if="showBehaviourEnabled && (course?.behaviour_1_grade || !course?.sem_1_grade)"
                                                class="behaviour-value"
                                                :class="course?.behaviour_1_grade ? 'behaviour-set' : 'behaviour-open'">
                                                Verhalten: {{ course?.behaviour_1_grade || 'offen' }}
                                            </div>
                                        </div>
                                        <div class="grade-item" :class="course?.sem_2_grade ? 'grade-set' : 'grade-open'">
                                            <div class="grade-label">2. Semester</div>
                                            <div class="grade-value">{{ course?.sem_2_grade || 'offen' }}</div>
                                            <div
                                                v-if="showBehaviourEnabled && (course?.behaviour_2_grade || !course?.sem_2_grade)"
                                                class="behaviour-value"
                                                :class="course?.behaviour_2_grade ? 'behaviour-set' : 'behaviour-open'">
                                                Verhalten: {{ course?.behaviour_2_grade || 'offen' }}
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="grade-item" :class="course?.sem_grade ? 'grade-set' : 'grade-open'">
                                            <div class="grade-label">Semesternote</div>
                                            <div class="grade-value">{{ course?.sem_grade || 'offen' }}</div>
                                            <div
                                                v-if="showBehaviourEnabled && (course?.behaviour_grade || !course?.sem_grade)"
                                                class="behaviour-value"
                                                :class="course?.behaviour_grade ? 'behaviour-set' : 'behaviour-open'">
                                                Verhalten: {{ course?.behaviour_grade || 'offen' }}
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </v-tabs-window-item>

                        <!-- Leistungen Tab -->
                        <v-tabs-window-item value="entries">
                            <!-- Loading State -->
                            <div v-if="loadingEntries" class="courses-loading">
                                <v-progress-circular indeterminate color="#fd802e" />
                                <p>Lade Leistungen...</p>
                            </div>

                            <div v-else class="entries-section">
                                <div class="entries-toolbar">
                                    <div class="entries-toolbar-title">
                                        <v-icon size="18">mdi-clipboard-text</v-icon>
                                        <span>Leistungen</span>
                                        <v-chip v-if="sortedEntries.length" size="x-small" color="primary" variant="tonal">
                                            {{ sortedEntries.length }}
                                        </v-chip>
                                    </div>
                                    <v-btn size="small" variant="tonal" color="primary" @click="toggleSortByType">
                                        {{ sortByType ? 'Sort: Typ' : 'Sort: Datum' }}
                                    </v-btn>
                                </div>

                                <div class="entries-semester-filter">
                                    <v-btn-toggle v-model="selectedSemester" class="semester-toggle" mandatory density="compact" color="primary">
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
                                                    <div class="entry-row" :class="[
                                                        item.stripe % 2 === 1 ? 'entry-row--alt' : 'entry-row--base',
                                                        isEntryOpen(item.entry) ? 'entry-row--open' : ''
                                                    ]">
                                                        <v-icon size="22" :color="getEntryStatusColor(item.entry)">{{ getEntryIcon(item.entry.type) }}</v-icon>
                                                        <v-chip v-if="item.entry.date" size="small" variant="tonal" color="primary">
                                                            {{ formatDate(item.entry.date) }}
                                                        </v-chip>
                                                        <v-chip v-if="item.entry.type" size="small" variant="outlined">
                                                            {{ entryTypeChipLabel(item.entry.type) }}
                                                        </v-chip>
                                                        <v-chip v-if="item.entry.is_required_entry" size="small" color="error" variant="flat">
                                                            Erforderlich
                                                        </v-chip>
                                                        <div class="entry-main text-caption">
                                                            <div v-if="entryTitle(item.entry)" class="entry-title">{{ entryTitle(item.entry) }}</div>
                                                            <!-- Show expand button for work entries with details -->
                                                            <div v-if="item.entry.work && (item.entry.work.description || item.entry.work.is_group_work)" class="entry-work-toggle">
                                                                <v-btn size="x-small" variant="tonal" color="primary" @click="toggleEntryExpansion(item.entry.id)">
                                                                    <v-icon start size="small">{{ expandedEntries[item.entry.id] ? 'mdi-chevron-up' : 'mdi-chevron-down' }}</v-icon>
                                                                    Aufgaben-Details
                                                                </v-btn>
                                                            </div>
                                                            <!-- Show work details if expanded -->
                                                            <div v-if="item.entry.work && expandedEntries[item.entry.id]" class="entry-work-details">
                                                                <div v-if="item.entry.work.description" class="work-detail-item">
                                                                    <strong>Beschreibung:</strong>
                                                                    <div class="work-description-content" v-html="item.entry.work.description"></div>
                                                                </div>
                                                                <div v-if="item.entry.work.is_group_work" class="work-detail-item">
                                                                    <strong>Gruppenarbeit:</strong>
                                                                    Ja
                                                                    <span v-if="item.entry.work.group_size">({{ item.entry.work.group_size }} Personen)</span>
                                                                </div>
                                                                <div v-if="item.entry.work.is_group_work" class="work-detail-item">
                                                                    <strong>Gruppenmitglieder:</strong>
                                                                    <span v-if="item.entry.work.group_members && item.entry.work.group_members.length">
                                                                        {{ item.entry.work.group_members.join(', ') }}
                                                                    </span>
                                                                    <span v-else class="text-muted">Keine weiteren Gruppenmitglieder</span>
                                                                </div>
                                                            </div>
                                                            <!-- Show description and comment for non-work entries -->
                                                            <div v-if="!item.entry.work && entryDescription(item.entry)" class="entry-description">
                                                                {{ entryDescription(item.entry) }}
                                                            </div>
                                                            <div v-if="entryComment(item.entry)" class="entry-comment">
                                                                {{ entryComment(item.entry) }}
                                                            </div>
                                                        </div>
                                                        <v-chip class="entry-grade" size="small" variant="tonal" :color="entryGradeChipColor(item.entry)">
                                                            {{ item.entry.grade || 'offen' }}
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
                                        <strong>Keine Leistungen:</strong>
                                        Für dieses Fach sind noch keine Leistungen vorhanden.
                                    </div>
                                </div>
                            </div>
                        </v-tabs-window-item>

                        <!-- Verhalten Tab -->
                        <v-tabs-window-item v-if="showBehaviourEnabled" value="behaviour">
                            <div v-if="behaviourEntries.length === 0" class="profile-info-box">
                                <v-icon color="#fd802e" size="24">mdi-account-star</v-icon>
                                <div>
                                    <strong>Verhalten:</strong>
                                    Keine Verhalteneinträge vorhanden.
                                </div>
                            </div>
                            <div v-else class="behaviour-entries-section">
                                <div class="behaviour-entries-list">
                                    <div v-for="entry in behaviourEntries" :key="entry.id" class="behaviour-entry-item">
                                        <div class="behaviour-entry-icon">
                                            <v-icon size="20" color="#2196f3">mdi-account-star</v-icon>
                                        </div>
                                        <div class="behaviour-entry-content">
                                            <div class="behaviour-entry-header">
                                                <span v-if="entry.date" class="behaviour-entry-date">{{ formatDate(entry.date) }}</span>
                                                <span v-if="entry.type" class="behaviour-entry-type">{{ getBehaviourTypeLabel(entry.type) }}</span>
                                            </div>
                                            <div class="behaviour-entry-description">{{ entry.description }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </v-tabs-window-item>

                        <!-- Termine Tab -->
                        <v-tabs-window-item value="dates">
                            <div v-if="courseDates.length === 0" class="profile-info-box">
                                <v-icon color="#fd802e" size="24">mdi-calendar-month</v-icon>
                                <div>
                                    <strong>Termine:</strong>
                                    Keine Termine vorhanden.
                                </div>
                            </div>
                            <div v-else class="dates-section">
                                <div class="dates-toolbar">
                                    <div class="dates-toolbar-title">
                                        <v-icon size="18">mdi-calendar-month</v-icon>
                                        <span>Termine</span>
                                        <v-chip v-if="filteredDates.length" size="x-small" color="primary" variant="tonal">
                                            {{ filteredDates.length }}
                                        </v-chip>
                                    </div>
                                </div>

                                <div class="dates-semester-filter">
                                    <v-btn-toggle v-model="selectedSemesterDates" class="semester-toggle" mandatory density="compact" color="primary">
                                        <v-btn :value="1" size="small">1. Sem</v-btn>
                                        <v-btn :value="2" size="small">2. Sem</v-btn>
                                        <v-btn :value="3" size="small">1+2</v-btn>
                                    </v-btn-toggle>
                                </div>

                                <v-card v-if="filteredDates.length > 0" variant="outlined">
                                    <v-card-text class="pa-0">
                                        <v-list density="comfortable">
                                            <v-list-item v-for="(dateEntry, index) in filteredDates" :key="dateEntry.id || index">
                                                <div
                                                    class="date-row"
                                                    :class="[
                                                        index % 2 === 1 ? 'date-row--alt' : 'date-row--base',
                                                        getDateStatusClass(dateEntry.status),
                                                        { 'date-row--today': isDateToday(dateEntry.date) },
                                                    ]">
                                                    <v-icon size="22" :color="getDateIconColor(dateEntry.status)">mdi-calendar</v-icon>
                                                    <v-chip v-if="isDateToday(dateEntry.date)" size="small" color="warning" variant="flat">
                                                        Heute
                                                    </v-chip>
                                                    <v-chip v-if="dateEntry.date && !isDateToday(dateEntry.date)" size="small" variant="tonal" color="primary">
                                                        {{ formatDate(dateEntry.date) }}
                                                    </v-chip>
                                                    <v-chip v-if="dateEntry.hours && dateEntry.hours.length" size="small" variant="outlined">
                                                        {{ dateEntry.hours.join(', ') }}. Std
                                                    </v-chip>
                                                    <v-chip v-if="hasFreeStatus(dateEntry.status) && dateEntry.free_reason" size="small" color="success" variant="tonal">
                                                        {{ dateEntry.free_reason }}
                                                    </v-chip>
                                                    <div class="date-main text-caption">
                                                        <div v-if="dateEntry.content" class="date-content" v-html="dateEntry.content"></div>
                                                    </div>
                                                </div>
                                            </v-list-item>
                                        </v-list>
                                    </v-card-text>
                                </v-card>
                                <div v-else class="profile-info-box" style="margin-top: 16px">
                                    <v-icon color="#999" size="24">mdi-calendar-blank</v-icon>
                                    <div>Keine Termine für dieses Semester vorhanden.</div>
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
        if (this.entries.length === 0 && !this.loadingEntries) {
            this.loadEntries()
        }
    },
    mounted() {
        this.nowTimer = setInterval(() => {
            this.nowTs = Date.now()
        }, 1000)
    },
    unmounted() {
        if (this.nowTimer) {
            clearInterval(this.nowTimer)
        }
    },

    data() {
        return {
            studentStore: null,
            courseStore: null,
            showDrawer: false,
            loading: true,
            course: null,
            currentTab: 'overview', // Start with Übersicht as default
            entries: [],
            loadingEntries: false,
            selectedSemester: 3, // 1 = Semester 1, 2 = Semester 2, 3 = Both (for entries)
            selectedSemesterDates: 3, // 1 = Semester 1, 2 = Semester 2, 3 = Both (for dates)
            sortByType: false,
            expandedEntries: {}, // Track which work entries are expanded
            nowTs: Date.now(),
            nowTimer: null,
            simulatedCourseEndAt: null,
        }
    },

    computed: {
        ...mapWritableState(useStudentStore, ['user']),

        courseId() {
            return this.$route.params.id
        },

        courseStars() {
            return this.course?.stars || []
        },

        courseNotifications() {
            return this.course?.notifications || []
        },

        openNotifications() {
            return this.courseNotifications.filter((n) => n.is_open)
        },

        closedNotifications() {
            return this.courseNotifications.filter((n) => !n.is_open)
        },

        behaviourEntries() {
            return this.course?.behaviour_entries || []
        },
        showBehaviourEnabled() {
            return this.course?.show_behaviour !== false
        },

        courseDates() {
            return this.course?.course_dates || []
        },
        courseRemainingLabel() {
            const endAtRaw = (this.course?.active_course_end_at || this.simulatedCourseEndAt || '').toString().trim()
            if (!endAtRaw) {
                return null
            }

            const endAt = new Date(endAtRaw)
            if (isNaN(endAt.getTime())) {
                return null
            }

            const diffSeconds = Math.max(0, Math.floor((endAt.getTime() - this.nowTs) / 1000))
            if (diffSeconds <= 0) {
                return null
            }

            const hours = Math.floor(diffSeconds / 3600)
            const minutes = Math.floor((diffSeconds % 3600) / 60)

            if (hours > 0) {
                return `${String(hours).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m`
            }

            return `${String(minutes).padStart(2, '0')}m`
        },
        heroLiveTimerText() {
            if (!this.courseRemainingLabel) {
                return null
            }

            const modePrefix = this.isSimulatedCourseTimer() ? 'Testmodus' : 'Live'
            const title = (this.course?.title || '').toString().trim() || 'Kurs'

            return `${modePrefix}: ${title} endet in ${this.courseRemainingLabel}`
        },

        requiredEntries() {
            const entries = Array.isArray(this.entries) ? this.entries : []
            return entries.filter((entry) => Boolean(entry?.is_required_entry))
        },

        showMissingRequiredNaHint() {
            const requiredNaCount = this.requiredEntries.filter((entry) => {
                const grade = String(entry?.grade || '')
                    .trim()
                    .toUpperCase()

                return grade === 'NA'
            }).length

            return requiredNaCount === 1
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

        notificationTypeLabels() {
            // Use teacher's teaching_notifications from the course
            const notifications = Array.isArray(this.course?.teacher_teaching_notifications) ? this.course.teacher_teaching_notifications : []
            return notifications.reduce((result, notification) => {
                if (notification?.short_name) {
                    result[String(notification.short_name)] = notification?.name || String(notification.short_name)
                }
                return result
            }, {})
        },

        behaviourTypeLabels() {
            // Use teacher's teaching_behaviour from the course
            const behaviours = Array.isArray(this.course?.teacher_teaching_behaviour) ? this.course.teacher_teaching_behaviour : []
            return behaviours.reduce((result, behaviour) => {
                if (behaviour?.short_name) {
                    result[String(behaviour.short_name)] = behaviour?.name || String(behaviour.short_name)
                }
                return result
            }, {})
        },

        // Get semester boundary: teacher's personal date > schoolyear sem_2_start > Feb 1st fallback
        semesterBoundary() {
            if (this.course?.teacher_count_for_semester_2_date) {
                return this.course.teacher_count_for_semester_2_date
            }
            if (this.course?.sem_2_start) {
                return this.course.sem_2_start
            }
            const now = new Date()
            const year = now.getMonth() >= 8 ? now.getFullYear() + 1 : now.getFullYear()
            return `${year}-02-01`
        },

        // Filter entries by selected semester
        filteredEntries() {
            if (this.selectedSemester === 3) {
                return this.entries
            }

            const boundary = this.normalizeDateKey(this.semesterBoundary)
            if (!boundary) return this.entries

            return this.entries.filter((entry) => {
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

        // Filter dates by selected semester
        filteredDates() {
            if (this.selectedSemesterDates === 3) {
                return this.courseDates
            }

            const boundary = this.normalizeDateKey(this.semesterBoundary)
            if (!boundary) return this.courseDates

            return this.courseDates.filter((dateEntry) => {
                if (!dateEntry.date) return true
                const date = this.normalizeDateKey(dateEntry.date)
                if (!date) return true
                if (this.selectedSemesterDates === 1) {
                    return date < boundary
                } else if (this.selectedSemesterDates === 2) {
                    return date >= boundary
                }
                return true
            })
        },

        sortedEntries() {
            const list = this.filteredEntries || []
            const sorted = this.sortByType
                ? [...list].sort((a, b) => {
                    const typeA = (a.type || '').toString()
                    const typeB = (b.type || '').toString()
                    const typeCompare = typeA.localeCompare(typeB, 'de', { sensitivity: 'base' })
                    if (typeCompare !== 0) return typeCompare
                    const dateA = a.date ? parseLocalDate(a.date).getTime() : 0
                    const dateB = b.date ? parseLocalDate(b.date).getTime() : 0
                    return dateB - dateA
                })
                : [...list]

            // Sort open entries (without grade) first
            return sorted.sort((a, b) => {
                const aIsOpen = !a.grade || a.grade.trim() === ''
                const bIsOpen = !b.grade || b.grade.trim() === ''
                if (aIsOpen && !bIsOpen) return -1
                if (!aIsOpen && bIsOpen) return 1
                return 0
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
            // Load entries when switching to Leistungen tab
            if (newTab === 'entries' && this.entries.length === 0 && !this.loadingEntries) {
                this.loadEntries()
            }
        },
        showBehaviourEnabled(newValue) {
            if (newValue === false && this.currentTab === 'behaviour') {
                this.currentTab = 'overview'
            }
        },
        '$route.query.live_timer'() {
            this.setupSimulatedTimer()
        },
        '$route.query.timer'() {
            this.setupSimulatedTimer()
        },
    },

    methods: {
        async handleLogout() {
            this.showDrawer = false
            await this.studentStore.logout()
            this.$router.push('/student')
        },

        toggleSortByType() {
            this.sortByType = !this.sortByType
        },

        normalizeDateKey(date) {
            if (!date) return ''
            // Keep pure date strings as-is; parse date-time strings in local time.
            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
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
                // Get course details with notifications
                await this.courseStore.getCourse(this.courseId)
                this.course = this.courseStore.course
                this.setupSimulatedTimer()
                if (!this.showBehaviourEnabled && this.currentTab === 'behaviour') {
                    this.currentTab = 'overview'
                }

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
            return String(value || '')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase()
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
                normalizedTitle === this.normalizeText(type) || normalizedTitle === this.normalizeText(typeLabel) || normalizedTitle === this.normalizeText(chipLabel)

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

        entryComment(entry) {
            const comment = String(entry?.comment || '').trim()
            return comment
        },

        isEntryOpen(entry) {
            const grade = String(entry?.grade || '').trim()
            return grade === ''
        },

        getEntryStatusColor(entry) {
            return this.isEntryOpen(entry) ? 'error' : 'success'
        },

        entryGradeChipColor(entry) {
            const grade = String(entry?.grade || '').trim()
            if (grade === '') {
                return 'error'
            }

            return grade.toUpperCase() === 'NA' ? 'error' : 'success'
        },

        formatDate(dateString) {
            if (!dateString) return '—'
            try {
                return new Intl.DateTimeFormat('de-AT', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                }).format(parseLocalDate(dateString))
            } catch (error) {
                return dateString
            }
        },
        isDateToday(dateValue) {
            const targetKey = this.normalizeDateKey(dateValue)
            if (!targetKey) return false
            return targetKey === this.normalizeDateKey(new Date())
        },
        isLiveTimerTestMode() {
            const liveTimerQuery = this.$route?.query?.live_timer
            const timerQuery = this.$route?.query?.timer
            return liveTimerQuery === '1'
                || liveTimerQuery === 'true'
                || timerQuery === '1'
                || timerQuery === 'true'
        },
        isSimulatedCourseTimer() {
            return !this.course?.active_course_end_at && !!this.simulatedCourseEndAt
        },
        setupSimulatedTimer() {
            this.simulatedCourseEndAt = null
            if (!this.isLiveTimerTestMode()) {
                return
            }

            if (this.course?.active_course_end_at) {
                return
            }

            const timeLabel = (this.course?.next_course_date?.time_label || '').toString().trim()
            const durationSeconds = this.durationSecondsFromTimeLabel(timeLabel)
            if (durationSeconds <= 0) {
                return
            }

            this.simulatedCourseEndAt = new Date(this.nowTs + (durationSeconds * 1000)).toISOString()
        },
        durationSecondsFromTimeLabel(timeLabel) {
            const normalized = (timeLabel || '').toString().trim()
            if (!normalized.includes('-')) {
                return 0
            }

            const [fromRaw, untilRaw] = normalized.split('-').map((part) => part.trim())
            const fromMinutes = this.minutesFromTime(fromRaw)
            const untilMinutes = this.minutesFromTime(untilRaw)

            if (fromMinutes === null || untilMinutes === null || untilMinutes <= fromMinutes) {
                return 0
            }

            return (untilMinutes - fromMinutes) * 60
        },
        minutesFromTime(value) {
            const parts = (value || '').toString().trim().split(':').map((part) => Number(part))
            if (!Number.isFinite(parts[0]) || !Number.isFinite(parts[1])) {
                return null
            }

            return (parts[0] * 60) + parts[1]
        },

        getNotificationTypeLabel(type) {
            if (!type) return ''
            return this.notificationTypeLabels[type] || type
        },

        getBehaviourTypeLabel(type) {
            const rawType = String(type || '').trim()
            if (!rawType) return ''

            const directLabel = this.behaviourTypeLabels[rawType]
            if (directLabel) {
                return directLabel
            }

            const normalizedType = rawType.toUpperCase()
            const behaviourDefinitions = Array.isArray(this.course?.teacher_teaching_behaviour) ? this.course.teacher_teaching_behaviour : []
            const normalizedDefinitions = behaviourDefinitions
                .map((entry) => {
                    const shortName = String(entry?.short_name || '').trim()
                    const name = String(entry?.name || shortName).trim()
                    return {
                        shortName,
                        shortNameUpper: shortName.toUpperCase(),
                        name: name || shortName,
                    }
                })
                .filter((entry) => entry.shortNameUpper !== '')

            const caseInsensitiveExact = normalizedDefinitions.find((entry) => entry.shortNameUpper === normalizedType)
            if (caseInsensitiveExact) {
                return caseInsensitiveExact.name
            }

            const tolerantMatches = normalizedDefinitions
                .filter((entry) => entry.shortNameUpper.startsWith(normalizedType) || normalizedType.startsWith(entry.shortNameUpper))
                .sort((a, b) => a.shortNameUpper.length - b.shortNameUpper.length)

            if (tolerantMatches.length >= 1) {
                return tolerantMatches[0].name
            }

            return rawType
        },

        getDateStatusClass(status) {
            if (!status || !Array.isArray(status)) return ''
            const statusStr = status.join(' ').toLowerCase()
            if (statusStr.includes('pruefung') || statusStr.includes('prüfung')) {
                return 'date-row--exam'
            }
            if (this.hasFreeStatus(status)) {
                return 'date-row--free'
            }
            return ''
        },

        getDateIconColor(status) {
            if (!status || !Array.isArray(status)) return '#2196f3'
            const statusStr = status.join(' ').toLowerCase()
            if (statusStr.includes('pruefung') || statusStr.includes('prüfung')) {
                return '#ff5722'
            }
            if (this.hasFreeStatus(status)) {
                return '#4caf50'
            }
            return '#2196f3'
        },

        hasFreeStatus(status) {
            if (!status || !Array.isArray(status)) return false
            const statusStr = status.join(' ').toLowerCase()
            return statusStr.includes('frei')
                || statusStr.includes('free')
                || statusStr.includes('entfaellt')
                || statusStr.includes('entfällt')
                || statusStr.includes('entfallen')
        },

        toggleEntryExpansion(entryId) {
            this.expandedEntries[entryId] = !this.expandedEntries[entryId]
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

.hero-live-timer {
    margin-top: 28px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    color: var(--primary, #fd802e);
    font-weight: 800;
    font-size: 1rem;
    line-height: 1.2;
}

.content-head {
    flex-wrap: wrap;
    row-gap: 8px;
}

.content-head h2 {
    flex: 1 1 auto;
    min-width: 0;
}

.course-tabs :deep(.v-slide-group__content) {
    gap: 4px;
}

.course-tabs :deep(.v-tab) {
    min-width: 0;
}

.course-tabs-mobile {
    display: none;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 12px;
}

.course-tab-mobile-btn {
    justify-content: flex-start;
    text-transform: none;
}

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
    width: 100%;
}

.semester-toggle {
    width: 100%;
    max-width: 330px;
}

.semester-toggle :deep(.v-btn) {
    min-width: 0;
    flex: 1 1 0;
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

.entry-row--open {
    border-left: 4px solid #ff9800 !important;
    background-color: #fff3e0 !important;
}

.entry-main {
    min-width: 120px;
    flex: 1 1 260px;
    color: #314d5d;
}

.entry-main,
.date-main,
.notification-content,
.behaviour-entry-content,
.star-beautiful-content,
.star-content {
    min-width: 0;
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

.entry-comment {
    margin-top: 6px;
    font-size: 0.9rem;
    line-height: 1.4;
    color: #6b7882;
    font-style: italic;
}

.entry-work-toggle {
    margin-top: 8px;
}

.entry-work-details {
    margin-top: 12px;
    padding: 12px;
    background-color: #f5f5f5;
    border-radius: 6px;
    border-left: 3px solid #2196f3;
}

.work-detail-item {
    margin-bottom: 8px;
    font-size: 0.9rem;
    line-height: 1.5;
    color: #314d5d;
}

.work-detail-item:last-child {
    margin-bottom: 0;
}

.work-detail-item strong {
    font-weight: 600;
    color: #1976d2;
}

.work-description-content {
    margin-top: 4px;
    line-height: 1.5;
    white-space: pre-wrap;
}

.entry-title,
.entry-description,
.entry-comment,
.date-content,
.notification-description,
.behaviour-entry-description,
.star-beautiful-comment,
.star-comment {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.work-description-content :deep(p) {
    margin: 0.5em 0;
}

.work-description-content :deep(p:first-child) {
    margin-top: 0;
}

.work-description-content :deep(p:last-child) {
    margin-bottom: 0;
}

.work-description-content :deep(img),
.date-content :deep(img) {
    max-width: 100%;
    height: auto;
}

.work-description-content :deep(table),
.date-content :deep(table) {
    display: block;
    max-width: 100%;
    overflow-x: auto;
}

.stars-inline {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin-left: 8px;
}

/* Dates Styles */
.dates-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.dates-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}

.dates-toolbar-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #314d5d;
}

.dates-semester-filter {
    display: flex;
    justify-content: flex-start;
    width: 100%;
}

.date-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 10px;
    width: 100%;
    padding: 12px;
    border-radius: 8px;
}

.date-row--base {
    background-color: #ffffff;
}

.date-row--alt {
    background-color: #e3f2fd;
}

.date-row--exam {
    background-color: #ffebee !important;
    border-left: 4px solid #ff5722;
}

.date-row--free {
    background-color: #c8e6c9 !important;
    border-left: 4px solid #4caf50;
}

.date-row--today {
    box-shadow: inset 0 0 0 2px rgba(255, 152, 0, 0.55);
}

.date-main {
    min-width: 120px;
    flex: 1 1 260px;
    color: #314d5d;
}

.date-content {
    font-size: 0.9rem;
    font-weight: 400;
    line-height: 1.5;
    white-space: pre-wrap;
}

.hero-badge.grade {
    background-color: #4caf50;
    border: 1px solid #4caf50;
    color: white;
    font-weight: 600;
}

.grades-display {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.grades-required-hint {
    margin-bottom: 12px;
    border: 1px solid rgba(211, 47, 47, 0.35);
}

.grade-item {
    flex: 1;
    min-width: 150px;
    padding: 16px;
    border-radius: 8px;
    text-align: center;
}

.grade-item.grade-set {
    background-color: #e8f5e9;
    border: 2px solid #4caf50;
}

.grade-item.grade-open {
    background-color: #ffebee;
    border: 3px solid #f44336;
    box-shadow: 0 2px 8px rgba(244, 67, 54, 0.2);
}

.grade-label {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 8px;
    font-weight: 500;
}

.grade-set .grade-value {
    font-size: 2rem;
    font-weight: 700;
    color: #2e7d32;
}

.grade-open .grade-value {
    font-size: 2rem;
    font-weight: 700;
    color: #c62828;
}

.behaviour-value {
    font-size: 0.9rem;
    font-weight: 600;
    margin-top: 8px;
    padding: 6px 12px;
    border-radius: 4px;
}

.behaviour-value.behaviour-set {
    color: #2e7d32;
    background-color: rgba(76, 175, 80, 0.1);
}

.behaviour-value.behaviour-open {
    color: #c62828;
    background-color: rgba(244, 67, 54, 0.1);
}

/* Compact Grades Display */
.grades-display-compact {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.grade-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.grade-sem-label {
    min-width: 120px;
    font-weight: 600;
    color: #314d5d;
    font-size: 0.95rem;
}

.grade-item-compact {
    flex: 1;
    padding: 10px;
    border-radius: 6px;
    text-align: center;
    font-weight: 600;
    font-size: 1.1rem;
}

.grade-item-compact.grade-set {
    background-color: #e8f5e9;
    border: 2px solid #4caf50;
    color: #2e7d32;
}

.grade-item-compact.grade-open {
    background-color: #ffebee;
    border: 2px solid #f44336;
    color: #c62828;
}

/* Beautiful Stars */
.stars-title-text {
    font-size: 1.1rem;
}

.stars-title-glow {
    margin-left: 8px;
    font-size: 1.2rem;
}

.stars-beautiful-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.star-beautiful-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
    background: linear-gradient(135deg, #fff5e1 0%, #ffe4b5 100%);
    border-radius: 12px;
    border: 2px solid #ffa726;
    box-shadow: 0 4px 12px rgba(255, 167, 38, 0.2);
    position: relative;
    overflow: hidden;
}

.star-beautiful-item::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
    pointer-events: none;
}

.star-beautiful-icon-container {
    flex-shrink: 0;
    position: relative;
}

.star-beautiful-icon {
    background: linear-gradient(135deg, #ffa726 0%, #ff9800 100%);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 8px rgba(255, 152, 0, 0.4);
}

.star-beautiful-content {
    flex: 1;
    position: relative;
    z-index: 1;
}

.star-beautiful-date {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 8px;
    font-weight: 500;
}

.star-beautiful-comment {
    font-size: 1.05rem;
    color: #314d5d;
    line-height: 1.5;
    font-weight: 500;
}

/* Notifications */
.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px;
    border-radius: 8px;
    border-left: 4px solid;
}

.notification-item.notification-open {
    background-color: #ffebee;
    border-left-color: #f44336;
    border: 3px solid #f44336;
    box-shadow: 0 3px 10px rgba(244, 67, 54, 0.25);
}

.notification-item.notification-closed {
    background-color: #e8f5e9;
    border: 2px solid #4caf50;
}

.notification-icon {
    flex-shrink: 0;
    padding-top: 2px;
}

.notification-content {
    flex: 1;
}

.notification-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 6px;
    flex-wrap: wrap;
}

.notification-date {
    font-size: 0.85rem;
    color: #666;
    font-weight: 500;
}

.notification-type {
    font-size: 0.8rem;
    color: #314d5d;
    font-weight: 600;
    padding: 4px 10px;
    background-color: rgba(49, 77, 93, 0.1);
    border-radius: 4px;
}

.notification-description {
    font-size: 1rem;
    color: #314d5d;
    line-height: 1.5;
    margin-bottom: 6px;
}

.notification-dates {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 6px;
}

.notification-due-date {
    font-size: 0.85rem;
    color: #f44336;
    font-weight: 600;
}

.notification-done-date {
    font-size: 0.85rem;
    color: #4caf50;
    font-weight: 500;
}

/* Behaviour Entries Styles */
.behaviour-entries-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.behaviour-entries-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.behaviour-entry-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px;
    border-radius: 8px;
    background-color: #e3f2fd;
    border: 2px solid #2196f3;
    box-shadow: 0 2px 8px rgba(33, 150, 243, 0.15);
}

.behaviour-entry-icon {
    flex-shrink: 0;
    padding-top: 2px;
}

.behaviour-entry-content {
    flex: 1;
}

.behaviour-entry-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 6px;
    flex-wrap: wrap;
}

.behaviour-entry-date {
    font-size: 0.85rem;
    color: #666;
    font-weight: 500;
}

.behaviour-entry-type {
    font-size: 0.8rem;
    color: #314d5d;
    font-weight: 600;
    padding: 4px 10px;
    background-color: rgba(49, 77, 93, 0.1);
    border-radius: 4px;
}

.behaviour-entry-description {
    font-size: 1rem;
    color: #314d5d;
    line-height: 1.5;
}

/* Old stars styles (kept for backwards compatibility) */
.stars-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.star-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    background-color: #f9f9f9;
    border-radius: 8px;
    border-left: 3px solid #fd802e;
}

.star-icon {
    flex-shrink: 0;
}

.star-content {
    flex: 1;
}

.star-date {
    font-size: 0.85rem;
    color: #888;
    margin-bottom: 4px;
}

.star-comment {
    font-size: 0.95rem;
    color: #314d5d;
    line-height: 1.4;
}

@media (max-width: 960px) {
    .course-tabs :deep(.v-slide-group__content) {
        flex-wrap: wrap;
    }

    .course-tabs :deep(.v-tab) {
        flex: 1 1 calc(50% - 4px);
    }
}

@media (max-width: 700px) {
    .course-tabs-desktop {
        display: none;
    }

    .course-tabs-mobile {
        display: grid;
    }

    .hero-logout-row {
        justify-content: flex-start;
    }

    .entries-toolbar,
    .dates-toolbar {
        align-items: flex-start;
    }

    .entries-toolbar > .v-btn {
        width: 100%;
    }

    .entry-row,
    .date-row {
        padding: 10px;
    }

    .entry-main,
    .date-main {
        flex-basis: 100%;
    }

    .entry-grade {
        margin-left: 0;
    }

    .grade-item {
        min-width: 100%;
    }

    .grade-set .grade-value,
    .grade-open .grade-value {
        font-size: 1.7rem;
    }

    .notification-item,
    .behaviour-entry-item,
    .star-beautiful-item {
        padding: 12px;
    }
}

@media (max-width: 520px) {
    .course-tabs-mobile {
        grid-template-columns: 1fr;
    }

    .course-tab-mobile-btn {
        width: 100%;
    }

    .semester-toggle {
        max-width: 100%;
    }

    .entry-row :deep(.v-chip),
    .date-row :deep(.v-chip) {
        max-width: 100%;
        width: fit-content;
        white-space: normal;
        height: auto;
        min-height: 26px;
    }

    .notification-dates {
        flex-direction: column;
        gap: 8px;
    }

    .star-beautiful-item {
        flex-direction: column;
        gap: 12px;
    }

    .star-beautiful-icon-container {
        align-self: flex-start;
    }
}
</style>
