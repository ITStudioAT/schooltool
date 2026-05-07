<template>
    <ItsGridBox variant="overview" color="primary" icon="mdi-account" class="w-100" v-if="selected_course_student" :disabled="action != '' || isSavingMutation">
        <template #title>
            <div class="its-grid-box__title-text">{{ selected_course_student.last_name }}, {{ selected_course_student.first_name }}</div>
            <v-menu>
                <template #activator="{ props }">
                    <v-btn v-bind="props" icon="mdi-menu" size="x-small" variant="tonal" color="primary" />
                </template>
                <v-list density="compact">
                    <v-list-item prepend-icon="mdi-pencil" title="Bemerkung" @click="editComment" />
                    <v-list-item v-if="showBehaviourEnabled" prepend-icon="mdi-account-alert" title="Verhalten" @click="newBehaviourEntry" />
                    <v-list-item prepend-icon="mdi-star" title="Sterne" @click="newStarEntry" />
                    <v-list-item v-if="showBehaviourEnabled" prepend-icon="mdi-message-text" title="Verständigung" @click="newNotificationEntry" />
                    <v-divider />
                    <v-list-item prepend-icon="mdi-plus" title="Neue Bewertung" @click="newEntry" />
                </v-list>
            </v-menu>
        </template>
        <template #header-actions>
            <div class="d-flex align-center ga-1">
                <v-btn icon="mdi-chevron-left" size="x-small" color="primary" variant="tonal" :disabled="!hasPreviousStudent" @click="goToPreviousStudent" />
                <v-btn icon="mdi-chevron-right" size="x-small" color="primary" variant="tonal" :disabled="!hasNextStudent" @click="goToNextStudent" />
            </div>
            <v-btn-toggle v-if="semesterCount === 2" v-model="activeSemester" mandatory density="compact" color="primary">
                <v-btn :value="1" size="small">1. Sem</v-btn>
                <v-btn :value="2" size="small">2. Sem</v-btn>
                <v-btn :value="3" size="small">1+2</v-btn>
            </v-btn-toggle>
            <v-btn icon="mdi-close" size="x-small" color="warning" variant="tonal" title="Zurück" @click="closeStudent" />
        </template>
        <template #header-below>
            <div class="d-flex flex-wrap align-center ga-2 mt-1">
                <div v-if="showBehaviourEnabled" class="d-flex flex-wrap align-center ga-2">
                    <span class="text-caption text-medium-emphasis">Verhalten:</span>
                    <template v-if="semesterCount === 2">
                        <v-chip size="small" variant="flat" class="cursor-pointer" :color="selected_course_student.behaviour_1_grade ? 'success' : 'default'" @click="editBehaviourGrades">
                            1. Sem: {{ selected_course_student.behaviour_1_grade || '–' }}
                        </v-chip>
                        <v-chip size="small" variant="flat" class="cursor-pointer" :color="selected_course_student.behaviour_2_grade ? 'success' : 'default'" @click="editBehaviourGrades">
                            2. Sem: {{ selected_course_student.behaviour_2_grade || '–' }}
                        </v-chip>
                    </template>
                    <v-chip v-else size="small" variant="flat" class="cursor-pointer" :color="selected_course_student.behaviour_grade ? 'success' : 'default'" @click="editBehaviourGrades">
                        {{ selected_course_student.behaviour_grade || '–' }}
                    </v-chip>
                </div>
                <div class="d-flex flex-wrap align-center ga-2">
                    <span class="text-caption text-medium-emphasis">Benotung:</span>
                    <template v-if="semesterCount === 2">
                        <v-chip size="small" variant="flat" class="cursor-pointer" :color="selected_course_student.sem_1_grade ? 'success' : 'default'" @click="editGrades">
                            1. Sem: {{ selected_course_student.sem_1_grade || '–' }}
                        </v-chip>
                        <v-chip size="small" variant="flat" class="cursor-pointer" :color="selected_course_student.sem_2_grade ? 'success' : 'default'" @click="editGrades">
                            2. Sem: {{ selected_course_student.sem_2_grade || '–' }}
                        </v-chip>
                    </template>
                    <v-chip v-else size="small" variant="flat" class="cursor-pointer" :color="selected_course_student.sem_grade ? 'success' : 'default'" @click="editGrades">
                        {{ selected_course_student.sem_grade || '–' }}
                    </v-chip>
                </div>
            </div>
        </template>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-card v-if="hasAuswertungContent && show_auswertung" variant="outlined">
                    <v-card-title class="d-flex align-center py-1 px-3">
                        <span class="text-subtitle-2">Auswertung</span>
                        <v-spacer />
                        <v-btn icon="mdi-close" size="x-small" variant="text" @click="show_auswertung = false" />
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="py-2">
                        <v-list density="compact">
                            <template v-if="showSemester1Auswertung">
                                <div class="auswertung-semester-card auswertung-semester-card--semester-1">
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
                                        <div class="auswertung-category-card">
                                            <v-list-item>
                                            <div class="d-flex align-center ga-2 w-100">
                                                <v-list-item-title class="text-subtitle-2" :class="cat.isNa ? 'text-error' : (categoryHasBewertung(cat) ? 'text-success' : (!cat.rows.length ? 'text-warning' : ''))">
                                                    {{ cat.name }}
                                                </v-list-item-title>
                                                <v-chip size="x-small" variant="outlined">{{ cat.weight }}%</v-chip>
                                                <v-chip v-if="cat.requireAllEntries" size="x-small" variant="tonal" color="primary">
                                                    Alle erforderlich
                                                </v-chip>
                                                <v-spacer />
                                                <div class="text-caption" :class="cat.isNa ? 'text-error' : (categoryHasBewertung(cat) ? 'text-success' : 'text-warning')">
                                                    Bewertung: {{ cat.value != null ? formatEvaluationValue(cat.value) : (cat.grade ? formatEvaluationValue(cat.grade) : '–') }}
                                                </div>
                                            </div>
                                            </v-list-item>
                                            <v-list-item v-if="categoryCalculationLine(cat)">
                                                <div class="text-caption text-medium-emphasis w-100">
                                                    {{ categoryCalculationLine(cat) }}
                                                </div>
                                            </v-list-item>
                                            <v-list-item v-for="row in cat.rows" :key="`sem1-cat-${cat.name}-${row.key}`">
                                                <div class="w-100">
                                                    <div class="d-flex align-center ga-2 w-100 auswertung-entry-main-row">
                                                        <div v-if="row.type" class="text-caption text-medium-emphasis auswertung-entry-title">
                                                            {{ workTypeLabel(row.type) }}
                                                        </div>
                                                        <v-spacer />
                                                        <v-chip v-if="row.date" size="x-small" variant="tonal" color="primary">
                                                            {{ formatDate(row.date) }}
                                                        </v-chip>
                                                    <v-chip v-if="row.sum != null" size="x-small" variant="tonal" color="primary">Σ {{ row.sum }}</v-chip>
                                                    <v-chip v-if="row.value != null" size="x-small" variant="tonal" :color="isNaGradeKey(row.value) ? 'error' : 'primary'">
                                                        {{ row.value }}
                                                    </v-chip>
                                                    <v-chip v-if="row.grade" size="x-small" variant="tonal" :color="isNaGradeKey(row.grade) ? 'error' : 'primary'">{{ row.grade }}</v-chip>
                                                    <v-chip v-if="row.requireAllEntriesIncomplete" size="x-small" variant="tonal" color="warning">
                                                        NB
                                                    </v-chip>
                                                    </div>
                                                    <div v-if="row.workTitle" class="text-body-2 auswertung-entry-work-title">
                                                        {{ row.workTitle }}
                                                    </div>
                                                </div>
                                            </v-list-item>
                                        </div>
                                    </template>
                                    <div v-if="semester1Total != null" class="auswertung-total-card">
                                        <v-list-item>
                                            <div class="d-flex align-center ga-2 w-100">
                                                <v-list-item-title class="text-subtitle-1 font-weight-bold">{{ semesterCount === 2 ? 'Berechnung Sem 1' : 'Berechnung' }}</v-list-item-title>
                                                <v-spacer />
                                                <div class="text-subtitle-1 font-weight-bold" :class="isNaGradeKey(semester1Total) ? 'text-error' : semester1HasMissingCategory ? 'text-warning' : 'text-primary'">
                                                    Bewertung: {{ formatEvaluationValue(semester1Total) }}
                                                </div>
                                            </div>
                                        </v-list-item>
                                    </div>
                                </div>
                            </template>

                            <template v-if="showSemester2Auswertung">
                                <div class="auswertung-semester-card auswertung-semester-card--semester-2">
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
                                        <div class="auswertung-category-card">
                                            <v-list-item>
                                            <div class="d-flex align-center ga-2 w-100">
                                                <v-list-item-title class="text-subtitle-2" :class="cat.isNa ? 'text-error' : (categoryHasBewertung(cat) ? 'text-success' : (!cat.rows.length ? 'text-warning' : ''))">
                                                    {{ cat.name }}
                                                </v-list-item-title>
                                                <v-chip size="x-small" variant="outlined">{{ cat.weight }}%</v-chip>
                                                <v-chip v-if="cat.requireAllEntries" size="x-small" variant="tonal" color="primary">
                                                    Alle erforderlich
                                                </v-chip>
                                                <v-spacer />
                                                <div class="text-caption" :class="cat.isNa ? 'text-error' : (categoryHasBewertung(cat) ? 'text-success' : 'text-warning')">
                                                    Bewertung: {{ cat.value != null ? formatEvaluationValue(cat.value) : (cat.grade ? formatEvaluationValue(cat.grade) : '–') }}
                                                </div>
                                            </div>
                                            </v-list-item>
                                            <v-list-item v-if="categoryCalculationLine(cat)">
                                                <div class="text-caption text-medium-emphasis w-100">
                                                    {{ categoryCalculationLine(cat) }}
                                                </div>
                                            </v-list-item>
                                            <v-list-item v-for="row in cat.rows" :key="`sem2-cat-${cat.name}-${row.key}`">
                                                <div class="w-100">
                                                    <div class="d-flex align-center ga-2 w-100 auswertung-entry-main-row">
                                                        <div v-if="row.type" class="text-caption text-medium-emphasis auswertung-entry-title">
                                                            {{ workTypeLabel(row.type) }}
                                                        </div>
                                                        <v-spacer />
                                                        <v-chip v-if="row.date" size="x-small" variant="tonal" color="primary">
                                                            {{ formatDate(row.date) }}
                                                        </v-chip>
                                                        <v-chip v-if="row.sum != null" size="x-small" variant="tonal" color="primary">Σ {{ row.sum }}</v-chip>
                                                        <v-chip v-if="row.value != null" size="x-small" variant="tonal" :color="isNaGradeKey(row.value) ? 'error' : 'primary'">
                                                            {{ row.value }}
                                                        </v-chip>
                                                        <v-chip v-if="row.grade" size="x-small" variant="tonal" :color="isNaGradeKey(row.grade) ? 'error' : 'primary'">{{ row.grade }}</v-chip>
                                                        <v-chip v-if="row.requireAllEntriesIncomplete" size="x-small" variant="tonal" color="warning">
                                                            NB
                                                        </v-chip>
                                                    </div>
                                                    <div v-if="row.workTitle" class="text-body-2 auswertung-entry-work-title">
                                                        {{ row.workTitle }}
                                                    </div>
                                                </div>
                                            </v-list-item>
                                        </div>
                                    </template>
                                    <div v-if="semester2Total != null" class="auswertung-total-card auswertung-total-card--semester-2">
                                        <v-list-item>
                                            <div class="d-flex align-center ga-2 w-100">
                                                <v-list-item-title class="text-subtitle-1 font-weight-bold">Berechnung Sem 2</v-list-item-title>
                                                <v-spacer />
                                                <div class="text-subtitle-1 font-weight-bold" :class="isNaGradeKey(semester2Total) ? 'text-error' : semester2HasMissingCategory ? 'text-warning' : 'text-secondary'">
                                                    Bewertung: {{ formatEvaluationValue(semester2Total) }}
                                                </div>
                                            </div>
                                        </v-list-item>
                                    </div>
                                </div>
                            </template>

                            <v-list-item v-if="semesterWeightedGrade">
                                <div class="w-100 auswertung-sum-card">
                                    <div class="d-flex align-center ga-2 w-100">
                                        <div class="auswertung-section-header">
                                            <v-icon size="16">mdi-calculator-variant</v-icon>
                                            <span>GESAMT</span>
                                        </div>
                                    </div>
                                    <div v-if="semesterWeightedGrade.isNb" class="text-caption mt-2" :class="isNaGradeKey(semesterWeightedGrade.value) ? 'text-error' : 'text-warning'">
                                        {{ semesterWeightedGrade.nbReason || 'NB: Pflichtkategorie "Alle erforderlich" ist nicht vollständig beurteilt.' }}
                                    </div>
                                    <div v-else class="sum-formula mt-2">
                                        <div class="sum-formula-columns" :class="{ 'sum-formula-columns--stacked': semesterCount !== 2 }">
                                            <div class="sum-formula-column">
                                                <div class="sum-formula-column-label">Semester 1</div>
                                                <div class="sum-formula-column-share">{{ semesterWeightedGrade.sem1Weight }}%</div>
                                                <div class="sum-formula-column-grade">{{ formatEvaluationValue(semesterWeightedGrade.sem1Value) }}</div>
                                                <div class="text-caption text-medium-emphasis">
                                                    <span v-if="semesterWeightedGrade.sem1Source === 'semester_grade'">
                                                        Basis Sem 1: Semesternote ({{ selected_course_student?.sem_1_grade || '–' }}) laut Einstellung "Nur die Semesternote".
                                                    </span>
                                                    <span v-else>
                                                        Basis Sem 1: Berechnung Sem 1 ({{ formatEvaluationValue(semesterWeightedGrade.sem1CalculatedValue) }}).
                                                    </span>
                                                </div>
                                                <div v-if="semesterWeightedGrade.sem1Source === 'calculated' && semesterWeightedGrade.sem1CalculatedFormula" class="text-caption text-medium-emphasis">
                                                    {{ semesterWeightedGrade.sem1CalculatedFormula }}
                                                </div>
                                            </div>
                                            <div class="sum-formula-column">
                                                <div class="sum-formula-column-label">Semester 2</div>
                                                <div class="sum-formula-column-share">{{ semesterWeightedGrade.sem2Weight }}%</div>
                                                <div class="sum-formula-column-grade">{{ formatEvaluationValue(semesterWeightedGrade.sem2Value) }}</div>
                                                <div class="text-caption text-medium-emphasis">
                                                    Basis Sem 2: Berechnung Sem 2 ({{ formatEvaluationValue(semesterWeightedGrade.sem2CalculatedValue) }}).
                                                </div>
                                                <div v-if="semesterWeightedGrade.sem2CalculatedFormula" class="text-caption text-medium-emphasis">
                                                    {{ semesterWeightedGrade.sem2CalculatedFormula }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sum-formula-result-card">
                                            <div class="sum-formula-result-label text-medium-emphasis">Gesamtbewertung</div>
                                            <div class="sum-formula-result-value" :class="isNaGradeKey(semesterWeightedGrade.value) ? 'text-error' : 'text-primary'">
                                                {{ formatEvaluationValue(semesterWeightedGrade.value) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>                                
                            </v-list-item>
                        </v-list>

                    </v-card-text>
                </v-card>
                <v-card v-if="selected_comment" variant="outlined" class="mt-4">
                    <v-card-text>
                        <div class="text-body-2 course-comment" v-html="commentHtml"></div>
                    </v-card-text>
                </v-card>

                <v-dialog v-model="show_comment_dialog" persistent max-width="500">
                    <v-card>
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                            <v-icon size="18">mdi-pencil</v-icon>
                            Bemerkung bearbeiten
                            <v-spacer />
                            <v-btn icon="mdi-close" size="x-small" variant="text" :disabled="isSavingMutation" @click="show_comment_dialog = false" />
                        </v-card-title>
                        <v-divider />
                        <v-card-text>
                            <ItsRichTextEditor v-model="edit_comment" :disabled="isSavingAction('save-comment')" />
                        </v-card-text>
                        <v-card-actions>
                            <v-btn color="warning" variant="tonal" :disabled="isSavingMutation" @click="show_comment_dialog = false">Abbruch</v-btn>
                            <v-spacer />
                            <v-btn color="success" variant="tonal" :loading="isSavingAction('save-comment')" :disabled="isSavingMutation" @click="saveComment">Speichern</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>


                <v-card variant="outlined" class="mt-4">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-clipboard-text</v-icon>
                        Bewertungen
                        <v-chip v-if="filteredEntries?.length" size="x-small" color="primary" variant="tonal">
                            {{ filteredEntries.length }}
                        </v-chip>
                        <v-spacer />
                        <v-btn
                            size="small"
                            :color="show_auswertung ? 'success' : 'primary'"
                            :variant="show_auswertung ? 'flat' : 'tonal'"
                            :prepend-icon="show_auswertung ? 'mdi-eye-off' : 'mdi-eye'"
                            :aria-pressed="show_auswertung ? 'true' : 'false'"
                            :disabled="!hasAuswertungContent"
                            @click="show_auswertung = !show_auswertung">
                            Auswerten
                        </v-btn>
                        <v-btn size="small" variant="tonal" color="primary" @click="toggleSortByType">
                            {{ sort_by_type ? 'Sort: Typ' : 'Sort: Datum' }}
                        </v-btn>
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
                                <v-list-item v-else-if="item.kind === 'type-header'" class="type-group-header">
                                    <div class="text-caption font-weight-bold text-medium-emphasis mt-2">{{ item.label }}</div>
                                </v-list-item>
                                <v-list-item v-else class="cursor-pointer" @click="onEntryRowClick(item.entry)">
                                    <div
                                        class="entry-row d-flex align-center ga-2 w-100"
                                        :class="item.stripe % 2 === 1 ? 'entry-list-row--alt' : 'entry-list-row--base'">
                                            <v-chip v-if="item.entry.date" size="x-small" variant="tonal" color="primary">
                                                {{ formatDate(item.entry.date) }}
                                            </v-chip>
                                            <v-chip v-if="entryIsDisplaySem1ButCountsSem2(item.entry)" size="x-small" variant="tonal" color="warning">
                                                Zählt zu Sem 2
                                            </v-chip>
                                            <v-chip v-if="item.entry.type && !sort_by_type" size="x-small" variant="outlined">
                                                {{ item.entry.type }}
                                            </v-chip>
                                            <v-chip v-if="entryIsDerivedFromWork(item.entry)" size="x-small" variant="tonal" color="info">
                                                <v-icon start size="12">mdi-lock</v-icon>
                                                Aus Arbeit
                                            </v-chip>
                                            <span v-if="entryInlineDetail(item.entry)" class="text-caption text-medium-emphasis text-truncate">
                                                {{ entryInlineDetail(item.entry) }}
                                            </span>
                                            <v-spacer />
                                            <v-chip size="small" variant="tonal" :color="isNaGradeKey(entryDisplayGrade(item.entry)) ? 'error' : entryHasDisplayGrade(item.entry) ? 'success' : 'error'">
                                                {{ entryDisplayGrade(item.entry) }}
                                            </v-chip>
                                    </div>
                                </v-list-item>
                            </template>
                            <v-list-item v-if="!filteredEntries?.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Einträge vorhanden.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>

                </v-card>

                <v-card v-if="showBehaviourEnabled && filteredBehaviourEntries?.length" variant="outlined" class="mt-4">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-account-alert</v-icon>
                        Verhaltens-Einträge
                        <v-chip v-if="filteredBehaviourEntries?.length" size="x-small" color="warning" variant="flat">
                            {{ filteredBehaviourEntries.length }}
                        </v-chip>
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

                <v-card v-if="studentStars?.length" variant="outlined" class="mt-4">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-star</v-icon>
                        Sterne
                        <v-chip v-if="studentStars.length" size="x-small" color="amber-darken-2" variant="flat">{{ studentStars.length }}</v-chip>
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

                <v-card v-if="filteredNotificationEntries?.length" variant="outlined" class="mt-4">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-bell</v-icon>
                        Verständigungen
                        <v-chip v-if="filteredNotificationEntries?.length" size="x-small" color="secondary" variant="flat">
                            {{ filteredNotificationEntries.length }}
                        </v-chip>
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
                                            :loading="isSavingAction('complete-notification')"
                                            :disabled="isSavingMutation"
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

                <v-dialog v-if="showBehaviourEnabled" v-model="show_behaviour_form" persistent max-width="500">
                    <v-card>
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                            <v-icon size="18">mdi-account-alert</v-icon>
                            {{ behaviour_form.id ? entryFormTitleEdit : entryFormTitleNew }}
                            <v-spacer />
                            <v-btn icon="mdi-close" size="x-small" variant="text" :disabled="isSavingMutation" @click="abortBehaviourEntry" />
                        </v-card-title>
                        <v-divider />
                        <v-card-text>
                            <v-form ref="behaviourForm" @submit.prevent="saveBehaviourEntry">
                                <div class="text-caption text-medium-emphasis mb-1 mt-2">Typ</div>
                                <div class="d-flex flex-wrap ga-1 mb-1">
                                    <v-btn
                                        v-for="item in entryTypeItems"
                                        :key="item.value"
                                        :variant="behaviour_form.type === item.value ? 'flat' : 'tonal'"
                                        :color="behaviour_form.type === item.value ? 'warning' : 'default'"
                                        size="small"
                                        @click="behaviour_form.type = behaviour_form.type === item.value ? null : item.value">
                                        {{ item.value }}
                                    </v-btn>
                                </div>
                                <div class="text-caption text-warning mb-3" style="min-height: 1.2em;">
                                    {{ entryTypeItems.find(i => i.value === behaviour_form.type)?.title ?? '' }}
                                </div>
                                <v-date-input v-model="behaviour_form.date" label="Datum" />
                                <v-textarea v-model="behaviour_form.description" label="Beschreibung" rows="3" :counter="1024" :maxlength="1024" />
                                <div v-if="showDueFields" class="d-flex flex-column ga-2 mt-2">
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
                            </v-form>
                        </v-card-text>
                        <v-divider />
                        <v-card-actions>
                            <v-btn color="warning" variant="tonal" :disabled="isSavingMutation" @click="abortBehaviourEntry">Abbruch</v-btn>
                            <v-spacer />
                            <v-btn color="success" variant="tonal" :loading="isSavingAction('save-behaviour-entry')" :disabled="!canSaveBehaviourForm || isSavingMutation" @click="saveBehaviourEntry">{{ behaviour_form.id ? 'Aktualisieren' : 'Speichern' }}</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="show_entry_form" persistent max-width="500">
                    <v-card>
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                            <v-icon size="18">mdi-clipboard-text</v-icon>
                            {{ entry_form.id ? 'Eintrag bearbeiten' : 'Neuer Eintrag' }}
                            <v-spacer />
                            <v-btn icon="mdi-close" size="x-small" variant="text" :disabled="isSavingMutation" @click="abortEntry" />
                        </v-card-title>
                        <v-divider />
                        <v-card-text>
                            <v-form ref="entryForm" @submit.prevent="saveEntry">
                                <div class="text-caption text-medium-emphasis mb-1 mt-2">Typ</div>
                                <div class="d-flex flex-wrap ga-1 mb-1">
                                    <v-btn
                                        v-for="item in workTypeItems"
                                        :key="item.value"
                                        :variant="entry_form.type === item.value ? 'flat' : 'tonal'"
                                        :color="entry_form.type === item.value ? 'primary' : 'default'"
                                        size="small"
                                        @click="selectEntryType(item.value)">
                                        {{ item.value }}
                                    </v-btn>
                                </div>
                                <div class="text-caption text-primary mb-3" style="min-height: 1.2em;">
                                    {{ workTypeItems.find(i => i.value === entry_form.type)?.title ?? '' }}
                                </div>
                                <div class="text-caption text-medium-emphasis mb-1">Note</div>
                                <div class="d-flex flex-wrap ga-1 mb-1" v-if="gradeItemsForType.length">
                                    <v-btn
                                        v-for="item in gradeItemsForType"
                                        :key="item.value"
                                        :variant="entry_form.grade === item.value ? 'flat' : 'tonal'"
                                        :color="entry_form.grade === item.value ? 'success' : 'default'"
                                        size="small"
                                        @click="entry_form.grade = entry_form.grade === item.value ? null : item.value">
                                        {{ item.value }}
                                    </v-btn>
                                </div>
                                <div class="text-caption text-success mb-3" style="min-height: 1.2em;" v-if="gradeItemsForType.length">
                                    {{ gradeItemsForType.find(i => i.value === entry_form.grade)?.title ?? '' }}
                                </div>
                                <div v-else class="text-caption text-medium-emphasis mb-4">Bitte zuerst Typ wählen</div>
                                <v-date-input v-model="entry_form.date" label="Datum" />
                                <v-textarea v-model="entry_form.description" label="Beschreibung" rows="3" :counter="1024" :maxlength="1024" />
                            </v-form>
                        </v-card-text>
                        <v-divider />
                        <v-card-actions>
                            <v-btn color="warning" variant="tonal" :disabled="isSavingMutation" @click="abortEntry">Abbruch</v-btn>
                            <v-spacer />
                            <v-btn color="success" variant="tonal" :loading="isSavingAction('save-entry')" :disabled="isSavingMutation" @click="saveEntry">{{ entry_form.id ? 'Aktualisieren' : 'Speichern' }}</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="show_star_form" persistent max-width="500">
                    <v-card>
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                            <v-icon size="18">mdi-star</v-icon>
                            {{ star_form.id ? 'Stern bearbeiten' : 'Stern vergeben' }}
                            <v-spacer />
                            <v-btn icon="mdi-close" size="x-small" variant="text" :disabled="isSavingMutation" @click="abortStarEntry" />
                        </v-card-title>
                        <v-divider />
                        <v-card-text>
                            <v-form ref="starForm" @submit.prevent="saveStarEntry">
                                <v-date-input v-model="star_form.date" label="Datum" class="mt-2" />
                                <v-textarea v-model="star_form.comment" label="Kommentar" rows="3" :counter="1024" :maxlength="1024" />
                                <v-alert v-if="starFormFrontendError" type="warning" class="mt-2">{{ starFormFrontendError }}</v-alert>
                            </v-form>
                        </v-card-text>
                        <v-divider />
                        <v-card-actions>
                            <v-btn color="warning" variant="tonal" :disabled="isSavingMutation" @click="abortStarEntry">Abbruch</v-btn>
                            <v-spacer />
                            <v-btn color="success" variant="tonal" :loading="isSavingAction('save-star-entry')" :disabled="!!starFormFrontendError || isSavingMutation" @click="saveStarEntry">{{ star_form.id ? 'Aktualisieren' : 'Speichern' }}</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
                <v-dialog v-model="show_behaviour_grade_dialog" persistent max-width="400">
                    <v-card>
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                            <v-icon size="18">mdi-account-alert</v-icon>
                            Verhalten bearbeiten
                            <v-spacer />
                            <v-btn icon="mdi-close" size="x-small" variant="text" :disabled="isSavingMutation" @click="show_behaviour_grade_dialog = false" />
                        </v-card-title>
                        <v-divider />
                        <v-card-text>
                            <template v-if="semesterCount === 2">
                                <v-text-field v-model="behaviour_grade_form.behaviour_1_grade" label="1. Semester" density="compact" hide-details class="mb-3" />
                                <v-text-field v-model="behaviour_grade_form.behaviour_2_grade" label="2. Semester" density="compact" hide-details />
                            </template>
                            <v-text-field v-else v-model="behaviour_grade_form.behaviour_grade" label="Note" density="compact" hide-details />
                        </v-card-text>
                        <v-card-actions>
                            <v-btn color="warning" variant="tonal" :disabled="isSavingMutation" @click="show_behaviour_grade_dialog = false">Abbruch</v-btn>
                            <v-spacer />
                            <v-btn color="success" variant="tonal" :loading="isSavingAction('save-behaviour-grades')" :disabled="isSavingMutation" @click="saveBehaviourGradesFromDialog">Speichern</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="show_grade_dialog" persistent max-width="400">
                    <v-card>
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                            <v-icon size="18">mdi-school</v-icon>
                            Benotung bearbeiten
                            <v-spacer />
                            <v-btn icon="mdi-close" size="x-small" variant="text" :disabled="isSavingMutation" @click="show_grade_dialog = false" />
                        </v-card-title>
                        <v-divider />
                        <v-card-text>
                            <template v-if="semesterCount === 2">
                                <v-text-field v-model="grade_form.sem_1_grade" label="1. Semester" density="compact" hide-details class="mb-3" />
                                <v-text-field v-model="grade_form.sem_2_grade" label="2. Semester" density="compact" hide-details />
                            </template>
                            <v-text-field v-else v-model="grade_form.sem_grade" label="Note" density="compact" hide-details />
                        </v-card-text>
                        <v-card-actions>
                            <v-btn color="warning" variant="tonal" :disabled="isSavingMutation" @click="show_grade_dialog = false">Abbruch</v-btn>
                            <v-spacer />
                            <v-btn color="success" variant="tonal" :loading="isSavingAction('save-grades')" :disabled="isSavingMutation" @click="saveGradesFromDialog">Speichern</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { parseLocalDate } from '@/helpers/date'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
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
            show_comment_dialog: false,
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
            show_behaviour_grade_dialog: false,
            show_grade_dialog: false,
            saving_action_key: null,
            copiedEmail: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['selected_course', 'selected_course_id', 'selected_course_student', 'show_works', 'show_infos', 'show_dates', 'students_sort_mode']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        ...mapWritableState(useCourseStudentEntryStore, ['entries']),
        ...mapWritableState(useTeachingStore, ['settings']),
        isSavingMutation() {
            return this.saving_action_key !== null
        },
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
            if (sem2.length) {
                result.push({ kind: 'header', key: 'header-sem2', label: '2. Semester' })
                sem2.forEach((e) => result.push({ kind: 'entry', key: `entry-${e.id}`, entry: e }))
            }
            if (sem1.length) {
                result.push({ kind: 'header', key: 'header-sem1', label: '1. Semester' })
                sem1.forEach((e) => result.push({ kind: 'entry', key: `entry-${e.id}`, entry: e }))
            }
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
            if (sem2.length) {
                result.push({ kind: 'header', key: 'notification-header-sem2', label: '2. Semester' })
                sem2.forEach((e) => result.push({ kind: 'entry', key: `notification-${e.id}`, entry: e }))
            }
            if (sem1.length) {
                result.push({ kind: 'header', key: 'notification-header-sem1', label: '1. Semester' })
                sem1.forEach((e) => result.push({ kind: 'entry', key: `notification-${e.id}`, entry: e }))
            }
            return result
        },
        teachingBehaviour() {
            return this.selected_course?.teacher_teaching_behaviour || this.settings?.teaching_behaviour || []
        },
        teachingNotifications() {
            return this.selected_course?.teacher_teaching_notifications || this.settings?.teaching_notifications || []
        },
        showBehaviourEnabled() {
            if (typeof this.selected_course?.teacher_teaching_show_behaviour === 'boolean') {
                return this.selected_course.teacher_teaching_show_behaviour
            }

            return this.settings?.teaching_show_behaviour !== false
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
            const raw = this.selected_course_student?.comment || ''
            const stripped = raw.replace(/<[^>]*>/g, '').trim()
            return stripped ? raw : ''
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
            const courseSchema = this.selected_course?.teacher_teaching_schema
            if (courseSchema?.id) {
                return [courseSchema]
            }

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
            return this.schoolSem2StartDate || this.config?.user?.teaching_count_for_semester_2_date || null
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
            const entries = this.semesterCount === 2
                ? this.entriesForSemester(this.entries || [], 1)
                : (this.filteredEntries || [])
            if (this.hasSingleNaSemesterGrade(entries)) return 5
            return this.totalFromCategoryGroups(this.semester1Groups)
        },
        semester2Total() {
            const entries = this.entriesForSemester(this.entries || [], 2)
            if (this.hasSingleNaSemesterGrade(entries)) return 5
            return this.totalFromCategoryGroups(this.semester2Groups)
        },
        semester1HasMissingCategory() {
            return this.semester1Groups.some((cat) => !cat.rows || !cat.rows.length || cat.isNb || cat.isNa)
        },
        semester2HasMissingCategory() {
            return this.semester2Groups.some((cat) => !cat.rows || !cat.rows.length || cat.isNb || cat.isNa)
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
            const sem1Groups = this.buildCategoryGroups(sem1Entries)
            const sem2Groups = this.buildCategoryGroups(sem2Entries)

            // Semester 1 basis for yearly grade:
            // - use_semester_grade_only = true  -> stored sem_1_grade
            // - otherwise                        -> calculated Semester-1 value
            const sem1ForcedNa = this.hasSingleNaSemesterGrade(sem1Entries)
            const sem1CalculatedValue = sem1ForcedNa ? 5 : this.totalFromCategoryGroups(sem1Groups)
            const sem1CalculatedFormula = sem1ForcedNa
                ? 'Regel: Einziger benoteter Eintrag ist NA (NICHT ABGEGEBEN) -> Semesterwertung 5.00'
                : this.categoryGroupTotalLine(sem1Groups, sem1CalculatedValue)
            const sem1GradeRaw = this.selected_course_student?.sem_1_grade
            const sem1GradeKey = this.normalizeGradeKey(sem1GradeRaw)
            const sem1GradeValue = sem1GradeRaw != null && sem1GradeRaw !== ''
                ? parseFloat(String(sem1GradeRaw).replace(',', '.'))
                : null

            const sem1Source = grading.use_semester_grade_only ? 'semester_grade' : 'calculated'
            const sem1Value = sem1Source === 'semester_grade'
                ? (this.isNbGradeKey(sem1GradeKey) ? 'NB' : (Number.isNaN(sem1GradeValue) ? null : sem1GradeValue))
                : sem1CalculatedValue

            // Semester 2 for yearly grade is based on calculated Semester-2 value.
            const sem2ForcedNa = this.hasSingleNaSemesterGrade(sem2Entries)
            const sem2CalculatedValue = sem2ForcedNa ? 5 : this.totalFromCategoryGroups(sem2Groups)
            const sem2CalculatedFormula = sem2ForcedNa
                ? 'Regel: Einziger benoteter Eintrag ist NA (NICHT ABGEGEBEN) -> Semesterwertung 5.00'
                : this.categoryGroupTotalLine(sem2Groups, sem2CalculatedValue)
            const sem2Value = sem2CalculatedValue

            if (this.isNaGradeKey(sem1Value) || this.isNaGradeKey(sem2Value)) {
                const naGroups = [...sem1Groups.filter((g) => g.isNa), ...sem2Groups.filter((g) => g.isNa)]
                const naLabels = [...new Set(naGroups.map((g) => g.name))]
                return {
                    isNb: true,
                    value: 'NA',
                    sem1Value,
                    sem2Value,
                    sem1CalculatedValue,
                    sem2CalculatedValue,
                    sem1CalculatedFormula,
                    sem2CalculatedFormula,
                    sem1Source,
                    sem1Weight: w1,
                    sem2Weight: w2,
                    totalWeight,
                    sem1SharePercent: Number(((w1 / totalWeight) * 100).toFixed(2)),
                    sem2SharePercent: Number(((w2 / totalWeight) * 100).toFixed(2)),
                    nbReason: naLabels.length
                        ? `NA: Pflichtkategorie "Alle erforderlich" in ${naLabels.join(', ')} hat NA-Eintrag.`
                        : 'NA: Pflichtkategorie "Alle erforderlich" hat NA-Eintrag.',
                }
            }
            if (this.isNbValue(sem1Value) || this.isNbValue(sem2Value)) {
                return {
                    isNb: true,
                    value: 'NB',
                    sem1Value,
                    sem2Value,
                    sem1CalculatedValue,
                    sem2CalculatedValue,
                    sem1CalculatedFormula,
                    sem2CalculatedFormula,
                    sem1Source,
                    sem1Weight: w1,
                    sem2Weight: w2,
                    totalWeight,
                    sem1SharePercent: Number(((w1 / totalWeight) * 100).toFixed(2)),
                    sem2SharePercent: Number(((w2 / totalWeight) * 100).toFixed(2)),
                    nbReason: 'Mindestens eine Pflichtkategorie "Alle erforderlich" ist nicht vollständig beurteilt.',
                }
            }

            if (sem1Value == null || sem2Value == null) return null

            const value = Number((((sem1Value * w1) + (sem2Value * w2)) / totalWeight).toFixed(2))
            const sem1SharePercent = Number(((w1 / totalWeight) * 100).toFixed(2))
            const sem2SharePercent = Number(((w2 / totalWeight) * 100).toFixed(2))
            return {
                isNb: false,
                sem1Value,
                sem2Value,
                sem1CalculatedValue,
                sem2CalculatedValue,
                sem1CalculatedFormula,
                sem2CalculatedFormula,
                sem1Source,
                sem1Weight: w1,
                sem2Weight: w2,
                totalWeight,
                sem1SharePercent,
                sem2SharePercent,
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

            const buildTypeGrouped = (list) => {
                if (!this.sort_by_type) {
                    return list.map((e, index) => ({ kind: 'entry', key: `entry-${e.id}`, entry: e, stripe: index }))
                }
                const result = []
                let stripeIndex = 0
                let lastType = null
                list.forEach((e) => {
                    const type = e.type || ''
                    if (type !== lastType) {
                        result.push({ kind: 'type-header', key: `type-header-${type}`, label: this.workTypeLabel(type) || 'Ohne Typ' })
                        lastType = type
                    }
                    result.push({ kind: 'entry', key: `entry-${e.id}`, entry: e, stripe: stripeIndex })
                    stripeIndex += 1
                })
                return result
            }

            if (this.semesterCount !== 2 || this.activeSemester !== 3) {
                return buildTypeGrouped(entries)
            }
            const boundary = this.displaySem2Boundary()
            if (!boundary) {
                return buildTypeGrouped(entries)
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
            if (sem2.length) {
                result.push({ kind: 'header', key: 'header-sem2', label: '2. Semester' })
                result.push(...buildTypeGrouped(sem2))
            }
            if (sem1.length) {
                result.push({ kind: 'header', key: 'header-sem1', label: '1. Semester' })
                result.push(...buildTypeGrouped(sem1))
            }
            return result
        },
        courseStudentsList() {
            const list = this.selected_course?.students_info || []

            return [...list].sort((a, b) => {
                const canceledA = this.isStudentCanceled(a) ? 1 : 0
                const canceledB = this.isStudentCanceled(b) ? 1 : 0

                if (canceledA !== canceledB) {
                    return canceledA - canceledB
                }

                return this.compareStudentsBySelectedSort(a, b)
            })
        },
        currentStudentIndex() {
            if (!this.selected_course_student) return -1
            return this.courseStudentsList.findIndex(s => s.id === this.selected_course_student.id)
        },
        hasPreviousStudent() {
            return this.currentStudentIndex > 0
        },
        hasNextStudent() {
            return this.currentStudentIndex >= 0 && this.currentStudentIndex < this.courseStudentsList.length - 1
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
        'settings.teaching_show_behaviour'(newValue) {
            if (newValue !== false) {
                return
            }

            this.show_behaviour_form = false
            this.behaviour_form = this.emptyBehaviourForm()
            this.delete_behaviour_id = null
            this.is_editing_behaviour_grades = false
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
        async copyEmail(email) {
            try {
                await navigator.clipboard.writeText(email)
                this.copiedEmail = true
                setTimeout(() => { this.copiedEmail = false }, 1500)
            } catch {
            }
        },
        async runStudentMutation(action, callback) {
            if (this.isSavingMutation) {
                return false
            }

            this.saving_action_key = action
            await this.$nextTick()

            try {
                return await callback()
            } finally {
                this.saving_action_key = null
            }
        },
        isSavingAction(action) {
            return this.saving_action_key === action
        },
        normalizeDateKey(date) {
            if (!date) return ''
            // Keep pure date strings as-is; parse date-time strings in local time.
            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
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
        toInputDate(value) {
            const normalized = this.normalizeDateString(value)
            if (!normalized) return null
            const parsed = parseLocalDate(normalized)
            if (Number.isNaN(parsed.getTime())) return null
            return parsed
        },
        emptyEntryForm() {
            const selectedDate = this.selected_courseDate?.date ? this.toInputDate(this.selected_courseDate.date) : null
            const defaultDate = selectedDate || new Date()
            return {
                id: null,
                type: '',
                grade: '',
                date: defaultDate,
                description: '',
            }
        },
        async loadEntries() {
            if (!this.selected_course?.id || !this.selected_course_student?.user_id) {
                this.entryStore?.clear()
                return
            }
            await this.entryStore.index(this.selected_course.id, this.selected_course_student.user_id)
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
        isStudentCanceled(student) {
            return !!student?.canceled_at || !!student?.deleted_at
        },
        studentClassValue(student) {
            return (student?.schoolclass || student?.class || '').toString()
        },
        compareStudentsBySelectedSort(a, b) {
            const lastA = (a?.last_name || '').toString()
            const lastB = (b?.last_name || '').toString()
            const firstA = (a?.first_name || '').toString()
            const firstB = (b?.first_name || '').toString()
            const classA = this.studentClassValue(a)
            const classB = this.studentClassValue(b)

            if (this.students_sort_mode === 'last_name_first_name') {
                const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                if (lastCmp !== 0) return lastCmp

                const firstCmp = firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
                if (firstCmp !== 0) return firstCmp

                return classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
            }

            const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
            if (classCmp !== 0) return classCmp

            const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
            if (lastCmp !== 0) return lastCmp

            return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
        },
        goToPreviousStudent() {
            if (!this.hasPreviousStudent) return
            const prevStudent = this.courseStudentsList[this.currentStudentIndex - 1]
            if (prevStudent) {
                this.selected_course_student = prevStudent
            }
        },
        goToNextStudent() {
            if (!this.hasNextStudent) return
            const nextStudent = this.courseStudentsList[this.currentStudentIndex + 1]
            if (nextStudent) {
                this.selected_course_student = nextStudent
            }
        },
        editGrades() {
            this.grade_form = {
                sem_1_grade: this.selected_course_student?.sem_1_grade || '',
                sem_2_grade: this.selected_course_student?.sem_2_grade || '',
                sem_grade: this.selected_course_student?.sem_grade || '',
            }
            this.show_grade_dialog = true
        },
        async saveGrades() {
            if (!this.selected_course) return
            await this.runStudentMutation('save-grades', async () => {
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
                    this.show_grade_dialog = false
                }
            })
        },
        async saveGradesFromDialog() {
            await this.saveGrades()
        },
        editComment() {
            this.edit_comment = this.selected_comment || ''
            this.show_comment_dialog = true
        },
        abortEdit() {
            this.is_editing = false
            this.edit_comment = ''
        },
        async saveComment() {
            if (!this.selected_course) return
            await this.runStudentMutation('save-comment', async () => {
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
                    this.show_comment_dialog = false
                    this.is_editing = false
                    this.edit_comment = ''
                }
            })
        },
        newEntry() {
            this.entry_form = this.emptyEntryForm()
            this.show_entry_form = true
        },
        onEntryRowClick(entry) {
            if (!entry) return
            if (this.entryIsDerivedFromWork(entry) && entry.teaching_course_work_id) {
                this.jumpToWork(entry)
                return
            }
            if (!this.entryIsDerivedFromWork(entry)) {
                this.editEntry(entry)
            }
        },
        editEntry(entry) {
            if (!entry) return
            this.entry_form = {
                id: entry.id,
                type: entry.type || '',
                grade: entry.grade || '',
                date: this.toInputDate(entry.date),
                description: entry.description || '',
            }
            this.show_entry_form = true
        },
        abortEntry() {
            this.show_entry_form = false
            this.entry_form = this.emptyEntryForm()
        },
        selectEntryType(value) {
            if (this.entry_form.type === value) {
                this.entry_form.type = null
                this.entry_form.grade = null
            } else {
                this.entry_form.type = value
                this.entry_form.grade = null
            }
        },
        async saveEntry() {
            if (!this.selected_course || !this.selected_course_student) return
            await this.runStudentMutation('save-entry', async () => {
                let ok = false
                if (this.entry_form.id) {
                    const payload = {
                        id: this.entry_form.id,
                        type: this.entry_form.type,
                        grade: this.entry_form.grade,
                        date: this.normalizeDateString(this.entry_form.date) || null,
                        description: this.entry_form.description,
                    }
                    ok = await this.entryStore.update(payload)
                } else {
                    const payload = {
                        teaching_course_id: this.selected_course.id,
                        user_id: this.selected_course_student.user_id,
                        type: this.entry_form.type,
                        grade: this.entry_form.grade,
                        date: this.normalizeDateString(this.entry_form.date) || null,
                        description: this.entry_form.description,
                    }
                    ok = await this.entryStore.store(payload)
                }

                if (ok) {
                    await this.loadEntries()
                    this.abortEntry()
                }
            })
        },
        async deleteEntry(entry) {
            await this.runStudentMutation('delete-entry', async () => {
                const ok = await this.entryStore.destroy(entry.id)
                if (ok) {
                    await this.loadEntries()
                }
                this.delete_entry_id = null
            })
        },
        emptyStarForm() {
            const defaultDate = this.selected_courseDate?.date
                ? this.toDateString?.(parseLocalDate(this.selected_courseDate.date)) || this.toDateString?.(new Date()) || ''
                : this.toDateString?.(new Date()) || ''
            return {
                id: null,
                value: 1,
                date: defaultDate,
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
            await this.runStudentMutation('save-star-entry', async () => {
                this.courseStore.ensureCourseStudentCollections(this.selected_course)

                const newStar = {
                    id: this.star_form.id || (crypto?.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`),
                    value: 1,
                    date: this.star_form.date instanceof Date ? this.toDateString(this.star_form.date) : this.star_form.date || this.toDateString(new Date()),
                    comment: this.star_form.comment.trim(),
                }

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
            })
        },
        async deleteStarEntry(starId) {
            if (!this.selected_course || !this.selected_course_student || !starId) return
            await this.runStudentMutation('delete-star-entry', async () => {
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
            })
        },
        emptyBehaviourForm() {
            const defaultDate = this.selected_courseDate?.date
                ? this.toDateString?.(parseLocalDate(this.selected_courseDate.date)) || this.toDateString?.(new Date()) || ''
                : this.toDateString?.(new Date()) || ''
            return {
                id: null,
                kind: 'behaviour',
                type: '',
                date: defaultDate,
                is_due: false,
                due_date: '',
                is_done: false,
                done_date: '',
                description: '',
            }
        },
        async loadBehaviourEntries() {
            if (!this.selected_course?.id || !this.selected_course_student?.user_id) {
                this.behaviourEntryStore?.clear()
                return
            }
            await this.behaviourEntryStore.index(this.selected_course.id, this.selected_course_student.user_id)
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
            await this.runStudentMutation('save-behaviour-entry', async () => {
                const allowsDue = this.behaviour_form.kind === 'notification'
                const payload = {
                    id: this.behaviour_form.id,
                    teaching_course_id: this.selected_course.id,
                    user_id: this.selected_course_student.user_id,
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
            })
        },
        editBehaviourGrades() {
            this.behaviour_grade_form = {
                behaviour_1_grade: this.selected_course_student?.behaviour_1_grade || '',
                behaviour_2_grade: this.selected_course_student?.behaviour_2_grade || '',
                behaviour_grade: this.selected_course_student?.behaviour_grade || '',
            }
            this.show_behaviour_grade_dialog = true
        },
        async saveBehaviourGrades() {
            if (!this.selected_course) return
            await this.runStudentMutation('save-behaviour-grades', async () => {
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
                    this.show_behaviour_grade_dialog = false
                }
            })
        },
        async saveBehaviourGradesFromDialog() {
            await this.saveBehaviourGrades()
        },
        async deleteBehaviourEntry(entry) {
            await this.runStudentMutation('delete-behaviour-entry', async () => {
                const ok = await this.behaviourEntryStore.destroy(entry.id)
                if (ok) {
                    await this.loadBehaviourEntries()
                }
                this.delete_behaviour_id = null
            })
        },
        async deleteNotificationEntry(entry) {
            await this.runStudentMutation('delete-notification-entry', async () => {
                const ok = await this.behaviourEntryStore.destroy(entry.id)
                if (ok) {
                    await this.loadBehaviourEntries()
                }
                this.delete_notification_id = null
            })
        },
        async completeNotificationToday(entry) {
            if (!entry?.id || !entry?.type || !entry?.due_date) return
            await this.runStudentMutation('complete-notification', async () => {
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
            })
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
            if (!found) return 'Unbekannter Typ'
            return `${found.short_name} - ${found.name}`
        },
        workConfigForType(type) {
            if (!type) return null
            return this.teachingWorks.find((w) => w.short_name === type) || null
        },
        normalizeGradeKey(gradeKey) {
            return String(gradeKey || '').trim().toUpperCase()
        },
        isNaGradeKey(gradeKey) {
            return this.normalizeGradeKey(gradeKey) === 'NA'
        },
        isNbGradeKey(gradeKey) {
            return this.normalizeGradeKey(gradeKey) === 'NB'
        },
        isNbValue(value) {
            if (value == null || value === '') return false
            return this.isNbGradeKey(value)
        },
        defaultGradeForWork(work) {
            if (!work) return ''
            const defaultGrade = String(work.default_grade || '').trim()
            if (!defaultGrade) return ''
            const exists = (work.grades || []).some((grade) => this.normalizeGradeKey(grade?.grade) === this.normalizeGradeKey(defaultGrade))
            return exists ? defaultGrade : ''
        },
        effectiveGradeKeyForEntry(entry, workOverride = null) {
            const effectiveFromApi = String(entry?.effective_grade || '').trim()
            if (effectiveFromApi) return effectiveFromApi

            const direct = String(entry?.grade || '').trim()
            if (direct) return direct

            const work = workOverride || this.workConfigForType(entry?.type)
            return this.defaultGradeForWork(work)
        },
        entryHasDisplayGrade(entry) {
            return this.effectiveGradeKeyForEntry(entry) !== ''
        },
        entryDisplayGrade(entry) {
            return this.effectiveGradeKeyForEntry(entry) || 'offen'
        },
        isGradedEntry(entry, workOverride = null) {
            return this.effectiveGradeKeyForEntry(entry, workOverride) !== ''
        },
        isTypeInRequireAllCategory(type) {
            if (!type) return false
            const categories = this.selectedSchema?.grading?.categories || []
            return categories.some((category) => {
                if (!category?.require_all_entries) return false
                const works = Array.isArray(category.works) ? category.works : []
                return works.some((workItem) => {
                    const shortName = typeof workItem === 'string' ? workItem : workItem?.short_name
                    return String(shortName || '') === String(type)
                })
            })
        },
        hasSingleNaSemesterGrade(entries) {
            const gradedEntries = (entries || []).filter((entry) => this.isGradedEntry(entry))
            if (gradedEntries.length !== 1) return false

            const onlyEntry = gradedEntries[0]
            if (!this.isNaGradeKey(this.effectiveGradeKeyForEntry(onlyEntry))) return false

            if (!this.isTypeInRequireAllCategory(onlyEntry?.type)) return false

            const sameTypeEntries = (entries || []).filter((entry) => entry?.type === onlyEntry?.type)
            const hasUngraded = sameTypeEntries.some((entry) => !this.isGradedEntry(entry))
            return !hasUngraded
        },
        gradeValueForWork(work, gradeKey) {
            if (!work || !gradeKey) return null
            const lookupGrade = this.normalizeGradeKey(gradeKey)
            const grade = (work.grades || []).find((g) => this.normalizeGradeKey(g.grade) === lookupGrade)
            if (!grade || grade.value == null) return null
            const num = parseFloat(String(grade.value).replace(',', '.'))
            return Number.isNaN(num) ? null : num
        },
        pointsGradeForWork(work, points) {
            if (!work || work.calculation !== 'points') return null
            const table = (work.semester_points_table || []).length ? work.semester_points_table : (work.points_table || [])
            const fallbackGrade = (work.semester_points_sonst_grade || work.points_sonst_grade || '').toString().trim()
            if (!table.length) return fallbackGrade || null
            const sorted = [...table].sort((a, b) => (b.min_points ?? 0) - (a.min_points ?? 0))
            const found = sorted.find((row) => points >= (row.min_points ?? 0))
            return found?.grade || fallbackGrade || null
        },
        formatTwoDecimals(value) {
            if (value == null || value === '') return ''
            const num = typeof value === 'number' ? value : parseFloat(String(value).replace(',', '.'))
            if (Number.isNaN(num)) return ''
            return num.toFixed(2)
        },
        formatEvaluationValue(value) {
            if (value == null || value === '') return ''
            if (this.isNbValue(value)) return 'NB'
            if (this.isNaGradeKey(value)) return 'NA'
            const num = typeof value === 'number' ? value : parseFloat(String(value).replace(',', '.'))
            if (!Number.isNaN(num)) return num.toFixed(2)
            return String(value)
        },
        categoryHasBewertung(category) {
            if (!category || category.isNa || category.isNb) return false
            return category.value != null || (category.grade != null && category.grade !== '')
        },
        pointsGradeForAnyWork(points) {
            const pointsWork = this.teachingWorks.find((w) => w.calculation === 'points' && ((w.semester_points_table || []).length || (w.points_table || []).length))
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
            const title = (work.title || '').toString().trim()
            const desc = (work.description || '').toString().trim()
            return title || desc || ''
        },
        entryWorkDescription(entry) {
            const workId = entry?.teaching_course_work_id
            if (!workId) return ''
            const work = (this.courseWorkStore?.courseWorks || []).find((w) => w.id === workId)
            if (!work) return ''
            const title = (work.title || '').toString().trim()
            const desc = (work.description || '').toString().trim()
            // Only return description if title exists (otherwise description is already shown in title chip)
            return title && desc ? desc : ''
        },
        entryWorkComment(entry) {
            const workId = entry?.teaching_course_work_id
            const userId = entry?.user_id
            if (!workId || !userId) return ''
            const work = (this.courseWorkStore?.courseWorks || []).find((w) => w.id === workId)
            if (!work || !Array.isArray(work.groups)) return ''

            // Find the group containing this student
            const group = work.groups.find((g) => Array.isArray(g?.student_ids) && g.student_ids.includes(userId))
            if (!group) return ''

            // Check for individual comment first
            if (Array.isArray(group.comments)) {
                const commentObj = group.comments.find((c) => c?.student_id === userId)
                if (commentObj?.comment) return commentObj.comment.toString().trim()
            }

            // Fall back to group comment
            return (group.comment || '').toString().trim()
        },
        entryComment(entry) {
            if (this.entryIsDerivedFromWork(entry)) {
                return this.entryWorkComment(entry) || ''
            }
            return (entry?.description || '').toString().trim()
        },
        entryInlineDetail(entry) {
            const parts = []
            const title = this.entryWorkTitle(entry)
            if (title) parts.push(title)
            const desc = this.entryWorkDescription(entry)
            if (desc) parts.push(desc)
            const comment = this.entryComment(entry)
            if (comment) parts.push(comment)
            return parts.join(' · ')
        },
        async jumpToWork(entry) {
            if (!entry?.teaching_course_work_id || !this.selected_course?.id) return
            await this.courseWorkStore?.index(this.selected_course.id)
            const work = (this.courseWorkStore?.courseWorks || []).find((w) => w.id === entry.teaching_course_work_id)
            if (!work) return

            // Remember the current student and visibility status so we can return to them after editing the work
            this.courseStore.previous_selected_student = this.selected_course_student
            this.courseStore.previous_show_infos = this.show_infos
            this.courseStore.previous_show_dates = this.show_dates
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
            const entriesByType = new Map()
            ;(entries || []).forEach((entry) => {
                const type = entry?.type
                if (!type) return
                if (!entriesByType.has(type)) entriesByType.set(type, [])
                entriesByType.get(type).push(entry)
            })

            const grouped = categories.map((cat) => {
                const works = (cat.works || []).map((w) => (typeof w === 'string' ? { short_name: w, factor: 100 } : w))
                const rows = []
                const workAverages = []
                const calculationParts = []
                let categoryPointsGrade = null
                const categoryRequireAllEntries = Boolean(cat?.require_all_entries)
                let categoryHasAnyEntries = false
                let categoryHasUngradedEntries = false
                let categoryHasNaEntry = false
                const missingRequiredTypes = []

                works.forEach((workItem) => {
                    const type = workItem.short_name
                    const work = worksByType.get(type)
                    if (!work) return
                    usedTypes.add(type)
                    const workEntries = entriesByType.get(type) || []
                    if (workEntries.length > 0) {
                        categoryHasAnyEntries = true
                    }
                    const hasUngradedEntries = workEntries.some((entry) => !this.isGradedEntry(entry))
                    if (hasUngradedEntries) {
                        categoryHasUngradedEntries = true
                    }
                    if (categoryRequireAllEntries && workEntries.some((entry) => this.isNaGradeKey(this.effectiveGradeKeyForEntry(entry, work)))) {
                        categoryHasNaEntry = true
                    }
                    const requireAllEntries = categoryRequireAllEntries
                    const requireAllEntriesIncomplete = requireAllEntries && workEntries.length > 0 && hasUngradedEntries
                    if (requireAllEntriesIncomplete && !missingRequiredTypes.includes(type)) {
                        missingRequiredTypes.push(type)
                    }

                    const factorPercentRaw = parseFloat(workItem.factor)
                    const factorPercent = Number.isNaN(factorPercentRaw) ? 0 : factorPercentRaw
                    const weight = factorPercent / 100

                    if (work.calculation === 'points') {
                        const values = workEntries
                            .map((entry) => this.gradeValueForWork(work, this.effectiveGradeKeyForEntry(entry, work)))
                            .filter((val) => val !== null)
                        const sum = values.reduce((s, v) => s + v, 0)
                        const rounded = Number.isInteger(sum) ? sum : Number(sum.toFixed(2))
                        const grade = this.pointsGradeForWork(work, rounded)
                        rows.push({
                            key: `sum-${type}`,
                            type,
                            sum: rounded,
                            grade,
                            requireAllEntries,
                            requireAllEntriesIncomplete,
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
                            requireAllEntries,
                            requireAllEntriesIncomplete,
                        })
                        return
                    }

                    const values = workEntries
                        .map((entry) => this.gradeValueForWork(work, this.effectiveGradeKeyForEntry(entry, work)))
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
                        requireAllEntries,
                        requireAllEntriesIncomplete,
                    })

                    workEntries.forEach((entry) => {
                        const effectiveGrade = this.effectiveGradeKeyForEntry(entry, work)
                        const value = this.gradeValueForWork(work, effectiveGrade)
                        rows.push({
                            key: `entry-${entry.id}`,
                            type,
                            workTitle: this.entryWorkTitle(entry),
                            value: value !== null ? value : (effectiveGrade || 'NA'),
                            date: entry.date || null,
                            requireAllEntries,
                            requireAllEntriesIncomplete,
                        })
                    })
                })

                const categoryIsNb = categoryRequireAllEntries && categoryHasAnyEntries && categoryHasUngradedEntries
                const categoryIsNa = categoryRequireAllEntries && categoryHasNaEntry
                let categoryValue = null
                let categoryGrade = null
                if (categoryIsNa) {
                    categoryGrade = 'NA'
                } else if (categoryIsNb) {
                    categoryGrade = 'NB'
                } else if (workAverages.length) {
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
                    isNb: categoryIsNb,
                    isNa: categoryIsNa,
                    requireAllEntries: categoryRequireAllEntries,
                    missingRequiredTypes,
                }
            })

            const undefinedRows = []
            ;(entries || []).forEach((entry) => {
                if (!entry.type || usedTypes.has(entry.type)) return
                const work = worksByType.get(entry.type)
                const typeEntries = entriesByType.get(entry.type) || []
                const requireAllEntries = false
                const requireAllEntriesIncomplete = false
                if (work && work.calculation === 'points') {
                    const values = typeEntries
                        .map((e) => this.gradeValueForWork(work, this.effectiveGradeKeyForEntry(e, work)))
                        .filter((val) => val !== null)
                    const sum = values.length ? values.reduce((s, v) => s + v, 0) : 0
                    const rounded = Number.isInteger(sum) ? sum : Number(sum.toFixed(2))
                    if (!undefinedRows.some((r) => r.key === `sum-${entry.type}`)) {
                        undefinedRows.push({
                            key: `sum-${entry.type}`,
                            type: entry.type,
                            sum: rounded,
                            grade: this.pointsGradeForWork(work, rounded),
                            requireAllEntries,
                            requireAllEntriesIncomplete,
                        })
                    }
                    return
                }

                const effectiveGrade = work ? this.effectiveGradeKeyForEntry(entry, work) : this.effectiveGradeKeyForEntry(entry)
                const value = work ? this.gradeValueForWork(work, effectiveGrade) : effectiveGrade
                undefinedRows.push({
                    key: `entry-${entry.id}`,
                    type: entry.type,
                    workTitle: this.entryWorkTitle(entry),
                    value: value !== null ? value : (effectiveGrade || 'NA'),
                    date: entry.date || null,
                    requireAllEntries,
                    requireAllEntriesIncomplete,
                })
            })

            if (undefinedRows.length) {
                grouped.push({
                    name: 'Undefiniert',
                    rows: undefinedRows,
                    grade: null,
                    isNb: false,
                    requireAllEntries: false,
                    missingRequiredTypes: [],
                })
            }

            return grouped
        },
        totalFromCategoryGroups(groups) {
            if (!groups?.length) return null
            if (groups.some((cat) => cat?.isNa)) return 'NA'
            if (groups.some((cat) => cat?.isNb)) return 'NB'
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
        categoryGroupTotalLine(groups, totalValue) {
            if (totalValue == null || !groups?.length) return ''
            if (this.isNaGradeKey(totalValue) || groups.some((group) => group?.isNa)) {
                const labels = groups
                    .filter((group) => group?.isNa)
                    .map((group) => group.name)
                if (!labels.length) {
                    return 'Berechnung: NA'
                }
                return `Berechnung: NA (Pflichtkategorie "Alle erforderlich" in ${labels.join(', ')} hat NA-Eintrag)`
            }
            if (this.isNbValue(totalValue) || groups.some((group) => group?.isNb)) {
                const labels = groups
                    .filter((group) => group?.isNb)
                    .map((group) => group.name)
                if (!labels.length) {
                    return 'Berechnung: NB'
                }
                return `Berechnung: NB (Pflichtkategorie "Alle erforderlich" in ${labels.join(', ')} nicht vollständig beurteilt)`
            }

            const weightedCats = groups
                .map((cat) => {
                    const value = cat.value != null ? cat.value : cat.grade != null ? parseFloat(String(cat.grade).replace(',', '.')) : null
                    const weightPercent = parseFloat(cat.weight) || 0
                    return {
                        value,
                        weightPercent,
                    }
                })
                .filter((cat) => cat.value != null && !Number.isNaN(cat.value) && cat.weightPercent > 0)

            if (!weightedCats.length) return ''

            const denominator = weightedCats.reduce((sum, cat) => sum + cat.weightPercent, 0)
            if (denominator <= 0) return ''

            const terms = weightedCats.map((cat) => {
                const sharePercent = (cat.weightPercent / denominator) * 100
                return `${this.formatTwoDecimals(cat.value)} * ${this.formatTwoDecimals(sharePercent)}%`
            })
            return `Berechnung: ${terms.join(' + ')} = ${this.formatTwoDecimals(totalValue)}`
        },
        categoryCalculationLine(category) {
            if (category?.isNb || category?.isNa) return ''
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
        setDueInAWeek() {
            const d = new Date()
            d.setDate(d.getDate() + 7)
            this.behaviour_form.due_date = this.toDateString(d)
        },
        setDueNextLesson() {
            if (!this.nextCourseLessonDate) return
            this.behaviour_form.due_date = this.nextCourseLessonDate
        },
        normalizeDateString(date) {
            if (!date) return ''
            // If it's a Date object, convert to string
            if (date instanceof Date) {
                return this.toDateString(date)
            }
            // Keep pure date strings as-is.
            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
                return date
            }
            // For date-time strings (including timezone), normalize via local parse
            // to avoid day shifts between list display and date-input edit mode.
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) return ''
            return this.toDateString(parsed)
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
.benotung-fieldset {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity, 0.12));
    border-radius: 8px;
    padding: 0;
    margin: 0;
}

.benotung-fieldset legend {
    margin-left: 8px;
    font-size: 0.75rem;
}

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

.auswertung-semester-card {
    margin-bottom: 16px;
    border: 1px solid rgba(var(--v-theme-primary), 0.18);
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(var(--v-theme-primary), 0.08) 0%, rgba(var(--v-theme-surface), 0.96) 100%);
    overflow: hidden;
}

.auswertung-semester-card--semester-2 {
    border-color: rgba(var(--v-theme-secondary), 0.2);
    background: linear-gradient(180deg, rgba(var(--v-theme-secondary), 0.08) 0%, rgba(var(--v-theme-surface), 0.96) 100%);
}

.auswertung-category-card {
    margin: 8px 12px;
    border: 1px solid rgba(var(--v-theme-primary), 0.12);
    border-radius: 12px;
    background: rgba(var(--v-theme-surface), 0.92);
    box-shadow:
        0 8px 18px rgba(var(--v-theme-primary), 0.08),
        inset 0 1px 0 rgba(var(--v-theme-primary), 0.04);
}

.auswertung-total-card {
    margin: 12px;
    border: 1px solid rgba(var(--v-theme-primary), 0.28);
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.18) 0%, rgba(var(--v-theme-primary), 0.08) 100%);
    box-shadow: 0 10px 24px rgba(var(--v-theme-primary), 0.12);
}

.auswertung-total-card--semester-2 {
    border-color: rgba(var(--v-theme-secondary), 0.3);
    background: linear-gradient(135deg, rgba(var(--v-theme-secondary), 0.18) 0%, rgba(var(--v-theme-secondary), 0.08) 100%);
    box-shadow: 0 10px 24px rgba(var(--v-theme-secondary), 0.12);
}

.auswertung-sum-card {
    padding: 14px 16px;
    border: 1px solid rgba(var(--v-theme-secondary), 0.32);
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(var(--v-theme-secondary), 0.18) 0%, rgba(var(--v-theme-secondary), 0.08) 100%);
    box-shadow: 0 12px 28px rgba(var(--v-theme-secondary), 0.12);
}

.sum-formula {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sum-formula-columns {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.sum-formula-columns--stacked {
    grid-template-columns: minmax(0, 1fr);
}

.sum-formula-column {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(var(--v-theme-surface), 0.72);
}

.sum-formula-column-label {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-surface), 0.7);
}

.sum-formula-column-share {
    font-size: 2rem;
    line-height: 1;
    font-weight: 800;
    color: rgb(var(--v-theme-primary));
}

.sum-formula-column-grade {
    font-size: 1.15rem;
    line-height: 1.2;
    font-weight: 700;
    color: rgba(var(--v-theme-on-surface), 0.88);
}

.sum-formula-result-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 16px 18px;
    border: 1px solid rgba(var(--v-theme-secondary), 0.28);
    border-radius: 14px;
    background: rgba(var(--v-theme-surface), 0.82);
    text-align: center;
}

.sum-formula-result-label {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.sum-formula-result-value {
    font-size: 2rem;
    line-height: 1;
    font-weight: 800;
}

.sum-formula-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 0.95rem;
    font-weight: 600;
}

.entry-work-title-line {
    padding-left: 4px;
    font-weight: 500;
    line-height: 1.35;
}

.auswertung-entry-title {
    padding-left: 4px;
    line-height: 1.35;
}

.auswertung-entry-work-title {
    padding-left: 4px;
    line-height: 1.35;
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

    .entry-work-title-line {
        width: 100%;
    }

    .sum-formula-columns {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
