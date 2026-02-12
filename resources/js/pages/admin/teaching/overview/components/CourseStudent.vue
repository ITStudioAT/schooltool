<template>
    <ItsGridBox color="primary" title="Schüler:in" icon="mdi-account" class="w-100" v-if="selected_course_student" :disabled="action != ''">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between">
                    <div>
                        <div class="text-body-1 font-weight-medium">{{ selected_course_student.last_name }}, {{ selected_course_student.first_name }}</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ selected_course_student.schoolclass || selected_course_student.class || '–' }}
                        </div>
                    </div>
                    <v-btn color="warning" flat tile @click="closeStudent">Zurück</v-btn>
                </v-card>

                <div v-if="semesterCount === 2" class="d-flex flex-wrap align-center ga-2 mt-2">
                    <v-btn-toggle v-model="activeSemester" mandatory density="compact" color="primary">
                        <v-btn :value="1" size="small">1. Sem</v-btn>
                        <v-btn :value="2" size="small">2. Sem</v-btn>
                        <v-btn :value="3" size="small">1+2</v-btn>
                    </v-btn-toggle>
                </div>

                <v-card variant="outlined" class="mt-4" v-if="!show_entry_form && !show_behaviour_form && !show_star_form">
                    <v-card-text v-if="!is_editing">
                        <div class="text-body-2 course-comment" v-if="selected_comment" v-html="commentHtml"></div>
                        <div class="text-body-2" v-else>Kein Kommentar vorhanden.</div>
                        <div class="w-100 text-right">
                            <v-btn flat tile size="small" color="primary" icon="mdi-pencil" @click="editComment" />
                        </div>
                    </v-card-text>
                    <v-card-text v-else>
                        <v-form ref="form" @submit.prevent="saveComment">
                            <div class="mb-4">
                                <label class="text-caption text-medium-emphasis">Kommentar</label>
                                <ItsRichTextEditor v-model="edit_comment" />
                            </div>

                            <div class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="abortEdit">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="!show_entry_form && !show_behaviour_form && !show_star_form">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-star</v-icon>
                        Sterne
                        <v-chip v-if="studentStars.length" size="x-small" color="amber-darken-2" variant="flat">{{ studentStars.length }}</v-chip>
                        <v-spacer />
                        <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newStarEntry" />
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="star in studentStars" :key="star.id">
                                <div class="star-row d-flex align-center ga-2 w-100">
                                    <v-chip size="x-small" color="amber-darken-2" variant="tonal">
                                        <v-icon start size="14">mdi-star</v-icon>1
                                    </v-chip>
                                    <v-chip v-if="star.date" size="x-small" variant="tonal" color="primary">{{ formatDate(star.date) }}</v-chip>
                                    <div class="star-description text-caption flex-grow-1">{{ star.comment }}</div>
                                    <div class="star-actions d-flex align-center ga-1">
                                        <v-btn icon="mdi-pencil" size="x-small" color="primary" variant="tonal" @click="editStarEntry(star)" />
                                        <v-btn v-if="delete_star_id !== star.id" icon="mdi-delete" size="x-small" color="warning" variant="tonal" @click="delete_star_id = star.id" />
                                        <v-btn v-if="delete_star_id === star.id" icon="mdi-delete-off" size="x-small" color="success" variant="tonal" @click="delete_star_id = null" />
                                        <v-btn v-if="delete_star_id === star.id" icon="mdi-delete" size="x-small" color="error" variant="tonal" @click="deleteStarEntry(star.id)" />
                                    </div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!studentStars.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Noch keine Sterne vorhanden.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="!show_entry_form && !show_behaviour_form && !show_star_form">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-bell</v-icon>
                        Verständigungen
                        <v-chip v-if="filteredNotificationEntries?.length" size="x-small" color="secondary" variant="flat">
                            {{ filteredNotificationEntries.length }}
                        </v-chip>
                        <v-spacer />
                        <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newNotificationEntry" />
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <template v-for="item in filteredNotificationEntriesGrouped" :key="item.key">
                                <v-list-item v-if="item.kind === 'header'">
                                    <div class="entry-row d-flex align-center ga-2 w-100">
                                        <v-divider />
                                        <span class="text-caption text-medium-emphasis text-no-wrap font-weight-bold">{{ item.label }}</span>
                                        <v-divider />
                                    </div>
                                </v-list-item>
                                <v-list-item v-else>
                                    <div class="entry-row d-flex align-center ga-2 w-100">
                                        <v-chip v-if="item.entry.date" size="x-small" variant="tonal" color="primary">
                                            {{ formatDate(item.entry.date) }}
                                        </v-chip>
                                        <v-chip v-if="item.entry.type" size="x-small" variant="outlined" color="secondary">
                                            {{ notificationTypeLabel(item.entry.type) }}
                                        </v-chip>
                                        <v-chip v-if="item.entry.due_date && !item.entry.done_date" size="x-small" variant="tonal" :color="dueDateColor(item.entry.due_date)">
                                            Fällig bis {{ formatDate(item.entry.due_date) }}
                                        </v-chip>
                                        <v-chip v-if="item.entry.done_date" size="x-small" variant="tonal" color="success">
                                            Erledigt {{ formatDate(item.entry.done_date) }}
                                        </v-chip>
                                        <div class="text-caption flex-grow-1">
                                            {{ item.entry.description || '' }}
                                        </div>
                                        <v-btn
                                            v-if="!item.entry.done_date"
                                            icon="mdi-check"
                                            size="x-small"
                                            color="success"
                                            variant="tonal"
                                            @click="completeNotificationToday(item.entry)" />
                                        <v-btn icon="mdi-pencil" size="x-small" color="primary" variant="tonal" @click="editNotificationEntry(item.entry)" />
                                        <v-btn
                                            v-if="delete_notification_id !== item.entry.id"
                                            icon="mdi-delete"
                                            size="x-small"
                                            color="warning"
                                            variant="tonal"
                                            @click="delete_notification_id = item.entry.id" />
                                        <v-btn
                                            v-if="delete_notification_id === item.entry.id"
                                            icon="mdi-delete-off"
                                            size="x-small"
                                            color="success"
                                            variant="tonal"
                                            @click="delete_notification_id = null" />
                                        <v-btn v-if="delete_notification_id === item.entry.id" icon="mdi-delete" size="x-small" color="error" variant="tonal" @click="deleteNotificationEntry(item.entry)" />
                                    </div>
                                </v-list-item>
                            </template>
                            <v-list-item v-if="!filteredNotificationEntries?.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Verständigungen vorhanden.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="!show_entry_form && !show_behaviour_form && !show_star_form">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-clipboard-text</v-icon>
                        Einträge
                        <v-chip v-if="filteredEntries?.length" size="x-small" color="primary" variant="tonal">
                            {{ filteredEntries.length }}
                        </v-chip>
                        <v-spacer />
                        <v-btn size="small" variant="tonal" color="primary" @click="toggleSortByType">
                            {{ sort_by_type ? 'Sort: Typ' : 'Sort: Datum' }}
                        </v-btn>
                        <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newEntry" />
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <template v-for="item in sortedEntriesGrouped" :key="item.key">
                                <v-list-item v-if="item.kind === 'header'">
                                    <div class="d-flex align-center ga-2 w-100">
                                        <v-divider />
                                        <span class="text-caption text-medium-emphasis text-no-wrap font-weight-bold">{{ item.label }}</span>
                                        <v-divider />
                                    </div>
                                </v-list-item>
                                <v-list-item v-else>
                                    <div
                                        class="entry-row d-flex align-center ga-2 w-100"
                                        :class="item.stripe % 2 === 1 ? 'entry-list-row--alt' : 'entry-list-row--base'">
                                        <v-chip v-if="item.entry.date" size="x-small" variant="tonal" color="primary">
                                            {{ formatDate(item.entry.date) }}
                                        </v-chip>
                                        <v-chip v-if="entryIsDisplaySem1ButCountsSem2(item.entry)" size="x-small" variant="tonal" color="warning">
                                            Zählt zu Sem 2
                                        </v-chip>
                                        <v-chip v-if="item.entry.type" size="x-small" variant="outlined">
                                            {{ workTypeLabel(item.entry.type) }}
                                        </v-chip>
                                        <v-chip
                                            v-if="entryWorkTitle(item.entry)"
                                            size="x-small"
                                            variant="outlined"
                                            color="primary"
                                            class="entry-work-title">
                                            {{ entryWorkTitle(item.entry) }}
                                        </v-chip>
                                        <v-chip v-if="entryIsDerivedFromWork(item.entry)" size="x-small" variant="tonal" color="info">
                                            <v-icon start size="12">mdi-lock</v-icon>
                                            Aus Arbeit
                                        </v-chip>
                                        <v-chip v-if="item.entry.grade" size="small" variant="tonal" color="success">
                                            {{ item.entry.grade }}
                                        </v-chip>
                                        <div class="entry-description text-caption flex-grow-1">
                                            {{ item.entry.description || '' }}
                                        </div>
                                        <div class="entry-actions d-flex align-center ga-1">
                                            <v-btn
                                                v-if="entryIsDerivedFromWork(item.entry) && item.entry.teaching_course_work_id"
                                                icon="mdi-open-in-new"
                                                size="x-small"
                                                color="info"
                                                variant="tonal"
                                                @click="jumpToWork(item.entry)" />
                                            <v-btn v-if="!entryIsDerivedFromWork(item.entry)" icon="mdi-pencil" size="x-small" color="primary" variant="tonal" @click="editEntry(item.entry)" />
                                            <v-btn
                                                v-if="!entryIsDerivedFromWork(item.entry) && delete_entry_id !== item.entry.id"
                                                icon="mdi-delete"
                                                size="x-small"
                                                color="warning"
                                                variant="tonal"
                                                @click="delete_entry_id = item.entry.id" />
                                            <v-btn
                                                v-if="!entryIsDerivedFromWork(item.entry) && delete_entry_id === item.entry.id"
                                                icon="mdi-delete-off"
                                                size="x-small"
                                                color="success"
                                                variant="tonal"
                                                @click="delete_entry_id = null" />
                                            <v-btn v-if="!entryIsDerivedFromWork(item.entry) && delete_entry_id === item.entry.id" icon="mdi-delete" size="x-small" color="error" variant="tonal" @click="deleteEntry(item.entry)" />
                                        </div>
                                    </div>
                                </v-list-item>
                            </template>
                            <v-list-item v-if="!filteredEntries?.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Einträge vorhanden.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                    <v-divider v-if="hasAuswertungContent" />
                    <v-card-text v-if="hasAuswertungContent" class="py-2">
                        <div class="d-flex align-center justify-space-between mb-2">
                            <div class="text-subtitle-2">Auswertung</div>
                            <v-btn
                                :icon="show_auswertung ? 'mdi-eye' : 'mdi-eye-off'"
                                size="x-small"
                                variant="tonal"
                                color="primary"
                                @click="show_auswertung = !show_auswertung" />
                        </div>
                        <v-list v-if="show_auswertung" density="compact">
                            <template v-if="showSemester1Auswertung">
                                <v-list-item>
                                    <div class="d-flex align-center ga-2 w-100">
                                        <div class="auswertung-section-header">
                                            <v-icon size="16">{{ semesterCount === 2 ? 'mdi-numeric-1-circle' : 'mdi-chart-box' }}</v-icon>
                                            <span>{{ semesterCount === 2 ? 'Semester 1' : 'Auswertung' }}</span>
                                        </div>
                                        <v-spacer />
                                        <v-chip size="small" variant="tonal" color="success">
                                            Note: {{ semesterCount === 2 ? (selected_course_student?.sem_1_grade || '–') : (selected_course_student?.sem_grade || '–') }}
                                        </v-chip>
                                    </div>
                                </v-list-item>
                                <template v-for="cat in semester1Groups" :key="`sem1-cat-${cat.name}`">
                                    <v-list-item>
                                    <div class="d-flex align-center ga-2 w-100">
                                        <v-list-item-title class="text-subtitle-2" :class="!cat.rows.length ? 'text-warning' : ''">
                                            {{ cat.name }}
                                        </v-list-item-title>
                                        <v-chip size="x-small" variant="outlined">{{ cat.weight }}%</v-chip>
                                        <v-spacer />
                                        <div v-if="cat.value != null || cat.grade" class="text-caption text-medium-emphasis">
                                            Bewertung: {{ cat.value != null ? formatTwoDecimals(cat.value) : formatTwoDecimals(cat.grade) }}
                                        </div>
                                    </div>
                                    </v-list-item>
                                    <v-list-item v-if="categoryCalculationLine(cat)">
                                        <div class="text-caption text-medium-emphasis w-100">
                                            {{ categoryCalculationLine(cat) }}
                                        </div>
                                    </v-list-item>
                                    <v-list-item v-if="categoryMissingLine(cat)">
                                        <div class="text-caption text-warning w-100">
                                            {{ categoryMissingLine(cat) }}
                                        </div>
                                    </v-list-item>
                                    <v-list-item v-for="row in cat.rows" :key="`sem1-cat-${cat.name}-${row.key}`">
                                        <div class="d-flex align-center ga-2 w-100">
                                            <v-chip v-if="row.type" size="x-small" variant="outlined">
                                                {{ workTypeLabel(row.type) }}
                                            </v-chip>
                                            <v-spacer />
                                            <v-chip v-if="row.sum != null" size="x-small" variant="tonal" color="primary">Σ {{ row.sum }}</v-chip>
                                            <v-chip v-if="row.grade" size="x-small" variant="tonal" color="primary">{{ row.grade }}</v-chip>
                                            <v-chip v-if="row.date" size="x-small" variant="tonal" color="primary">
                                                {{ formatDate(row.date) }}
                                            </v-chip>
                                            <v-chip v-if="row.value != null" size="x-small" variant="tonal" color="primary">
                                                {{ row.value }}
                                            </v-chip>
                                        </div>
                                    </v-list-item>
                                </template>
                                <v-list-item v-if="semester1Total != null">
                                    <div class="d-flex align-center ga-2 w-100">
                                        <v-list-item-title class="text-subtitle-2">{{ semesterCount === 2 ? 'Gesamtbewertung Sem 1' : 'Gesamtbewertung' }}</v-list-item-title>
                                        <v-spacer />
                                        <div class="text-subtitle-2" :class="semester1HasMissingCategory ? 'text-warning' : 'text-medium-emphasis'">
                                            Bewertung: {{ formatTwoDecimals(semester1Total) }}
                                        </div>
                                    </div>
                                </v-list-item>
                            </template>

                            <template v-if="showSemester2Auswertung">
                                <v-list-item>
                                    <div class="d-flex align-center ga-2 w-100">
                                        <div class="auswertung-section-header">
                                            <v-icon size="16">mdi-numeric-2-circle</v-icon>
                                            <span>Semester 2</span>
                                        </div>
                                        <v-spacer />
                                        <v-chip size="small" variant="tonal" color="success">
                                            Note: {{ selected_course_student?.sem_2_grade || '–' }}
                                        </v-chip>
                                    </div>
                                </v-list-item>
                                <template v-for="cat in semester2Groups" :key="`sem2-cat-${cat.name}`">
                                    <v-list-item>
                                    <div class="d-flex align-center ga-2 w-100">
                                        <v-list-item-title class="text-subtitle-2" :class="!cat.rows.length ? 'text-warning' : ''">
                                            {{ cat.name }}
                                        </v-list-item-title>
                                        <v-chip size="x-small" variant="outlined">{{ cat.weight }}%</v-chip>
                                        <v-spacer />
                                        <div v-if="cat.value != null || cat.grade" class="text-caption text-medium-emphasis">
                                            Bewertung: {{ cat.value != null ? formatTwoDecimals(cat.value) : formatTwoDecimals(cat.grade) }}
                                        </div>
                                        </div>
                                    </v-list-item>
                                    <v-list-item v-if="categoryCalculationLine(cat)">
                                        <div class="text-caption text-medium-emphasis w-100">
                                            {{ categoryCalculationLine(cat) }}
                                        </div>
                                    </v-list-item>
                                    <v-list-item v-if="categoryMissingLine(cat)">
                                        <div class="text-caption text-warning w-100">
                                            {{ categoryMissingLine(cat) }}
                                        </div>
                                    </v-list-item>
                                    <v-list-item v-for="row in cat.rows" :key="`sem2-cat-${cat.name}-${row.key}`">
                                        <div class="d-flex align-center ga-2 w-100">
                                            <v-chip v-if="row.type" size="x-small" variant="outlined">
                                                {{ workTypeLabel(row.type) }}
                                            </v-chip>
                                            <v-spacer />
                                            <v-chip v-if="row.sum != null" size="x-small" variant="tonal" color="primary">Σ {{ row.sum }}</v-chip>
                                            <v-chip v-if="row.grade" size="x-small" variant="tonal" color="primary">{{ row.grade }}</v-chip>
                                            <v-chip v-if="row.date" size="x-small" variant="tonal" color="primary">
                                                {{ formatDate(row.date) }}
                                            </v-chip>
                                            <v-chip v-if="row.value != null" size="x-small" variant="tonal" color="primary">
                                                {{ row.value }}
                                            </v-chip>
                                        </div>
                                    </v-list-item>
                                </template>
                                <v-list-item v-if="semester2Total != null">
                                    <div class="d-flex align-center ga-2 w-100">
                                        <v-list-item-title class="text-subtitle-2">Gesamtbewertung Sem 2</v-list-item-title>
                                        <v-spacer />
                                        <div class="text-subtitle-2" :class="semester2HasMissingCategory ? 'text-warning' : 'text-medium-emphasis'">
                                            Bewertung: {{ formatTwoDecimals(semester2Total) }}
                                        </div>
                                    </div>
                                </v-list-item>
                            </template>

                            <v-list-item v-if="semesterWeightedGrade">
                                <div class="w-100">
                                    <div class="d-flex align-center ga-2 w-100">
                                        <div class="auswertung-section-header auswertung-section-header--sum">
                                            <v-icon size="16">mdi-calculator-variant</v-icon>
                                            <span>Summe Semester 1+2</span>
                                        </div>
                                        <v-spacer />
                                        <v-chip size="small" color="secondary" variant="flat">
                                            Gesamtbewertung: {{ formatTwoDecimals(semesterWeightedGrade.value) }}
                                        </v-chip>
                                    </div>
                                    <div class="sum-formula mt-2">
                                        <div class="sum-formula-line">
                                            <v-chip size="small" variant="tonal" color="primary">Sem 1 {{ semesterWeightedGrade.sem1Weight }}%</v-chip>
                                            <span>{{ formatTwoDecimals(semesterWeightedGrade.sem1Value) }}</span>
                                        </div>
                                        <div class="sum-formula-line">
                                            <v-chip size="small" variant="tonal" color="primary">Sem 2 {{ semesterWeightedGrade.sem2Weight }}%</v-chip>
                                            <span>{{ formatTwoDecimals(semesterWeightedGrade.sem2Value) }}</span>
                                        </div>
                                        <div class="sum-formula-line text-medium-emphasis">
                                            <span>
                                                {{ formatTwoDecimals(semesterWeightedGrade.sem1Value) }} * {{ formatTwoDecimals(semesterWeightedGrade.sem1Weight / 100) }} +
                                                {{ formatTwoDecimals(semesterWeightedGrade.sem2Value) }} * {{ formatTwoDecimals(semesterWeightedGrade.sem2Weight / 100) }}
                                            </span>
                                            <span>=</span>
                                            <strong>{{ formatTwoDecimals(semesterWeightedGrade.value) }}</strong>
                                        </div>
                                    </div>
                                </div>                                
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="!show_entry_form && !show_behaviour_form && !show_star_form">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-account-alert</v-icon>
                        Verhaltens-Einträge
                        <v-chip v-if="filteredBehaviourEntries?.length" size="x-small" color="warning" variant="flat">
                            {{ filteredBehaviourEntries.length }}
                        </v-chip>
                        <v-spacer />
                        <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newBehaviourEntry" />
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <template v-for="item in filteredBehaviourEntriesGrouped" :key="item.key">
                                <v-list-item v-if="item.kind === 'header'">
                                    <div class="d-flex align-center ga-2 w-100">
                                        <v-divider />
                                        <span class="text-caption text-medium-emphasis text-no-wrap font-weight-bold">{{ item.label }}</span>
                                        <v-divider />
                                    </div>
                                </v-list-item>
                                <v-list-item v-else>
                                    <div class="behaviour-row d-flex align-center ga-2 w-100">
                                        <v-chip v-if="item.entry.date" size="x-small" variant="tonal" color="primary">
                                            {{ formatDate(item.entry.date) }}
                                        </v-chip>
                                        <v-chip v-if="item.entry.due_date" size="x-small" variant="tonal" color="error">
                                            Fällig bis {{ formatDate(item.entry.due_date) }}
                                        </v-chip>
                                        <v-chip v-if="item.entry.done_date" size="x-small" variant="tonal" color="success">
                                            Erledigt {{ formatDate(item.entry.done_date) }}
                                        </v-chip>
                                        <v-chip v-if="item.entry.type" size="x-small" variant="outlined" color="warning">
                                            {{ behaviourTypeLabel(item.entry.type) }}
                                        </v-chip>
                                        <div class="behaviour-description text-caption flex-grow-1">
                                            {{ item.entry.description || '' }}
                                        </div>
                                        <div class="behaviour-actions d-flex align-center ga-1">
                                            <v-btn icon="mdi-pencil" size="x-small" color="primary" variant="tonal" @click="editBehaviourEntry(item.entry)" />
                                            <v-btn
                                                v-if="delete_behaviour_id !== item.entry.id"
                                                icon="mdi-delete"
                                                size="x-small"
                                                color="warning"
                                                variant="tonal"
                                                @click="delete_behaviour_id = item.entry.id" />
                                            <v-btn
                                                v-if="delete_behaviour_id === item.entry.id"
                                                icon="mdi-delete-off"
                                                size="x-small"
                                                color="success"
                                                variant="tonal"
                                                @click="delete_behaviour_id = null" />
                                            <v-btn v-if="delete_behaviour_id === item.entry.id" icon="mdi-delete" size="x-small" color="error" variant="tonal" @click="deleteBehaviourEntry(item.entry)" />
                                        </div>
                                    </div>
                                </v-list-item>
                            </template>
                            <v-list-item v-if="!filteredBehaviourEntries?.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Verhaltens-Einträge vorhanden.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="!show_entry_form && !show_behaviour_form && !show_star_form">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-school</v-icon>
                        Semesternoten
                        <v-spacer />
                        <v-btn v-if="!is_editing_grades" icon="mdi-pencil" size="x-small" color="primary" variant="flat" @click="editGrades" />
                        <v-btn v-if="is_editing_grades" icon="mdi-check" size="x-small" color="success" variant="flat" @click="saveGrades" />
                        <v-btn v-if="is_editing_grades" icon="mdi-close" size="x-small" color="warning" variant="flat" @click="is_editing_grades = false" />
                    </v-card-title>
                    <v-divider />
                    <v-card-text>
                        <template v-if="!is_editing_grades">
                            <div class="d-flex flex-wrap ga-2" v-if="semesterCount === 2">
                                <v-chip variant="flat" :color="selected_course_student.sem_1_grade ? 'success' : 'default'">
                                    1. Sem: {{ selected_course_student.sem_1_grade || '–' }}
                                </v-chip>
                                <v-chip variant="flat" :color="selected_course_student.sem_2_grade ? 'success' : 'default'">
                                    2. Sem: {{ selected_course_student.sem_2_grade || '–' }}
                                </v-chip>
                            </div>
                            <div class="d-flex flex-wrap ga-2" v-else>
                                <v-chip variant="flat" :color="selected_course_student.sem_grade ? 'success' : 'default'">
                                    Note: {{ selected_course_student.sem_grade || '–' }}
                                </v-chip>
                            </div>
                        </template>
                        <template v-else>
                            <div class="d-flex flex-wrap ga-2" v-if="semesterCount === 2">
                                <v-text-field v-model="grade_form.sem_1_grade" label="1. Semester" density="compact" hide-details class="flex-grow-1" />
                                <v-text-field v-model="grade_form.sem_2_grade" label="2. Semester" density="compact" hide-details class="flex-grow-1" />
                            </div>
                            <div v-else>
                                <v-text-field v-model="grade_form.sem_grade" label="Semesternote" density="compact" hide-details />
                            </div>
                        </template>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="!show_entry_form && !show_behaviour_form && !show_star_form">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-account-alert</v-icon>
                        Verhaltensnoten
                        <v-spacer />
                        <v-btn v-if="!is_editing_behaviour_grades" icon="mdi-pencil" size="x-small" color="primary" variant="flat" @click="editBehaviourGrades" />
                        <v-btn v-if="is_editing_behaviour_grades" icon="mdi-check" size="x-small" color="success" variant="flat" @click="saveBehaviourGrades" />
                        <v-btn v-if="is_editing_behaviour_grades" icon="mdi-close" size="x-small" color="warning" variant="flat" @click="is_editing_behaviour_grades = false" />
                    </v-card-title>
                    <v-divider />
                    <v-card-text>
                        <template v-if="!is_editing_behaviour_grades">
                            <div class="d-flex flex-wrap ga-2" v-if="semesterCount === 2">
                                <v-chip variant="flat" :color="selected_course_student.behaviour_1_grade ? 'success' : 'default'">
                                    1. Sem: {{ selected_course_student.behaviour_1_grade || '–' }}
                                </v-chip>
                                <v-chip variant="flat" :color="selected_course_student.behaviour_2_grade ? 'success' : 'default'">
                                    2. Sem: {{ selected_course_student.behaviour_2_grade || '–' }}
                                </v-chip>
                            </div>
                            <div class="d-flex flex-wrap ga-2" v-else>
                                <v-chip variant="flat" :color="selected_course_student.behaviour_grade ? 'success' : 'default'">
                                    Note: {{ selected_course_student.behaviour_grade || '–' }}
                                </v-chip>
                            </div>
                        </template>
                        <template v-else>
                            <div class="d-flex flex-wrap ga-2" v-if="semesterCount === 2">
                                <v-text-field v-model="behaviour_grade_form.behaviour_1_grade" label="1. Semester" density="compact" hide-details class="flex-grow-1" />
                                <v-text-field v-model="behaviour_grade_form.behaviour_2_grade" label="2. Semester" density="compact" hide-details class="flex-grow-1" />
                            </div>
                            <div v-else>
                                <v-text-field v-model="behaviour_grade_form.behaviour_grade" label="Verhaltensnote" density="compact" hide-details />
                            </div>
                        </template>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="show_behaviour_form">
                    <v-card-text>
                        <v-form ref="behaviourForm" @submit.prevent="saveBehaviourEntry">
                            <div class="text-subtitle-1 mb-2">{{ behaviour_form.id ? entryFormTitleEdit : entryFormTitleNew }}</div>
                            <v-select v-model="behaviour_form.type" label="Typ" :items="entryTypeItems" item-title="title" item-value="value" clearable />
                            <v-date-input v-model="behaviour_form.date" label="Datum" />
                            <v-textarea v-model="behaviour_form.description" label="Beschreibung" rows="3" :counter="1024" :maxlength="1024" />
                            <div v-if="showDueFields" class="d-flex flex-column ga-2">
                                <v-switch v-model="behaviour_form.is_due" label="Fällig" color="warning" hide-details />
                                <template v-if="behaviour_form.is_due">
                                    <v-date-input v-model="behaviour_form.due_date" label="Fällig bis" />
                                    <div class="d-flex flex-wrap ga-1 mt-1">
                                        <v-chip size="x-small" variant="outlined" class="cursor-pointer" @click="setDueInAWeek">In einer Woche</v-chip>
                                        <v-chip
                                            size="x-small"
                                            variant="outlined"
                                            class="cursor-pointer"
                                            :disabled="!nextCourseLessonDate"
                                            @click="setDueNextLesson">
                                            Nächste Unterrichtseinheit<span v-if="nextCourseLessonDate">: {{ formatDate(nextCourseLessonDate) }}</span>
                                        </v-chip>
                                    </div>
                                    <v-checkbox v-model="behaviour_form.is_done" label="Erledigt" color="success" hide-details density="compact" class="mt-1" />
                                    <v-date-input v-if="behaviour_form.is_done" v-model="behaviour_form.done_date" label="Erledigt am" />
                                </template>
                            </div>
                            <v-alert v-if="behaviourFormFrontendError" type="warning" class="mt-2">
                                {{ behaviourFormFrontendError }}
                            </v-alert>

                            <div class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="abortBehaviourEntry">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit" :disabled="!canSaveBehaviourForm">{{ behaviour_form.id ? 'Aktualisieren' : 'Speichern' }}</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="show_entry_form">
                    <v-card-text>
                        <v-form ref="entryForm" @submit.prevent="saveEntry">
                            <div class="text-subtitle-1 mb-2">Neuer Eintrag</div>
                            <v-select v-model="entry_form.type" label="Typ" :items="workTypeItems" item-title="title" item-value="value" clearable />
                            <v-select v-model="entry_form.grade" label="Note" :items="gradeItemsForType" item-title="title" item-value="value" clearable />
                            <v-date-input v-model="entry_form.date" label="Datum" />
                            <v-textarea v-model="entry_form.description" label="Beschreibung" rows="3" :counter="1024" :maxlength="1024" />

                            <div class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="abortEntry">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit">{{ entry_form.id ? 'Aktualisieren' : 'Speichern' }}</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="show_star_form">
                    <v-card-text>
                        <v-form ref="starForm" @submit.prevent="saveStarEntry">
                            <div class="text-subtitle-1 mb-2">{{ star_form.id ? 'Stern bearbeiten' : 'Stern vergeben' }}</div>
                            <v-date-input v-model="star_form.date" label="Datum" />
                            <v-textarea v-model="star_form.comment" label="Kommentar" rows="3" :counter="1024" :maxlength="1024" />
                            <v-alert v-if="starFormFrontendError" type="warning" class="mt-2">{{ starFormFrontendError }}</v-alert>
                            <div class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="abortStarEntry">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit" :disabled="!!starFormFrontendError">{{ star_form.id ? 'Aktualisieren' : 'Speichern' }}</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { parseLocalDate } from '@/helpers/date'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'

