<template>
    <div class="lernportal-page student-workspace student-course-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Navigation Drawer -->
        <StudentNavigationDrawer v-model="showDrawer" current-route="course" />

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/student/overview')">Meine Fächer</v-btn>
                    <v-btn class="menu-btn" variant="text" icon="mdi-menu" aria-label="Menü öffnen" @click="showDrawer = true" />
                </div>

                <div class="course-identity">
                    <div>
                        <span class="course-eyebrow">DEIN LERNRAUM</span>
                        <h1 class="hero-title">{{ course?.title || 'Fach wird geladen...' }}</h1>
                        <p class="hero-subtitle">{{ course?.teacher || 'Dein Unterricht' }}<span v-if="course?.classes?.length"> · {{ course.classes.join(', ') }}</span></p>
                    </div>
                    <div class="course-emblem" aria-hidden="true"><v-icon size="44">mdi-lightning-bolt-outline</v-icon></div>
                </div>

                <div class="hero-badges">
                    <span v-if="user" class="hero-badge">
                        {{ user.first_name }} {{ user.last_name }}
                        <span v-if="courseStars.length" class="stars-inline">
                            <v-icon v-for="(star, index) in courseStars" :key="index" size="18" color="#fd802e">mdi-star</v-icon>
                        </span>
                    </span>
                    <span v-if="user?.schoolclass" class="hero-badge dark">{{ user.schoolclass }}</span>
                </div>

                <ParentAccessPanel />

                <div v-if="heroLiveTimer" class="hero-on-air">
                    <div class="on-air-badge">
                        <span class="on-air-dot"></span>
                        <span class="on-air-label">{{ heroLiveTimer.isSimulated ? 'TESTMODUS' : 'ON AIR' }}</span>
                    </div>
                    <div class="on-air-title">{{ heroLiveTimer.title }}</div>
                    <div class="on-air-timer">endet in {{ heroLiveTimer.remainingLabel }}</div>
                </div>
            </div>
        </section>

        <section class="content-cover">
            <!-- Course Details Card -->
            <div class="content-card">
                <!-- Loading State -->
                <div v-if="loading" class="courses-loading">
                    <v-progress-circular indeterminate color="#fd802e" />
                    <p>Lade Fach-Details...</p>
                </div>

                <!-- Course Tabs -->
                <div v-else-if="course">
                    <nav class="student-course-nav" aria-label="Bereiche deines Fachs">
                        <v-btn class="course-tab-mobile-btn" :aria-pressed="currentTab === 'overview'" :variant="currentTab === 'overview' ? 'flat' : 'text'" :color="currentTab === 'overview' ? '#4056d6' : undefined" @click="currentTab = 'overview'">
                            <v-icon start>mdi-information-outline</v-icon>
                            Übersicht
                        </v-btn>
                        <v-btn class="course-tab-mobile-btn" :aria-pressed="currentTab === 'entries'" :variant="currentTab === 'entries' ? 'flat' : 'text'" :color="currentTab === 'entries' ? '#4056d6' : undefined" @click="currentTab = 'entries'">
                            <v-icon start>mdi-notebook-outline</v-icon>
                            <span>Leistungen<span v-if="showBehaviourEnabled" class="course-nav-secondary"> &amp; Verhalten</span></span>
                        </v-btn>
                        <v-btn class="course-tab-mobile-btn" :aria-pressed="currentTab === 'additional'" :variant="currentTab === 'additional' ? 'flat' : 'text'" :color="currentTab === 'additional' ? '#4056d6' : undefined" @click="currentTab = 'additional'">
                            <v-icon start>mdi-information-variant-circle-outline</v-icon>
                            Weitere
                        </v-btn>
                        <v-btn class="course-tab-mobile-btn" :aria-pressed="currentTab === 'dates'" :variant="currentTab === 'dates' ? 'flat' : 'text'" :color="currentTab === 'dates' ? '#4056d6' : undefined" @click="currentTab = 'dates'">
                            <v-icon start>mdi-calendar-month</v-icon>
                            Termine
                        </v-btn>
                    </nav>

                    <v-tabs-window v-model="currentTab" style="margin-top: 20px">
                        <!-- Übersicht Tab -->
                        <v-tabs-window-item value="overview">
                            <div class="student-course-summary">
                                <button class="student-summary-card student-summary-card--next" type="button" @click="currentTab = 'dates'">
                                    <v-icon size="24">mdi-calendar-clock-outline</v-icon>
                                    <span>Nächster Unterricht</span>
                                    <strong>{{ course.next_course_date?.date ? formatDate(course.next_course_date.date) : 'Noch kein Termin' }}</strong>
                                    <small>{{ course.next_course_date?.time_label || 'Alle Termine ansehen' }}</small>
                                </button>
                                <div class="student-summary-card student-summary-card--open">
                                    <v-icon size="24">mdi-bell-outline</v-icon>
                                    <span>Offene Verständigungen</span>
                                    <strong>{{ openNotifications.length }}</strong>
                                    <small>{{ openNotifications.length ? 'Weiter unten im Überblick' : 'Du bist auf dem Laufenden' }}</small>
                                </div>
                                <div class="student-summary-card student-summary-card--stars">
                                    <v-icon size="24">mdi-star-outline</v-icon>
                                    <span>Deine Sterne</span>
                                    <strong>{{ courseStars.length }}</strong>
                                    <small>{{ courseStars.length ? 'Stark gemacht!' : 'Dein Einsatz zählt' }}</small>
                                </div>
                            </div>
                            <details class="profile-section student-course-information">
                                <summary class="profile-section-title">
                                    <v-icon size="20">mdi-information</v-icon>
                                    Kontakt &amp; Fachinfos
                                    <v-icon class="ml-auto" size="20">mdi-chevron-down</v-icon>
                                </summary>
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
                                        <a class="profile-value student-contact-link" :href="`mailto:${course.teacher_email}`">{{ course.teacher_email }}</a>
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
                            </details>

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
                            <details v-if="closedNotifications.length" class="profile-section student-course-information" style="margin-top: 20px">
                                <summary class="profile-section-title">
                                    <v-icon size="20" color="#4caf50">mdi-bell-check</v-icon>
                                    Erledigte Verständigungen ({{ closedNotifications.length }})
                                    <v-icon class="ml-auto" size="20">mdi-chevron-down</v-icon>
                                </summary>
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
                            </details>

                            <!-- Grades Section (last) -->
                            <div v-if="showSemesterGrade || showBehaviourGrade || showCalculatedGradesSection || showMissingRequiredNaHint" class="profile-section" style="margin-top: 20px">
                                <h3 class="profile-section-title">
                                    <v-icon size="20">mdi-chart-line</v-icon>
                                    Noten
                                </h3>
                                <v-alert v-if="showMissingRequiredNaHint" type="error" variant="tonal" density="comfortable" icon="mdi-alert-circle" class="grades-required-hint">
                                    <strong>Hinweis:</strong> Es wurden noch nicht alle Leistungen erbracht! (siehe Leistungen)
                                </v-alert>
                                <div v-if="showSemesterGrade || showBehaviourGrade" class="grades-display" data-testid="student-assigned-grades">
                                    <div
                                        v-for="period in assignedGradePeriods"
                                        :key="period.label"
                                        class="grade-item"
                                        :class="(showSemesterGrade ? period.grade : period.behaviourGrade) ? 'grade-set' : 'grade-open'">
                                        <div class="grade-label">{{ period.label }}</div>
                                        <div v-if="showSemesterGrade" class="grade-value semester-grade-value">{{ period.grade || 'offen' }}</div>
                                        <div
                                            v-if="showBehaviourGrade"
                                            class="behaviour-value"
                                            :class="period.behaviourGrade ? 'behaviour-set' : 'behaviour-open'">
                                            Verhaltensnote: {{ period.behaviourGrade || 'offen' }}
                                        </div>
                                    </div>
                                </div>

                                <div v-if="showCalculatedGradesSection" class="calculated-grades-section">
                                    <h4 class="calculated-grades-title">
                                        <v-icon size="18">mdi-calculator</v-icon>
                                        Berechnete Noten
                                    </h4>
                                    <v-alert
                                        type="info"
                                        variant="tonal"
                                        density="compact"
                                        icon="mdi-information-outline"
                                        class="calculated-grades-note">
                                        Dient ausschließlich zur Information/als Richtwert. Die Beurteilung erfolgt immer durch den/die Lehrer:in.
                                    </v-alert>
                                    <div class="grades-display">
                                        <template v-if="hasTwoSemesters">
                                            <div class="grade-item grade-calculated" v-if="showCalculatedGradeSem1">
                                                <div class="grade-label">1. Semester</div>
                                                <div class="grade-value" :class="calcGradeClass(calculatedGradeValues.sem1)">{{ calcFormatGrade(calculatedGradeValues.sem1) }}</div>
                                            </div>
                                            <div class="grade-item grade-calculated" v-if="showCalculatedGradeSem2">
                                                <div class="grade-label">2. Semester</div>
                                                <div class="grade-value" :class="calcGradeClass(calculatedGradeValues.sem2)">{{ calcFormatGrade(calculatedGradeValues.sem2) }}</div>
                                            </div>
                                            <div class="grade-item grade-calculated" v-if="showCalculatedGradeYear">
                                                <div class="grade-label">Gesamt (1+2)</div>
                                                <div class="grade-value" :class="calcGradeClass(calculatedGradeValues.year)">{{ calcFormatGrade(calculatedGradeValues.year) }}</div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="grade-item grade-calculated" v-if="showCalculatedGradeSem1">
                                                <div class="grade-label">Berechnung</div>
                                                <div class="grade-value" :class="calcGradeClass(calculatedGradeValues.sem1)">{{ calcFormatGrade(calculatedGradeValues.sem1) }}</div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </v-tabs-window-item>

                        <!-- Leistungen Tab -->
                        <v-tabs-window-item value="entries">
                            <header class="feedback-intro">
                                <div>
                                    <span class="feedback-eyebrow">DEIN FEEDBACK</span>
                                    <h2>{{ showBehaviourEnabled ? 'Leistungen & Verhalten' : 'Deine Leistungen' }}</h2>
                                    <p>Alles im Blick. Schritt für Schritt weiter.</p>
                                </div>
                                <v-btn class="feedback-refresh" variant="outlined" prepend-icon="mdi-refresh" :loading="loadingEntries" @click="loadEntries">Aktualisieren</v-btn>
                            </header>
                            <div class="feedback-tools">
                                <div class="feedback-jumps">
                                    <a href="#student-performance" class="feedback-jump"><strong>{{ sortedEntries.length }}</strong><span>Leistungen</span><v-icon size="16">mdi-arrow-down</v-icon></a>
                                    <a v-if="showBehaviourEnabled" href="#student-behaviour" class="feedback-jump feedback-jump--behaviour"><strong>{{ filteredBehaviourEntries.length }}</strong><span>Verhalten</span><v-icon size="16">mdi-arrow-down</v-icon></a>
                                </div>
                                <v-btn-toggle v-if="hasTwoSemesters" v-model="selectedSemester" class="semester-toggle" mandatory density="compact" color="#4056d6" aria-label="Semester für Leistungen und Verhalten">
                                    <v-btn :value="1" size="small">1. Sem</v-btn>
                                    <v-btn :value="2" size="small">2. Sem</v-btn>
                                    <v-btn :value="3" size="small">Gesamt</v-btn>
                                </v-btn-toggle>
                            </div>
                            <!-- Loading State -->
                            <div v-if="loadingEntries" class="courses-loading">
                                <v-progress-circular indeterminate color="#4056d6" />
                                <p>Lade Rückmeldungen...</p>
                            </div>
                            <div v-else class="feedback-grid" :class="{ 'feedback-grid--single': !showBehaviourEnabled }">
                                <section id="student-performance" class="entries-section feedback-panel feedback-panel--assessment" data-testid="student-performance-entries">
                                    <header class="feedback-panel-heading">
                                        <span class="feedback-panel-icon"><v-icon size="23">mdi-chart-timeline-variant-shimmer</v-icon></span>
                                        <div><h3>Leistungen</h3><p>Deine Arbeiten und Bewertungen</p></div>
                                        <v-btn class="feedback-sort" size="small" variant="text" prepend-icon="mdi-sort" @click="toggleSortByType">{{ sortByType ? 'Typ' : 'Datum' }}</v-btn>
                                    </header>

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
                                                    <div class="entry-category-group" :class="[
                                                        item.group.categoryClass,
                                                        item.stripe % 2 === 1 ? 'entry-category-group--alt' : 'entry-category-group--base',
                                                    ]">
                                                        <div v-if="item.group.categoryName || item.group.categoryEvaluationValue" class="entry-category-group-head">
                                                            <v-chip v-if="item.group.categoryName" size="small" color="#4c60cf" variant="tonal" class="entry-category-chip">
                                                                {{ item.group.categoryName }}
                                                            </v-chip>
                                                            <v-chip
                                                                v-if="item.group.categoryEvaluationEnabled && shouldShowCategoryEvaluationValue(item.group.categoryEvaluationValue)"
                                                                size="small"
                                                                :color="categoryEvaluationValueColor(item.group.categoryEvaluationValue)"
                                                                variant="flat"
                                                                class="entry-category-evaluation-chip">
                                                                {{ item.group.categoryEvaluationValue }}
                                                            </v-chip>
                                                        </div>
                                                        <div class="entry-category-group-list">
                                                            <div
                                                                v-for="(entry, entryIndex) in item.group.entries"
                                                                :key="`entry-${item.group.key}-${entry.id || entryIndex}`"
                                                                class="entry-category-group-entry">
                                                                <div class="entry-row" :class="[
                                                                    entryIndex % 2 === 1 ? 'entry-row--alt' : 'entry-row--base',
                                                                    isEntryOpen(entry) ? 'entry-row--open' : '',
                                                                ]">
                                                                    <v-chip v-if="entry.date" size="small" variant="tonal" color="primary">
                                                                        {{ formatDate(entry.date) }}
                                                                    </v-chip>
                                                                    <div class="entry-main text-caption">
                                                                        <div v-if="entryTitle(entry)" class="entry-title-row">
                                                                            <v-icon size="22" :color="getEntryStatusColor(entry)">{{ getEntryIcon(entry.type) }}</v-icon>
                                                                            <div class="entry-title">{{ entryTitle(entry) }}</div>
                                                                        </div>
                                                                        <div v-if="entry.work && (entry.work.description || entry.work.is_group_work)" class="entry-work-toggle">
                                                                            <v-btn size="x-small" variant="tonal" color="primary" @click="toggleEntryExpansion(entry.id)">
                                                                                <v-icon start size="small">{{ expandedEntries[entry.id] ? 'mdi-chevron-up' : 'mdi-chevron-down' }}</v-icon>
                                                                                Aufgaben-Details
                                                                            </v-btn>
                                                                        </div>
                                                                        <div v-if="entry.work && expandedEntries[entry.id]" class="entry-work-details">
                                                                            <div v-if="entry.work.description" class="work-detail-item">
                                                                                <strong>Beschreibung:</strong>
                                                                                <div class="work-description-content" v-html="entry.work.description"></div>
                                                                            </div>
                                                                            <div v-if="entry.work.is_group_work" class="work-detail-item">
                                                                                <strong>Gruppenarbeit:</strong>
                                                                                Ja
                                                                                <span v-if="entry.work.group_size">({{ entry.work.group_size }} Personen)</span>
                                                                            </div>
                                                                            <div v-if="entry.work.is_group_work" class="work-detail-item">
                                                                                <strong>Gruppenmitglieder:</strong>
                                                                                <span v-if="entry.work.group_members && entry.work.group_members.length">
                                                                                    {{ entry.work.group_members.join(', ') }}
                                                                                </span>
                                                                                <span v-else class="text-muted">Keine weiteren Gruppenmitglieder</span>
                                                                            </div>
                                                                        </div>
                                                                        <div v-if="!entry.work && entryDescription(entry)" class="entry-description">
                                                                            {{ entryDescription(entry) }}
                                                                        </div>
                                                                        <div v-if="entryComment(entry)" class="entry-comment">
                                                                            {{ entryComment(entry) }}
                                                                        </div>
                                                                    </div>
                                                                    <v-chip v-if="entry.type" size="small" variant="outlined">
                                                                        {{ entryTypeChipLabel(entry.type) }}
                                                                    </v-chip>
                                                                    <v-chip class="entry-grade" size="small" variant="tonal" :color="entryGradeChipColor(entry)">
                                                                        {{ entry.grade || 'offen' }}
                                                                    </v-chip>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </v-list-item>
                                            </template>
                                        </v-list>
                                    </v-card-text>
                                </v-card>

                                <!-- Empty State -->
                                <div v-else class="feedback-empty">
                                    <v-icon size="28">mdi-notebook-outline</v-icon>
                                    <strong>Noch keine Leistungen</strong>
                                    <p>Für diesen Zeitraum sind noch keine Bewertungen eingetragen.</p>
                                </div>
                                </section>
                                <StudentFeedbackEntries v-if="showBehaviourEnabled" id="student-behaviour" data-testid="student-behaviour-entries" title="Verhalten" kind="behaviour" :entries="behaviourFeedbackEntries" />
                            </div>
                        </v-tabs-window-item>

                        <!-- Weitere Einträge -->
                        <v-tabs-window-item value="additional">
                            <div v-if="loadingEntries" class="courses-loading" role="status">
                                <v-progress-circular indeterminate color="#4056d6" />
                                <p>Lade Einträge...</p>
                            </div>
                            <StudentFeedbackEntries v-else data-testid="student-additional-entries" title="Weitere" kind="additional" :entries="additionalFeedbackEntries" />
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

                                <div v-if="hasTwoSemesters" class="dates-semester-filter">
                                    <v-btn-toggle v-model="selectedSemesterDates" class="semester-toggle" mandatory density="compact" color="#4056d6">
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
                                                    <v-icon size="22" :color="getDateIconColor(dateEntry.date, dateEntry.status)">{{ getDateLeadingIcon(dateEntry.date) }}</v-icon>
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
                                                        <div v-if="dateEntry.adopted_materials && dateEntry.adopted_materials.length" class="date-adopted-materials">
                                                            <div v-for="mat in dateEntry.adopted_materials" :key="mat.id" class="date-adopted-material">
                                                                <div class="date-adopted-item">
                                                                    <v-icon size="14" color="#4caf50" class="mr-1">mdi-check-circle-outline</v-icon>
                                                                    <span>{{ mat.title }}</span>
                                                                    <v-chip v-if="mat.type" size="x-small" variant="tonal" color="primary" class="ml-1">{{ mat.type }}</v-chip>
                                                                </div>
                                                                <div v-if="mat.attachments && mat.attachments.length" class="date-adopted-attachments">
                                                                    <a v-for="att in mat.attachments" :key="att.id" :href="att.preview_url" target="_blank" class="date-adopted-attachment" :title="att.name">
                                                                        <v-icon size="14">mdi-paperclip</v-icon>
                                                                        <span class="text-caption">{{ att.name }}</span>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
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
import { computeStudentGrades, formatGrade as formatGradeValue, gradeClass as gradeClassHelper } from '@/helpers/gradeCalculation'
import {
    normalizeTeachingCategoryEvaluationValueItems,
    teachingCategoryEvaluationColorForValue,
    teachingCategoryEvaluationValueLabels,
} from '@/helpers/teachingCategoryEvaluation'
import ParentAccessPanel from '../../components/ParentAccessPanel.vue'
import StudentNavigationDrawer from '../../components/StudentNavigationDrawer.vue'
import StudentFeedbackEntries from '../../components/StudentFeedbackEntries.vue'
import '../../../../../../css/student.css'