export default {
    components: { ItsGridBox, ItsRichTextEditor },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.courseWorkStore = useCourseWorkStore()
        this.entryStore = useCourseStudentEntryStore()
        this.behaviourEntryStore = useCourseBehaviourEntryStore()
        this.teachingStore = useTeachingStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        this.activeSemester = Number(this.config?.user?.teaching_active_semester) || 1
        await Promise.all([
            this.loadEntries(),
            this.loadBehaviourEntries(),
            this.selected_course?.id ? this.courseWorkStore.index(this.selected_course.id) : Promise.resolve(true),
        ])
    },

    data() {
        return {
            adminStore: null,
            courseStore: null,
            courseWorkStore: null,
            entryStore: null,
            behaviourEntryStore: null,
            teachingStore: null,
            is_editing: false,
            edit_comment: '',
            is_editing_grades: false,
            grade_form: { sem_1_grade: '', sem_2_grade: '', sem_grade: '' },
            show_entry_form: false,
            entry_form: this.emptyEntryForm(),
            sort_by_type: false,
            delete_entry_id: null,
            show_auswertung: false,
            activeSemester: null,
            show_behaviour_form: false,
            behaviour_form: this.emptyBehaviourForm(),
            show_star_form: false,
            star_form: this.emptyStarForm(),
            delete_star_id: null,
            delete_behaviour_id: null,
            delete_notification_id: null,
            is_editing_behaviour_grades: false,
            behaviour_grade_form: { behaviour_1_grade: '', behaviour_2_grade: '', behaviour_grade: '' },
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['selected_course', 'selected_course_id', 'selected_course_student', 'show_works', 'show_infos', 'show_dates']),
        ...mapWritableState(useCourseStudentEntryStore, ['entries']),
        ...mapWritableState(useTeachingStore, ['settings']),
        behaviourEntries() {
            return (this.behaviourEntryStore?.entries || []).filter((entry) => (entry.kind || 'behaviour') === 'behaviour')
        },
        notificationEntries() {
            return (this.behaviourEntryStore?.entries || []).filter((entry) => entry.kind === 'notification')
        },
        filteredBehaviourEntries() {
            if (this.semesterCount === 1) return this.behaviourEntries
            const semester = this.activeSemester
            if (!semester || semester === 3) return this.behaviourEntries
            const boundary = this.displaySem2Boundary()
            if (!boundary) return this.behaviourEntries
            return this.behaviourEntries.filter((entry) => {
                if (!entry.date) return true
                const d = this.normalizeDateKey(entry.date)
                if (!d) return true
                if (semester === 1) return d < boundary
                if (semester === 2) return d >= boundary
                return true
            })
        },
        filteredBehaviourEntriesGrouped() {
            const entries = this.filteredBehaviourEntries
            if (this.semesterCount !== 2 || this.activeSemester !== 3) {
                return entries.map((e) => ({ kind: 'entry', key: `entry-${e.id}`, entry: e }))
            }
            const boundary = this.displaySem2Boundary()
            if (!boundary) {
                return entries.map((e) => ({ kind: 'entry', key: `entry-${e.id}`, entry: e }))
            }
            const sem1 = entries.filter((e) => {
                if (!e.date) return true
                const d = this.normalizeDateKey(e.date)
                return !d || d < boundary
            })
            const sem2 = entries.filter((e) => {
                if (!e.date) return false
                const d = this.normalizeDateKey(e.date)
                return !!d && d >= boundary
            })
            const result = []
            result.push({ kind: 'header', key: 'header-sem2', label: '2. Semester' })
            sem2.forEach((e) => result.push({ kind: 'entry', key: `entry-${e.id}`, entry: e }))
            result.push({ kind: 'header', key: 'header-sem1', label: '1. Semester' })
            sem1.forEach((e) => result.push({ kind: 'entry', key: `entry-${e.id}`, entry: e }))
            return result
        },
        filteredNotificationEntries() {
            if (this.semesterCount === 1) return this.notificationEntries
            const semester = this.activeSemester
            if (!semester || semester === 3) return this.notificationEntries
            const boundary = this.displaySem2Boundary()
            if (!boundary) return this.notificationEntries
            return this.notificationEntries.filter((entry) => {
                if (!entry.date) return true
                const d = this.normalizeDateKey(entry.date)
                if (!d) return true
                if (semester === 1) return d < boundary
                if (semester === 2) return d >= boundary
                return true
            })
        },
        filteredNotificationEntriesGrouped() {
            const entries = this.filteredNotificationEntries
            if (this.semesterCount !== 2 || this.activeSemester !== 3) {
                return entries.map((e) => ({ kind: 'entry', key: `notification-${e.id}`, entry: e }))
            }
            const boundary = this.displaySem2Boundary()
            if (!boundary) {
                return entries.map((e) => ({ kind: 'entry', key: `notification-${e.id}`, entry: e }))
            }
            const sem1 = entries.filter((e) => {
                if (!e.date) return true
                const d = this.normalizeDateKey(e.date)
                return !d || d < boundary
            })
            const sem2 = entries.filter((e) => {
                if (!e.date) return false
                const d = this.normalizeDateKey(e.date)
                return !!d && d >= boundary
            })
            const result = []
            result.push({ kind: 'header', key: 'notification-header-sem2', label: '2. Semester' })
            sem2.forEach((e) => result.push({ kind: 'entry', key: `notification-${e.id}`, entry: e }))
            result.push({ kind: 'header', key: 'notification-header-sem1', label: '1. Semester' })
            sem1.forEach((e) => result.push({ kind: 'entry', key: `notification-${e.id}`, entry: e }))
            return result
        },
        teachingBehaviour() {
            return this.settings?.teaching_behaviour || []
        },
        teachingNotifications() {
            return this.settings?.teaching_notifications || []
        },
        behaviourTypeItems() {
            return this.teachingBehaviour.map((b) => ({
                title: `${b.short_name} - ${b.name}`,
                value: b.short_name,
            }))
        },
        notificationTypeItems() {
            return this.teachingNotifications.map((n) => ({
                title: `${n.short_name} - ${n.name}`,
                value: n.short_name,
            }))
        },
        entryTypeItems() {
            return this.behaviour_form.kind === 'notification' ? this.notificationTypeItems : this.behaviourTypeItems
        },
        entryFormTitleNew() {
            return this.behaviour_form.kind === 'notification' ? 'Neuer Verständigungs-Eintrag' : 'Neuer Verhaltens-Eintrag'
        },
        entryFormTitleEdit() {
            return this.behaviour_form.kind === 'notification' ? 'Verständigungs-Eintrag ändern' : 'Verhaltens-Eintrag ändern'
        },
        showDueFields() {
            return this.behaviour_form.kind === 'notification'
        },
        behaviourFormFrontendError() {
            if (!this.behaviour_form?.type) return 'Bitte einen Typ auswählen.'
            if (!this.showDueFields) return ''
            if (this.behaviour_form?.is_due && !this.behaviour_form?.due_date) return 'Bitte "Fällig bis" eingeben.'
            if (this.behaviour_form?.is_due && this.behaviour_form?.is_done && !this.behaviour_form?.done_date) return 'Bitte "Erledigt am" eingeben.'
            return ''
        },
        canSaveBehaviourForm() {
            return !this.behaviourFormFrontendError
        },
        studentStars() {
            return this.selected_course_student?.stars || []
        },
        starFormFrontendError() {
            if (!this.star_form?.comment?.trim()) return 'Bitte einen Kommentar eingeben.'
            return ''
        },
        selected_comment() {
            return this.selected_course_student?.comment || ''
        },
        commentHtml() {
            const text = this.selected_comment
            if (!text) return ''
            if (text.includes('<p>') || text.includes('<br')) return text
            return text
                .split('\n')
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
        teachingSchemas() {
            return this.config?.user?.teaching_schemas || this.settings?.teaching_schemas || []
        },
        selectedSchema() {
            const schemaId = this.selected_course?.teaching_schema_id
            if (!schemaId) return null
            return this.teachingSchemas.find((schema) => schema.id === schemaId) || null
        },
        semesterCount() {
            const grading = this.selectedSchema?.grading || {}
            return Number(grading.semester_count) || 1
        },
        schoolSem2StartDate() {
            return this.config?.selected_schoolyear?.sem_2_start || null
        },
        countSem2StartDate() {
            return this.config?.user?.teaching_count_for_semester_2_date || this.schoolSem2StartDate || null
        },
        nextCourseLessonDate() {
            const dates = this.selected_course?.course_dates || []
            if (!dates.length) return null
            const todayKey = this.toDateString(new Date())
            const upcoming = dates
                .map((d) => this.normalizeDateKey(d?.date))
                .filter((d) => !!d && d >= todayKey)
                .sort((a, b) => a.localeCompare(b))[0]
            return upcoming || null
        },
        hasDifferentSem2CountDate() {
            const countBoundary = this.countSem2Boundary()
            const displayBoundary = this.displaySem2Boundary()
            return !!countBoundary && !!displayBoundary && countBoundary !== displayBoundary
        },
        filteredEntries() {
            if (this.semesterCount === 1) return this.entries || []
            const semester = this.activeSemester
            if (!semester || semester === 3) return this.entries || []
            const boundary = this.displaySem2Boundary()
            if (!boundary) return this.entries || []
            return (this.entries || []).filter((entry) => {
                if (!entry.date) return true
                const d = this.normalizeDateKey(entry.date)
                if (!d) return true
                if (semester === 1) return d < boundary
                if (semester === 2) return d >= boundary
                return true
            })
        },
        teachingWorks() {
            return this.selectedSchema?.works || []
        },
        workTypeItems() {
            return this.teachingWorks.map((work) => ({
                title: `${work.short_name} - ${work.name}`,
                value: work.short_name,
            }))
        },
        gradeItemsForType() {
            const selectedType = this.entry_form.type
            if (!selectedType) return []
            const work = this.teachingWorks.find((w) => w.short_name === selectedType)
            const grades = work?.grades || []
            return grades.map((grade) => ({
                title: grade.name ? `${grade.grade} (${grade.name})` : grade.grade,
                value: grade.grade,
            }))
        },
        categoryGroups() {
            return this.buildCategoryGroups(this.filteredEntries || [])
        },
        semester1Groups() {
            if (this.semesterCount !== 2) return this.categoryGroups
            return this.buildCategoryGroups(this.entriesForSemester(this.entries || [], 1))
        },
        semester2Groups() {
            if (this.semesterCount !== 2) return []
            return this.buildCategoryGroups(this.entriesForSemester(this.entries || [], 2))
        },
        semester1Total() {
            return this.totalFromCategoryGroups(this.semester1Groups)
        },
        semester2Total() {
            return this.totalFromCategoryGroups(this.semester2Groups)
        },
        semester1HasMissingCategory() {
            return this.semester1Groups.some((cat) => !cat.rows || !cat.rows.length)
        },
        semester2HasMissingCategory() {
            return this.semester2Groups.some((cat) => !cat.rows || !cat.rows.length)
        },
        showSemester1Auswertung() {
            if (this.semesterCount !== 2) return true
            return this.activeSemester === 1 || this.activeSemester === 3
        },
        showSemester2Auswertung() {
            return this.semesterCount === 2 && (this.activeSemester === 2 || this.activeSemester === 3)
        },
        hasAuswertungContent() {
            if (this.semesterCount !== 2) return this.categoryGroups.length > 0
            if (this.activeSemester === 1) return this.semester1Groups.length > 0
            if (this.activeSemester === 2) return this.semester2Groups.length > 0 || !!this.semesterWeightedGrade
            if (this.activeSemester === 3) return this.semester1Groups.length > 0 || this.semester2Groups.length > 0 || !!this.semesterWeightedGrade
            return false
        },
        semesterWeightedGrade() {
            if (this.semesterCount !== 2) return null
            if (this.activeSemester !== 2 && this.activeSemester !== 3) return null

            const grading = this.selectedSchema?.grading || {}
            const sem1Weight = parseFloat(grading.semester_1_weight)
            const sem2Weight = parseFloat(grading.semester_2_weight)
            const w1 = Number.isNaN(sem1Weight) ? 50 : sem1Weight
            const w2 = Number.isNaN(sem2Weight) ? 50 : sem2Weight
            const totalWeight = w1 + w2
            if (totalWeight <= 0) return null

            const sem1Entries = this.entriesForSemester(this.entries || [], 1)
            const sem2Entries = this.entriesForSemester(this.entries || [], 2)
            const sem1Value = this.totalFromCategoryGroups(this.buildCategoryGroups(sem1Entries))
            const sem2Value = this.totalFromCategoryGroups(this.buildCategoryGroups(sem2Entries))

            if (sem1Value == null || sem2Value == null) return null

            const value = Number((((sem1Value * w1) + (sem2Value * w2)) / totalWeight).toFixed(2))
            return {
                sem1Value,
                sem2Value,
                sem1Weight: w1,
                sem2Weight: w2,
                value,
            }
        },
        sortedEntries() {
            const list = this.filteredEntries
            if (!this.sort_by_type) return list
            return [...list].sort((a, b) => {
                const typeA = (a.type || '').toString()
                const typeB = (b.type || '').toString()
                const typeCmp = typeA.localeCompare(typeB, 'de', { sensitivity: 'base' })
                if (typeCmp !== 0) return typeCmp
                const dateA = a.date ? parseLocalDate(a.date).getTime() : 0
                const dateB = b.date ? parseLocalDate(b.date).getTime() : 0
                return dateB - dateA
            })
        },
        sortedEntriesGrouped() {
            const entries = this.sortedEntries
            if (this.semesterCount !== 2 || this.activeSemester !== 3) {
                return entries.map((e, index) => ({ kind: 'entry', key: `entry-${e.id}`, entry: e, stripe: index }))
            }
            const boundary = this.displaySem2Boundary()
            if (!boundary) {
                return entries.map((e, index) => ({ kind: 'entry', key: `entry-${e.id}`, entry: e, stripe: index }))
            }
            const sem1 = entries.filter((e) => {
                if (!e.date) return true
                const d = this.normalizeDateKey(e.date)
                return !d || d < boundary
            })
            const sem2 = entries.filter((e) => {
                if (!e.date) return false
                const d = this.normalizeDateKey(e.date)
                return !!d && d >= boundary
            })
            const result = []
            let stripeIndex = 0
            result.push({ kind: 'header', key: 'header-sem2', label: '2. Semester' })
            sem2.forEach((e) => {
                result.push({ kind: 'entry', key: `entry-${e.id}`, entry: e, stripe: stripeIndex })
                stripeIndex += 1
            })
            result.push({ kind: 'header', key: 'header-sem1', label: '1. Semester' })
            sem1.forEach((e) => {
                result.push({ kind: 'entry', key: `entry-${e.id}`, entry: e, stripe: stripeIndex })
                stripeIndex += 1
            })
            return result
        },
    },

    watch: {
        activeSemester(val) {
            if (val !== this.config?.user?.teaching_active_semester) {
                this.teachingStore.saveActiveSemester(val)
            }
        },
        'config.user.teaching_active_semester'(val) {
            if (val) this.activeSemester = Number(val) || 1
        },
        selected_course: {
            async handler(course) {
                if (!course?.id) return
                await this.courseWorkStore?.index(course.id)
            },
            deep: false,
        },
        selected_course_student: {
            handler() {
                this.show_entry_form = false
                this.entry_form = this.emptyEntryForm()
                this.show_behaviour_form = false
                this.behaviour_form = this.emptyBehaviourForm()
                this.show_star_form = false
                this.star_form = this.emptyStarForm()
                this.delete_star_id = null
                this.delete_behaviour_id = null
                this.delete_notification_id = null
                this.is_editing_grades = false
                this.is_editing_behaviour_grades = false
                this.show_auswertung = false
                this.loadEntries()
                this.loadBehaviourEntries()
            },
        },
        'entry_form.date'(val) {
            if (val && val instanceof Date) {
                this.entry_form.date = this.toDateString(val)
            }
        },
        'behaviour_form.date'(val) {
            if (val && val instanceof Date) {
                this.behaviour_form.date = this.toDateString(val)
            }
        },
        'behaviour_form.due_date'(val) {
            if (val && val instanceof Date) {
                this.behaviour_form.due_date = this.toDateString(val)
            }
        },
        'behaviour_form.done_date'(val) {
            if (val && val instanceof Date) {
                this.behaviour_form.done_date = this.toDateString(val)
            }
        },
        'behaviour_form.is_due'(val) {
            if (val) return
            this.behaviour_form.is_done = false
            this.behaviour_form.done_date = ''
            this.behaviour_form.due_date = ''
        },
        'behaviour_form.is_done'(val) {
            if (val) {
                if (!this.behaviour_form.done_date) {
                    this.behaviour_form.done_date = this.toDateString(new Date())
                }
                return
            }
            this.behaviour_form.done_date = ''
        },
        'star_form.date'(val) {
            if (val && val instanceof Date) {
                this.star_form.date = this.toDateString(val)
            }
        },
    },

    methods: {
        normalizeDateKey(date) {
            if (!date) return ''
            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}/.test(date)) {
                return date.slice(0, 10)
            }
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) return ''
            return this.toDateString(parsed)
        },
        displaySem2Boundary() {
            return this.normalizeDateKey(this.schoolSem2StartDate || this.countSem2StartDate)
        },
        countSem2Boundary() {
            return this.normalizeDateKey(this.countSem2StartDate || this.schoolSem2StartDate)
        },
        emptyEntryForm() {
            return {
                id: null,
                type: '',
                grade: '',
                date: this.toDateString(new Date()),
                description: '',
            }
        },
        async loadEntries() {
            if (!this.selected_course?.id || !this.selected_course_student?.id) {
                this.entryStore?.clear()
                return
            }
            await this.entryStore.index(this.selected_course.id, this.selected_course_student.id)
        },
        closeStudent() {
            this.selected_course_student = null
            this.action_2 = ''
            this.entryStore?.clear()
            this.behaviourEntryStore?.clear()
            this.show_star_form = false
            this.star_form = this.emptyStarForm()
            this.delete_star_id = null
            this.delete_behaviour_id = null
            this.delete_notification_id = null
        },
        editGrades() {
            this.grade_form = {
                sem_1_grade: this.selected_course_student?.sem_1_grade || '',
                sem_2_grade: this.selected_course_student?.sem_2_grade || '',
                sem_grade: this.selected_course_student?.sem_grade || '',
            }
            this.is_editing_grades = true
        },
        async saveGrades() {
            if (!this.selected_course) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)
            const gradeFields =
                this.semesterCount === 2
                    ? { sem_1_grade: this.grade_form.sem_1_grade || null, sem_2_grade: this.grade_form.sem_2_grade || null }
                    : { sem_grade: this.grade_form.sem_grade || null }

            const studentsInfo = (this.selected_course.students_info || []).map((student) => {
                if (student.id === this.selected_course_student.id) {
                    return { ...student, ...gradeFields }
                }
                return student
            })
            const studentsPayload = studentsInfo.length ? studentsInfo : (Array.isArray(this.selected_course.students) ? this.selected_course.students : [])

            const payload = {
                ...this.selected_course,
                students: studentsPayload,
                students_deleted: this.selected_course.students_deleted || [],
            }

            const ok = await this.courseStore.update(payload)
            if (ok) {
                if (studentsInfo.length) {
                    this.selected_course.students_info = studentsInfo
                    this.selected_course_student = studentsInfo.find((s) => s.id === this.selected_course_student.id) || this.selected_course_student
                }
                this.is_editing_grades = false
            }
        },
        editComment() {
            this.edit_comment = this.selected_comment || ''
            this.is_editing = true
        },
        abortEdit() {
            this.is_editing = false
            this.edit_comment = ''
        },
        async saveComment() {
            if (!this.selected_course) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)
            const studentsInfo = (this.selected_course.students_info || []).map((student) => {
                if (student.id === this.selected_course_student.id) {
                    return { ...student, comment: this.edit_comment }
                }
                return student
            })
            const studentsPayload = studentsInfo.length ? studentsInfo : (Array.isArray(this.selected_course.students) ? this.selected_course.students : [])

            const payload = {
                ...this.selected_course,
                students: studentsPayload,
                students_deleted: this.selected_course.students_deleted || [],
            }

            const ok = await this.courseStore.update(payload)
            if (ok) {
                if (studentsInfo.length) {
                    this.selected_course.students_info = studentsInfo
                    this.selected_course_student = studentsInfo.find((s) => s.id === this.selected_course_student.id) || this.selected_course_student
                }
                this.abortEdit()
            }
        },
        newEntry() {
            this.entry_form = this.emptyEntryForm()
            this.show_entry_form = true
        },
        editEntry(entry) {
            if (!entry) return
            this.entry_form = {
                id: entry.id,
                type: entry.type || '',
                grade: entry.grade || '',
                date: entry.date || '',
                description: entry.description || '',
            }
            this.show_entry_form = true
        },
        abortEntry() {
            this.show_entry_form = false
            this.entry_form = this.emptyEntryForm()
        },
        async saveEntry() {
            if (!this.selected_course || !this.selected_course_student) return
            const payload = {
                id: this.entry_form.id,
                teaching_course_id: this.selected_course.id,
                user_id: this.selected_course_student.id,
                type: this.entry_form.type,
                grade: this.entry_form.grade,
                date: this.entry_form.date instanceof Date ? this.toDateString(this.entry_form.date) : this.entry_form.date,
                description: this.entry_form.description,
            }
            const ok = this.entry_form.id ? await this.entryStore.update(payload) : await this.entryStore.store(payload)
            if (ok) {
                await this.loadEntries()
                this.abortEntry()
            }
        },
        async deleteEntry(entry) {
            const ok = await this.entryStore.destroy(entry.id)
            if (ok) {
                await this.loadEntries()
            }
            this.delete_entry_id = null
        },
        emptyStarForm() {
            return {
                id: null,
                value: 1,
                date: this.toDateString?.(new Date()) || '',
                comment: '',
            }
        },
        newStarEntry() {
            this.star_form = this.emptyStarForm()
            this.show_star_form = true
        },
        editStarEntry(star) {
            if (!star) return
            this.star_form = {
                id: star.id || null,
                value: 1,
                date: star.date || this.toDateString(new Date()),
                comment: star.comment || '',
            }
            this.show_star_form = true
        },
        abortStarEntry() {
            this.show_star_form = false
            this.star_form = this.emptyStarForm()
        },
        async saveStarEntry() {
            if (!this.selected_course || !this.selected_course_student) return
            if (this.starFormFrontendError) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            const newStar = {
                id: this.star_form.id || (crypto?.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`),
                value: 1,
                date: this.star_form.date instanceof Date ? this.toDateString(this.star_form.date) : this.star_form.date || this.toDateString(new Date()),
                comment: this.star_form.comment.trim(),
            }

            let studentsInfoBase = Array.isArray(this.selected_course.students_info) ? this.selected_course.students_info : []
            if (!studentsInfoBase.length) {
                // Recovery path: keep payload non-empty even if local students_info got desynced.
                const courseFromStore = (this.courseStore?.courses || []).find((course) => course.id === this.selected_course.id)
                if (courseFromStore) {
                    this.courseStore.ensureCourseStudentCollections(courseFromStore)
                    studentsInfoBase = Array.isArray(courseFromStore.students_info) ? courseFromStore.students_info : []
                }
            }

            const studentsInfo = studentsInfoBase.map((student) => {
                if (student.id !== this.selected_course_student.id) return student
                const stars = Array.isArray(student.stars) ? [...student.stars] : []
                const existingIndex = stars.findIndex((star) => star.id === newStar.id)
                if (existingIndex >= 0) {
                    stars.splice(existingIndex, 1, newStar)
                } else {
                    stars.push(newStar)
                }
                return { ...student, stars }
            })

            const studentsPayload = studentsInfo.length ? studentsInfo : (Array.isArray(this.selected_course.students) ? this.selected_course.students : [])

            const payload = {
                ...this.selected_course,
                students: studentsPayload,
                students_deleted: this.selected_course.students_deleted || [],
            }

            const ok = await this.courseStore.update(payload)
            if (ok) {
                if (studentsInfo.length) {
                    this.selected_course.students_info = studentsInfo
                    this.selected_course_student = studentsInfo.find((s) => s.id === this.selected_course_student.id) || this.selected_course_student
                }
                this.abortStarEntry()
            }
        },
        async deleteStarEntry(starId) {
            if (!this.selected_course || !this.selected_course_student || !starId) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            let studentsInfoBase = Array.isArray(this.selected_course.students_info) ? this.selected_course.students_info : []
            if (!studentsInfoBase.length) {
                const courseFromStore = (this.courseStore?.courses || []).find((course) => course.id === this.selected_course.id)
                if (courseFromStore) {
                    this.courseStore.ensureCourseStudentCollections(courseFromStore)
                    studentsInfoBase = Array.isArray(courseFromStore.students_info) ? courseFromStore.students_info : []
                }
            }

            const studentsInfo = studentsInfoBase.map((student) => {
                if (student.id !== this.selected_course_student.id) return student
                const stars = (student.stars || []).filter((star) => star.id !== starId)
                return { ...student, stars }
            })

            const studentsPayload = studentsInfo.length ? studentsInfo : (Array.isArray(this.selected_course.students) ? this.selected_course.students : [])

            const payload = {
                ...this.selected_course,
                students: studentsPayload,
                students_deleted: this.selected_course.students_deleted || [],
            }

            const ok = await this.courseStore.update(payload)
            if (ok) {
                if (studentsInfo.length) {
                    this.selected_course.students_info = studentsInfo
                    this.selected_course_student = studentsInfo.find((s) => s.id === this.selected_course_student.id) || this.selected_course_student
                }
            }
            this.delete_star_id = null
        },
        emptyBehaviourForm() {
            return {
                id: null,
                kind: 'behaviour',
                type: '',
                date: this.toDateString?.(new Date()) || '',
                is_due: false,
                due_date: '',
                is_done: false,
                done_date: '',
                description: '',
            }
        },
        async loadBehaviourEntries() {
            if (!this.selected_course?.id || !this.selected_course_student?.id) {
                this.behaviourEntryStore?.clear()
                return
            }
            await this.behaviourEntryStore.index(this.selected_course.id, this.selected_course_student.id)
        },
        behaviourTypeLabel(type) {
            if (!type) return ''
            const found = this.teachingBehaviour.find((b) => b.short_name === type)
            if (!found) return type
            return `${found.short_name} - ${found.name}`
        },
        notificationTypeLabel(type) {
            if (!type) return ''
            const found = this.teachingNotifications.find((n) => n.short_name === type)
            if (!found) return type
            return `${found.short_name} - ${found.name}`
        },
        newNotificationEntry() {
            this.behaviour_form = {
                ...this.emptyBehaviourForm(),
                kind: 'notification',
            }
            this.show_behaviour_form = true
        },
        editNotificationEntry(entry) {
            if (!entry) return
            this.behaviour_form = {
                id: entry.id,
                kind: 'notification',
                type: entry.type || '',
                date: entry.date || '',
                is_due: !!entry.due_date,
                due_date: entry.due_date || '',
                is_done: !!entry.done_date,
                done_date: entry.done_date || '',
                description: entry.description || '',
            }
            this.show_behaviour_form = true
        },
        newBehaviourEntry() {
            this.behaviour_form = this.emptyBehaviourForm()
            this.show_behaviour_form = true
        },
        editBehaviourEntry(entry) {
            if (!entry) return
            this.behaviour_form = {
                id: entry.id,
                kind: entry.kind || 'behaviour',
                type: entry.type || '',
                date: entry.date || '',
                is_due: false,
                due_date: '',
                is_done: false,
                done_date: '',
                description: entry.description || '',
            }
            this.show_behaviour_form = true
        },
        abortBehaviourEntry() {
            this.show_behaviour_form = false
            this.behaviour_form = this.emptyBehaviourForm()
        },
        async saveBehaviourEntry() {
            if (!this.selected_course || !this.selected_course_student) return
            if (this.behaviourFormFrontendError) return
            const allowsDue = this.behaviour_form.kind === 'notification'
            const payload = {
                id: this.behaviour_form.id,
                teaching_course_id: this.selected_course.id,
                user_id: this.selected_course_student.id,
                kind: this.behaviour_form.kind || 'behaviour',
                type: this.behaviour_form.type,
                date: this.behaviour_form.date instanceof Date ? this.toDateString(this.behaviour_form.date) : this.behaviour_form.date,
                is_due: allowsDue && !!this.behaviour_form.is_due,
                due_date:
                    allowsDue && this.behaviour_form.is_due
                        ? this.behaviour_form.due_date instanceof Date
                            ? this.toDateString(this.behaviour_form.due_date)
                            : this.behaviour_form.due_date || null
                        : null,
                is_done: allowsDue && !!this.behaviour_form.is_due && !!this.behaviour_form.is_done,
                done_date:
                    allowsDue && this.behaviour_form.is_due && this.behaviour_form.is_done
                        ? this.behaviour_form.done_date instanceof Date
                            ? this.toDateString(this.behaviour_form.done_date)
                            : this.behaviour_form.done_date || null
                        : null,
                description: this.behaviour_form.description,
            }
            const ok = this.behaviour_form.id ? await this.behaviourEntryStore.update(payload) : await this.behaviourEntryStore.store(payload)
            if (ok) {
                await this.loadBehaviourEntries()
                this.abortBehaviourEntry()
            }
        },
        editBehaviourGrades() {
            this.behaviour_grade_form = {
                behaviour_1_grade: this.selected_course_student?.behaviour_1_grade || '',
                behaviour_2_grade: this.selected_course_student?.behaviour_2_grade || '',
                behaviour_grade: this.selected_course_student?.behaviour_grade || '',
            }
            this.is_editing_behaviour_grades = true
        },
        async saveBehaviourGrades() {
            if (!this.selected_course) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)
            const gradeFields =
                this.semesterCount === 2
                    ? { behaviour_1_grade: this.behaviour_grade_form.behaviour_1_grade || null, behaviour_2_grade: this.behaviour_grade_form.behaviour_2_grade || null }
                    : { behaviour_grade: this.behaviour_grade_form.behaviour_grade || null }

            const studentsInfo = (this.selected_course.students_info || []).map((student) => {
                if (student.id === this.selected_course_student.id) {
                    return { ...student, ...gradeFields }
                }
                return student
            })
            const studentsPayload = studentsInfo.length ? studentsInfo : (Array.isArray(this.selected_course.students) ? this.selected_course.students : [])

            const payload = {
                ...this.selected_course,
                students: studentsPayload,
                students_deleted: this.selected_course.students_deleted || [],
            }

            const ok = await this.courseStore.update(payload)
            if (ok) {
                if (studentsInfo.length) {
                    this.selected_course.students_info = studentsInfo
                    this.selected_course_student = studentsInfo.find((s) => s.id === this.selected_course_student.id) || this.selected_course_student
                }
                this.is_editing_behaviour_grades = false
            }
        },
        async deleteBehaviourEntry(entry) {
            const ok = await this.behaviourEntryStore.destroy(entry.id)
            if (ok) {
                await this.loadBehaviourEntries()
            }
            this.delete_behaviour_id = null
        },
        async deleteNotificationEntry(entry) {
            const ok = await this.behaviourEntryStore.destroy(entry.id)
            if (ok) {
                await this.loadBehaviourEntries()
            }
            this.delete_notification_id = null
        },
        async completeNotificationToday(entry) {
            if (!entry?.id || !entry?.type || !entry?.due_date) return
            const payload = {
                id: entry.id,
                kind: 'notification',
                type: entry.type,
                date: entry.date || null,
                description: entry.description || null,
                is_due: true,
                due_date: entry.due_date,
                is_done: true,
                done_date: this.toDateString(new Date()),
            }
            const ok = await this.behaviourEntryStore.update(payload)
            if (ok) {
                await this.loadBehaviourEntries()
            }
        },
        toggleSortByType() {
            this.sort_by_type = !this.sort_by_type
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        dueDateColor(date) {
            const due = parseLocalDate(date)
            if (isNaN(due.getTime())) return 'warning'
            due.setHours(0, 0, 0, 0)
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            return due <= today ? 'error' : 'warning'
        },
        workTypeLabel(type) {
            if (!type) return ''
            const found = this.teachingWorks.find((w) => w.short_name === type)
            if (!found) return type
            return `${found.short_name} - ${found.name}`
        },
        gradeValueForWork(work, gradeKey) {
            if (!work || !gradeKey) return null
            const grade = (work.grades || []).find((g) => g.grade === gradeKey)
            if (!grade || grade.value == null) return null
            const num = parseFloat(String(grade.value).replace(',', '.'))
            return Number.isNaN(num) ? null : num
        },
        pointsGradeForWork(work, points) {
            if (!work || work.calculation !== 'points') return null
            const table = work.points_table || []
            if (!table.length) return null
            const sorted = [...table].sort((a, b) => (b.min_points ?? 0) - (a.min_points ?? 0))
            const found = sorted.find((row) => points >= (row.min_points ?? 0))
            return found?.grade || null
        },
        formatTwoDecimals(value) {
            if (value == null || value === '') return ''
            const num = typeof value === 'number' ? value : parseFloat(String(value).replace(',', '.'))
            if (Number.isNaN(num)) return ''
            return num.toFixed(2)
        },
        pointsGradeForAnyWork(points) {
            const pointsWork = this.teachingWorks.find((w) => w.calculation === 'points' && (w.points_table || []).length)
            return pointsWork ? this.pointsGradeForWork(pointsWork, points) : null
        },
        entryIsDerivedFromWork(entry) {
            return (entry?.source || '') === 'course_work'
        },
        entryWorkTitle(entry) {
            const workId = entry?.teaching_course_work_id
            if (!workId) return ''
            const work = (this.courseWorkStore?.courseWorks || []).find((w) => w.id === workId)
            if (!work) return ''
            const desc = (work.description || '').toString().trim()
            return desc || ''
        },
        async jumpToWork(entry) {
            if (!entry?.teaching_course_work_id || !this.selected_course?.id) return
            await this.courseWorkStore?.index(this.selected_course.id)
            const work = (this.courseWorkStore?.courseWorks || []).find((w) => w.id === entry.teaching_course_work_id)
            if (!work) return

            this.action_2 = ''
            this.selected_course_student = null
            this.show_works = true
            this.show_infos = false
            this.show_dates = false
            this.selected_course_id = this.selected_course.id
            this.courseWorkStore.selected_courseWork = work
        },
        entriesForSemester(entries, semester) {
            if (this.semesterCount !== 2) return entries
            const boundary = this.countSem2Boundary()
            if (!boundary) return entries
            return (entries || []).filter((entry) => {
                if (!entry.date) return true
                const d = this.normalizeDateKey(entry.date)
                if (!d) return true
                if (semester === 1) return d < boundary
                if (semester === 2) return d >= boundary
                return true
            })
        },
        entryIsDisplaySem1ButCountsSem2(entry) {
            if (this.semesterCount !== 2) return false
            if (!entry?.date) return false
            if (!this.hasDifferentSem2CountDate) return false
            if (!this.schoolSem2StartDate || !this.countSem2StartDate) return false
            const d = this.normalizeDateKey(entry.date)
            const displayBoundary = this.displaySem2Boundary()
            const countBoundary = this.countSem2Boundary()
            if (!d || !displayBoundary || !countBoundary) return false
            const isDisplaySem1 = d < displayBoundary
            const isCountedSem2 = d >= countBoundary
            return isDisplaySem1 && isCountedSem2
        },
        buildCategoryGroups(entries) {
            const grading = this.selectedSchema?.grading || {}
            const categories = grading.categories || []
            const worksByType = new Map(this.teachingWorks.map((w) => [w.short_name, w]))
            const usedTypes = new Set()

            const grouped = categories.map((cat) => {
                const works = (cat.works || []).map((w) => (typeof w === 'string' ? { short_name: w, factor: 100 } : w))
                const rows = []
                const workAverages = []
                const calculationParts = []
                let categoryPointsGrade = null

                works.forEach((workItem) => {
                    const type = workItem.short_name
                    const work = worksByType.get(type)
                    if (!work) return
                    usedTypes.add(type)

                    const factorPercentRaw = parseFloat(workItem.factor)
                    const factorPercent = Number.isNaN(factorPercentRaw) ? 0 : factorPercentRaw
                    const weight = factorPercent / 100

                    if (work.calculation === 'points') {
                        const values = (entries || [])
                            .filter((entry) => entry.type === type)
                            .map((entry) => this.gradeValueForWork(work, entry.grade))
                            .filter((val) => val !== null)
                        const sum = values.reduce((s, v) => s + v, 0)
                        const rounded = Number.isInteger(sum) ? sum : Number(sum.toFixed(2))
                        const grade = this.pointsGradeForWork(work, rounded)
                        rows.push({
                            key: `sum-${type}`,
                            type,
                            sum: rounded,
                            grade,
                        })
                        if (categoryPointsGrade == null && grade != null && grade !== '') {
                            categoryPointsGrade = grade
                        }
                        let numericGrade = this.gradeValueForWork(work, grade)
                        if (numericGrade === null && grade != null && grade !== '') {
                            const parsed = parseFloat(String(grade).replace(',', '.'))
                            numericGrade = Number.isNaN(parsed) ? null : parsed
                        }
                        if (numericGrade !== null) {
                            workAverages.push({ value: numericGrade, weight })
                        }
                        calculationParts.push({
                            type,
                            factorPercent,
                            value: numericGrade,
                        })
                        return
                    }

                    const values = (entries || [])
                        .filter((entry) => entry.type === type)
                        .map((entry) => this.gradeValueForWork(work, entry.grade))
                        .filter((val) => val !== null)
                    let avg = null
                    if (values.length) {
                        avg = values.reduce((s, v) => s + v, 0) / values.length
                        workAverages.push({ value: avg, weight })
                    }
                    calculationParts.push({
                        type,
                        factorPercent,
                        value: avg !== null ? Number(avg.toFixed(2)) : null,
                    })

                    ;(entries || [])
                        .filter((entry) => entry.type === type)
                        .forEach((entry) => {
                            const value = this.gradeValueForWork(work, entry.grade)
                            rows.push({
                                key: `entry-${entry.id}`,
                                type,
                                value: value !== null ? value : entry.grade,
                                date: entry.date || null,
                            })
                        })
                })

                let categoryValue = null
                let categoryGrade = null
                if (workAverages.length) {
                    const totalWeight = workAverages.reduce((s, w) => s + w.weight, 0) || 1
                    const weighted = workAverages.reduce((s, w) => s + w.value * w.weight, 0) / totalWeight
                    categoryValue = Number(weighted.toFixed(2))
                    categoryGrade = categoryPointsGrade || null
                } else if (categoryPointsGrade) {
                    categoryGrade = categoryPointsGrade
                }

                return {
                    name: cat.name || 'Kategorie',
                    weight: cat.weight ?? 0,
                    rows,
                    value: categoryValue,
                    grade: categoryGrade,
                    calculationParts,
                }
            })

            const undefinedRows = []
            ;(entries || []).forEach((entry) => {
                if (!entry.type || usedTypes.has(entry.type)) return
                const work = worksByType.get(entry.type)
                if (work && work.calculation === 'points') {
                    const values = (entries || [])
                        .filter((e) => e.type === entry.type)
                        .map((e) => this.gradeValueForWork(work, e.grade))
                        .filter((val) => val !== null)
                    const sum = values.length ? values.reduce((s, v) => s + v, 0) : 0
                    const rounded = Number.isInteger(sum) ? sum : Number(sum.toFixed(2))
                    if (!undefinedRows.some((r) => r.key === `sum-${entry.type}`)) {
                        undefinedRows.push({
                            key: `sum-${entry.type}`,
                            type: entry.type,
                            sum: rounded,
                            grade: this.pointsGradeForWork(work, rounded),
                        })
                    }
                    return
                }

                const value = work ? this.gradeValueForWork(work, entry.grade) : entry.grade
                undefinedRows.push({
                    key: `entry-${entry.id}`,
                    type: entry.type,
                    value: value !== null ? value : entry.grade,
                    date: entry.date || null,
                })
            })

            if (undefinedRows.length) {
                grouped.push({
                    name: 'Undefiniert',
                    rows: undefinedRows,
                })
            }

            return grouped
        },
        totalFromCategoryGroups(groups) {
            if (!groups?.length) return null
            const weightedCats = groups
                .map((cat) => {
                    const value = cat.value != null ? cat.value : cat.grade != null ? parseFloat(String(cat.grade).replace(',', '.')) : null
                    return {
                        value,
                        weight: (parseFloat(cat.weight) || 0) / 100,
                    }
                })
                .filter((cat) => cat.value != null && !Number.isNaN(cat.value) && cat.weight > 0)

            if (!weightedCats.length) return null
            const totalWeight = weightedCats.reduce((s, c) => s + c.weight, 0) || 1
            const weighted = weightedCats.reduce((s, c) => s + c.value * c.weight, 0) / totalWeight
            return Number(weighted.toFixed(2))
        },
        categoryCalculationLine(category) {
            const parts = (category?.calculationParts || []).filter((part) => part.value != null && part.factorPercent > 0)
            if (!parts.length) return ''

            const terms = parts.map((part) => {
                return `${part.type}(${this.formatTwoDecimals(part.value)}) x ${this.formatTwoDecimals(part.factorPercent / 100)}`
            })
            const denominator = parts.reduce((sum, part) => sum + part.factorPercent / 100, 0)
            const result = category?.value != null ? this.formatTwoDecimals(category.value) : ''
            if (!result) return ''

            return `Berechnung: (${terms.join(' + ')}) / ${this.formatTwoDecimals(denominator)} = ${result}`
        },
        categoryMissingLine(category) {
            const missing = (category?.calculationParts || []).filter((part) => (part.value == null || Number.isNaN(part.value)) && part.factorPercent > 0)
            if (!missing.length) return ''
            const list = missing.map((part) => `${part.type} (${this.formatTwoDecimals(part.factorPercent)}%)`).join(', ')
            return `Ohne Wert: ${list}`
        },
        setDueInAWeek() {
            const d = new Date()
            d.setDate(d.getDate() + 7)
            this.behaviour_form.due_date = this.toDateString(d)
        },
        setDueNextLesson() {
            if (!this.nextCourseLessonDate) return
            this.behaviour_form.due_date = this.nextCourseLessonDate
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

<style scoped>
.course-comment :deep(p) {
    margin: 0;
    min-height: 1.2em;
}

.auswertung-section-header {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 700;
    line-height: 1;
    color: rgb(var(--v-theme-on-primary));
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgba(var(--v-theme-primary), 0.78) 100%);
    box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.28);
}

.auswertung-section-header--sum {
    color: rgb(var(--v-theme-on-secondary));
    background: linear-gradient(135deg, rgb(var(--v-theme-secondary)) 0%, rgba(var(--v-theme-secondary), 0.82) 100%);
    box-shadow: 0 4px 14px rgba(var(--v-theme-secondary), 0.24);
}

.sum-formula {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sum-formula-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 0.95rem;
    font-weight: 600;
}

.entry-work-title {
    max-width: min(460px, 60vw);
}

.entry-work-title :deep(.v-chip__content) {
    overflow: hidden;
    display: block;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.entry-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
}

.entry-list-row--base {
    background-color: #ffffff !important;
    border-radius: 8px;
}

.entry-list-row--alt {
    background-color: #e9edf5 !important;
    border-radius: 8px;
}

.entry-row.entry-list-row--base,
.entry-row.entry-list-row--alt {
    padding: 6px 8px;
}

.entry-row > .v-chip {
    flex: 0 0 auto;
}

.star-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
}

.star-row > .v-chip {
    flex: 0 0 auto;
}

.star-description {
    min-width: 120px;
}

.star-actions {
    margin-left: auto;
    flex: 0 0 auto;
}

.behaviour-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
}

.behaviour-row > .v-chip {
    flex: 0 0 auto;
}

.behaviour-description {
    min-width: 120px;
}

.behaviour-actions {
    margin-left: auto;
    flex: 0 0 auto;
}

.entry-description {
    min-width: 120px;
}

.entry-actions {
    margin-left: auto;
    flex: 0 0 auto;
}

.entry-work-title {
    flex: 0 1 auto;
    min-width: 0;
    width: fit-content;
}

@media (max-width: 700px) {
    .star-description {
        flex-basis: 100%;
        min-width: 100%;
        margin-top: 2px;
        order: 2;
    }

    .star-actions {
        order: 1;
    }

    .behaviour-description {
        flex-basis: 100%;
        min-width: 100%;
        margin-top: 2px;
        order: 2;
    }

    .behaviour-actions {
        order: 1;
    }

    .entry-description {
        flex-basis: 100%;
        min-width: 100%;
        margin-top: 2px;
    }

    .entry-actions {
        margin-left: 0;
        width: 100%;
        justify-content: flex-end;
    }

    .entry-work-title {
        flex-basis: 100%;
        min-width: 0;
        max-width: 100%;
    }
}
</style>