export default {
    components: {
        ParentAccessPanel,
        StudentNavigationDrawer,
        StudentFeedbackEntries,
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

        // Load course details and entries
        await this.loadCourse()
        await this.loadEntries()
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
            entries: [],
            entryAreaBehaviourEntries: [],
            additionalEntries: [],
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

        currentTab: {
            get() {
                const panel = this.$route?.query?.panel

                if (panel === 'behaviour') {
                    return 'entries'
                }

                return ['overview', 'entries', 'additional', 'dates'].includes(panel) ? panel : 'overview'
            },
            set(panel) {
                const selectedPanel = panel === 'behaviour' ? 'entries' : panel

                if (!['overview', 'entries', 'additional', 'dates'].includes(selectedPanel)
                    || this.$route.query.panel === selectedPanel) {
                    return
                }

                this.$router.replace({
                    query: { ...this.$route.query, panel: selectedPanel },
                    hash: this.$route.hash,
                })
            },
        },

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
            if (!this.showBehaviourEnabled) {
                return []
            }

            return [...(this.course?.behaviour_entries || []), ...this.entryAreaBehaviourEntries]
                .sort((first, second) => String(second.date || '').localeCompare(String(first.date || '')))
        },
        filteredBehaviourEntries() {
            return this.filterEntriesBySemester(this.behaviourEntries)
        },
        behaviourFeedbackEntries() {
            return this.filteredBehaviourEntries.map(this.feedbackEntry)
        },
        additionalFeedbackEntries() {
            return this.additionalEntries.map(this.feedbackEntry)
        },
        showBehaviourEnabled() {
            return this.course?.show_behaviour !== false
        },
        showSemesterGrade() {
            return this.course?.teacher_teaching_student_grade_columns?.show_semester_grade !== false
        },
        showBehaviourGrade() {
            return this.showBehaviourEnabled
                && this.course?.teacher_teaching_student_grade_columns?.show_behaviour_grade !== false
        },
        assignedGradePeriods() {
            if (this.hasTwoSemesters) {
                return [
                    { label: '1. Semester', grade: this.course?.sem_1_grade, behaviourGrade: this.course?.behaviour_1_grade },
                    { label: '2. Semester', grade: this.course?.sem_2_grade, behaviourGrade: this.course?.behaviour_2_grade },
                ]
            }

            return [{
                label: this.showSemesterGrade ? 'Semesternote' : 'Verhalten',
                grade: this.course?.sem_grade,
                behaviourGrade: this.course?.behaviour_grade,
            }]
        },

        courseDates() {
            return this.course?.course_dates || []
        },
        courseCategoryEvaluations() {
            return Array.isArray(this.courseStore?.categoryEvaluations) ? this.courseStore.categoryEvaluations : []
        },
        categoryEvaluationValueItems() {
            if (Array.isArray(this.courseStore?.categoryEvaluationValues) && this.courseStore.categoryEvaluationValues.length) {
                return normalizeTeachingCategoryEvaluationValueItems(this.courseStore.categoryEvaluationValues)
            }

            if (!this.currentTeachingSchema) {
                return []
            }

            return normalizeTeachingCategoryEvaluationValueItems(this.currentTeachingSchema?.grading?.category_evaluation_values)
        },
        courseGradingCategories() {
            return Array.isArray(this.courseStore?.gradingCategories) ? this.courseStore.gradingCategories : []
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
        heroLiveTimer() {
            if (!this.courseRemainingLabel) {
                return null
            }

            return {
                title: (this.course?.title || '').toString().trim() || 'Kurs',
                remainingLabel: this.courseRemainingLabel,
                isSimulated: this.isSimulatedCourseTimer(),
            }
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

        courseSchema() {
            return this.course?.teaching_schema || this.currentTeachingSchema || null
        },
        calculatedGrades() {
            if (!this.entries.length || !this.courseSchema) return null
            const works = this.courseSchema?.works || []
            const grading = this.courseSchema?.grading || {}
            const semCount = Number(grading?.semester_count) === 2 ? 2 : 1
            const studentProxy = {
                sem_1_grade: this.course?.sem_1_grade || null,
                sem_2_grade: this.course?.sem_2_grade || null,
                sem_grade: this.course?.sem_grade || null,
            }
            return computeStudentGrades(studentProxy, this.entries, works, grading, semCount, this.semesterBoundary)
        },
        calculatedGradeValues() {
            return {
                sem1: this.calculatedGrades?.sem1 ?? null,
                sem2: this.calculatedGrades?.sem2 ?? null,
                year: this.calculatedGrades?.year ?? null,
            }
        },
        teacherCalculatedGradeColumns() {
            const columns = this.course?.teacher_teaching_student_grade_columns ?? this.course?.teacher_teaching_grade_columns

            return {
                show_sem1: Boolean(columns?.show_sem1),
                show_sem2: Boolean(columns?.show_sem2),
                show_year: Boolean(columns?.show_year),
            }
        },
        showCalculatedGradeSem1() {
            return Boolean(this.teacherCalculatedGradeColumns.show_sem1)
        },
        showCalculatedGradeSem2() {
            return Boolean(this.hasTwoSemesters && this.teacherCalculatedGradeColumns.show_sem2)
        },
        showCalculatedGradeYear() {
            return Boolean(this.hasTwoSemesters && this.teacherCalculatedGradeColumns.show_year)
        },
        showCalculatedGradesSection() {
            return this.showCalculatedGradeSem1 || this.showCalculatedGradeSem2 || this.showCalculatedGradeYear
        },

        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },

        hasTwoSemesters() {
            const schema = this.currentTeachingSchema
            if (!schema) return false
            return Number(schema?.grading?.semester_count) === 2
        },

        currentTeachingSchema() {
            if (this.course?.teaching_schema) {
                return this.course.teaching_schema
            }

            const schemas = Array.isArray(this.user?.teaching_schemas) ? this.user.teaching_schemas : []
            if (!schemas.length) return null

            const courseSchemaId = this.course?.teaching_schema_id
            if (courseSchemaId == null) return null

            return schemas.find((item) => String(item?.id) === String(courseSchemaId)) || null
        },

        schemaWorkTypeLabels() {
            const schema = this.currentTeachingSchema
            if (!schema) return {}

            const works = Array.isArray(schema.works) ? schema.works : []
            return works.reduce((result, work) => {
                if (work?.short_name) {
                    result[String(work.short_name)] = work?.name || String(work.short_name)
                }
                return result
            }, {})
        },

        schemaGradingCategories() {
            if (this.courseGradingCategories.length) {
                return this.courseGradingCategories.map((category, index) => ({
                    name: String(category?.name || '').trim(),
                    index,
                    categoryEvaluationEnabled: Boolean(category?.category_evaluation_enabled),
                    works: (Array.isArray(category?.works) ? category.works : [])
                        .map((work) => String(work || '').trim())
                        .filter((work) => work !== ''),
                })).filter((category) => category.name !== '')
            }

            const schema = this.currentTeachingSchema
            const categories = Array.isArray(schema?.grading?.categories) ? schema.grading.categories : []

            return categories
                .map((category, index) => ({
                    name: String(category?.name || '').trim(),
                    index,
                    categoryEvaluationEnabled: Boolean(category?.category_evaluation_enabled),
                    works: (Array.isArray(category?.works) ? category.works : [])
                        .map((work) => {
                            if (typeof work === 'string') {
                                return String(work).trim()
                            }

                            return String(work?.short_name || '').trim()
                        })
                        .filter((work) => work !== ''),
                }))
                .filter((category) => category.name !== '')
        },
        categoryEvaluationDefaultValue() {
            const storeDefault = String(this.courseStore?.categoryEvaluationDefaultValue || '').trim()
            if (storeDefault) {
                return storeDefault
            }

            const cleanedValues = teachingCategoryEvaluationValueLabels(this.categoryEvaluationValueItems)

            const configuredDefault = String(this.currentTeachingSchema?.grading?.default_category_evaluation_value || '').trim()
            if (configuredDefault && cleanedValues.includes(configuredDefault)) {
                return configuredDefault
            }

            return cleanedValues[0] || ''
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
            return this.filterEntriesBySemester(this.entries)
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
            if (!this.hasTwoSemesters) {
                return this.buildEntryGroups(entries).map((group, index) => ({
                    kind: 'group',
                    key: `group-${group.key}`,
                    group,
                    stripe: index,
                }))
            }

            if (this.selectedSemester !== 3) {
                return this.buildEntryGroups(entries).map((group, index) => ({
                    kind: 'group',
                    key: `group-${group.key}`,
                    group,
                    stripe: index,
                }))
            }

            const boundary = this.normalizeDateKey(this.semesterBoundary)
            if (!boundary) {
                return this.buildEntryGroups(entries).map((group, index) => ({
                    kind: 'group',
                    key: `group-${group.key}`,
                    group,
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
            if (sem2.length) {
                grouped.push({ kind: 'header', key: 'header-sem2', label: '2. Semester' })
            }
            this.buildEntryGroups(sem2).forEach((group, index) => {
                grouped.push({
                    kind: 'group',
                    key: `sem2-group-${group.key || index}`,
                    group,
                    stripe: stripeIndex,
                })
                stripeIndex += 1
            })
            if (sem1.length) {
                grouped.push({ kind: 'header', key: 'header-sem1', label: '1. Semester' })
            }
            this.buildEntryGroups(sem1).forEach((group, index) => {
                grouped.push({
                    kind: 'group',
                    key: `sem1-group-${group.key || index}`,
                    group,
                    stripe: stripeIndex,
                })
                stripeIndex += 1
            })
            return grouped
        },
    },

    watch: {
        currentTab(newTab) {
            if (newTab === 'behaviour') {
                this.currentTab = 'entries'
                return
            }
            if (['entries', 'additional'].includes(newTab)
                && this.entries.length + this.entryAreaBehaviourEntries.length + this.additionalEntries.length === 0
                && !this.loadingEntries) {
                this.loadEntries()
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
        feedbackEntry(entry) {
            return {
                ...entry,
                key: `${entry.category ? 'entry' : 'behaviour'}-${entry.id}`,
                dateLabel: entry.date ? this.formatDate(entry.date) : '',
                typeLabel: entry.category ? this.entryTypeChipLabel(entry.type) : this.getBehaviourTypeLabel(entry.type),
            }
        },
        filterEntriesBySemester(entries) {
            if (this.selectedSemester === 3) {
                return entries
            }

            const boundary = this.normalizeDateKey(this.semesterBoundary)
            if (!boundary) return entries

            return entries.filter((entry) => {
                const date = this.normalizeDateKey(entry.date)
                if (!date) return true
                return this.selectedSemester === 1 ? date < boundary : date >= boundary
            })
        },
        async handleLogout() {
            this.showDrawer = false
            await this.studentStore.logout()
            this.$router.push('/student')
        },

        calcFormatGrade(value) {
            return formatGradeValue(value)
        },
        calcGradeClass(value) {
            return gradeClassHelper(value)
        },
        toggleSortByType() {
            this.sortByType = !this.sortByType
        },

        buildEntryGroups(entries) {
            const groups = []
            const groupsByKey = new Map()

            entries.forEach((entry, index) => {
                const category = this.entryCategory(entry)
                const categoryName = String(category?.name || '').trim()
                const groupKey = categoryName !== '' ? `category-${categoryName}` : `entry-${entry.id || index}`

                if (!groupsByKey.has(groupKey)) {
                    const group = {
                        key: groupKey,
                        categoryName,
                        categoryEvaluationEnabled: Boolean(category?.categoryEvaluationEnabled),
                        categoryEvaluationValue: category?.categoryEvaluationEnabled ? this.entryCategoryEvaluationValue(entry) : '',
                        categoryClass: category ? this.entryCategoryClass(entry) : '',
                        entries: [],
                    }
                    groupsByKey.set(groupKey, group)
                    groups.push(group)
                }

                groupsByKey.get(groupKey).entries.push(entry)
            })

            return groups
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
                if (this.currentTab === 'behaviour') {
                    this.currentTab = 'entries'
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
                    const entries = this.courseStore.entries || []
                    this.entries = entries.filter((entry) => !entry.category || entry.category === 'Benotung')
                    this.entryAreaBehaviourEntries = entries.filter((entry) => entry.category === 'Verhalten')
                    this.additionalEntries = entries.filter((entry) => entry.category === 'Weitere')
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

        entryCategory(entry) {
            const type = String(entry?.type || '').trim()
            if (!type) {
                return null
            }

            return this.schemaGradingCategories.find((category) => category.works.includes(type)) || null
        },

        entryCategoryLabel(entry) {
            return this.entryCategory(entry)?.name || ''
        },

        entryCategoryClass(entry) {
            const category = this.entryCategory(entry)
            if (!category) {
                return ''
            }

            return `entry-category-group--category-${category.index % 4}`
        },

        entrySemester(entry) {
            if (!this.hasTwoSemesters) {
                return 1
            }

            const boundary = this.normalizeDateKey(this.semesterBoundary)
            if (!boundary) {
                return 1
            }

            const entryDate = this.normalizeDateKey(entry?.date)
            if (!entryDate) {
                return 1
            }

            return entryDate >= boundary ? 2 : 1
        },

        entryCategoryEvaluationValue(entry) {
            const category = this.entryCategory(entry)
            if (!category?.categoryEvaluationEnabled) {
                return ''
            }

            const evaluation = this.courseCategoryEvaluations.find((item) => {
                return Number(item?.semester) === this.entrySemester(entry)
                    && String(item?.category_name || '').trim() === category.name
            })

            return String(evaluation?.value || '').trim() || this.categoryEvaluationDefaultValue
        },

        shouldShowCategoryEvaluationValue(value) {
            const normalized = String(value || '').trim()

            return normalized !== '' && normalized !== 'Keine Bewertung'
        },

        categoryEvaluationValueColor(value) {
            return teachingCategoryEvaluationColorForValue(this.categoryEvaluationValueItems, value, '#4f6fb3')
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
        isDatePast(dateValue) {
            const targetKey = this.normalizeDateKey(dateValue)
            if (!targetKey) return false
            const todayKey = this.normalizeDateKey(new Date())
            if (!todayKey) return false

            return targetKey < todayKey
        },
        getDateLeadingIcon(dateValue) {
            return this.isDatePast(dateValue) ? 'mdi-check-circle' : 'mdi-calendar'
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

        getDateIconColor(dateValue, status) {
            if (this.isDatePast(dateValue)) {
                return '#4caf50'
            }
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

.hero-on-air {
    margin-top: 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 20px 28px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
    text-align: center;
}

.on-air-badge {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: #e53935;
    color: #fff;
    border-radius: 999px;
    padding: 10px 32px;
    font-size: 1.36rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.on-air-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    animation: on-air-pulse 1s ease-in-out infinite;
}

@keyframes on-air-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.7); }
}

.on-air-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #1a1a1a;
    line-height: 1.2;
    margin-top: 4px;
}

.on-air-timer {
    font-size: 2rem;
    font-weight: 600;
    color: var(--primary, #fd802e);
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

.entries-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
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

.entry-category-group {
    border: 2px solid rgba(201, 216, 236, 0.95);
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}

.entry-category-group--base {
    background-color: #ffffff;
}

.entry-category-group--alt {
    background-color: #f8fafc;
}

.entry-category-group-head {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    padding: 12px 12px 0;
}

.entry-category-group-list {
    display: flex;
    flex-direction: column;
    margin-top: 12px;
}

.entry-category-group-entry + .entry-category-group-entry {
    border-top: 1px solid rgba(49, 77, 93, 0.12);
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

.entry-category-group--category-0 {
    border-color: rgba(13, 110, 253, 0.28);
}

.entry-category-group--category-1 {
    border-color: rgba(46, 125, 50, 0.28);
}

.entry-category-group--category-2 {
    border-color: rgba(255, 143, 0, 0.34);
}

.entry-category-group--category-3 {
    border-color: rgba(123, 31, 162, 0.28);
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

.entry-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.entry-category-chip {
    font-weight: 600;
}

.entry-category-evaluation-chip {
    margin-left: auto;
    font-weight: 700;
    font-size: 0.95rem;
    min-height: 32px;
    padding-inline: 14px;
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

.date-adopted-materials {
    margin-top: 6px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.date-adopted-item {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2px;
    font-size: 0.88rem;
    color: #314d5d;
    line-height: 1.4;
}

.date-adopted-attachments {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding-left: 22px;
}

.date-adopted-attachment {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #1976d2;
    text-decoration: none;
    font-size: 0.84rem;
}

.date-adopted-attachment:hover {
    text-decoration: underline;
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

.calculated-grades-section {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px dashed rgba(49, 77, 93, 0.2);
}

.calculated-grades-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #546e7a;
    margin-bottom: 6px;
}

.calculated-grades-note {
    margin-bottom: 10px;
}

.calculated-grades-note :deep(.v-alert__content) {
    font-size: 0.78rem;
    line-height: 1.35;
}

.grade-item.grade-calculated {
    background: linear-gradient(135deg, #e8eaf6 0%, #e3f2fd 100%);
    border: 2px solid #7986cb;
    min-width: 130px;
    padding: 12px;
}

.grade-calculated .grade-value {
    font-size: 1.7rem;
    font-weight: 700;
    color: #1e40af;
}

.grade-calculated .grade-label {
    font-size: 0.82rem;
    margin-bottom: 6px;
}

.grade-calculated .grade-value.grade--na {
    color: #c62828;
}

.grade-calculated .grade-value.grade--nb {
    color: #e65100;
}

.grade-calculated .grade-value.grade--empty {
    color: rgba(16, 38, 58, 0.35);
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

    .entries-toolbar-actions {
        width: 100%;
    }

    .entries-toolbar-actions > .v-btn {
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
