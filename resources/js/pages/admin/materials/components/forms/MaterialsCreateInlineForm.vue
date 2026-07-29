<template>
    <v-card class="create-form-card pa-5 pa-md-6" rounded="xl" elevation="0">
        <div class="d-flex align-start justify-space-between flex-wrap ga-2 mb-1">
            <div class="text-h6 font-weight-bold">{{ formTitle }}</div>
        </div>

        <div class="d-flex align-center justify-space-between flex-wrap ga-2 mb-2">
            <div class="d-flex align-center flex-wrap ga-2">
                <v-menu location="bottom end" :disabled="isReadOnly">
                    <template #activator="{ props: statusMenuActivatorProps }">
                        <v-chip
                            v-bind="statusMenuActivatorProps"
                            size="small"
                            variant="flat"
                            :color="currentStatusColor"
                            append-icon="mdi-chevron-down"
                            class="status-chip"
                            :disabled="isReadOnly">
                            {{ currentStatusLabel }}
                        </v-chip>
                    </template>

                    <v-list density="comfortable" style="min-width: 210px;">
                        <v-list-subheader>Status ändern</v-list-subheader>
                        <v-list-item
                            v-for="option in normalizedStatusOptions"
                            :key="`status-option-${option.value}`"
                            :active="option.value === normalizedStatusValue"
                            @click="selectStatus(option.value)">
                            <v-list-item-title>{{ option.label }}</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>

                <v-menu location="bottom end" :disabled="isReadOnly">
                    <template #activator="{ props: typeMenuActivatorProps }">
                        <v-chip
                            v-bind="typeMenuActivatorProps"
                            size="small"
                            :variant="normalizedMaterialTypeValue ? 'flat' : 'tonal'"
                            :color="currentMaterialTypeColor"
                            append-icon="mdi-chevron-down"
                            class="type-chip"
                            :disabled="isReadOnly">
                            {{ currentMaterialTypeLabel }}
                        </v-chip>
                    </template>

                    <v-list density="comfortable" style="min-width: 230px;">
                        <v-list-subheader>Typ ändern</v-list-subheader>
                        <v-list-item
                            :active="normalizedMaterialTypeValue === ''"
                            @click="selectMaterialType('')">
                            <v-list-item-title>Kein Typ</v-list-item-title>
                        </v-list-item>
                        <v-list-item
                            v-for="option in normalizedTypeOptions"
                            :key="`type-option-${option.id || option.value}`"
                            :active="option.value === normalizedMaterialTypeValue"
                            @click="selectMaterialType(option.value)">
                            <v-list-item-title>{{ option.label }}</v-list-item-title>
                        </v-list-item>

                        <template v-if="canManageTypes">
                            <v-divider class="my-1" />
                            <v-list-item prepend-icon="mdi-cog-outline" @click="openTypeManager">
                                <v-list-item-title>Typen verwalten</v-list-item-title>
                            </v-list-item>
                        </template>
                    </v-list>
                </v-menu>
            </div>

            <div class="d-flex align-center justify-end flex-wrap ga-2">
                <v-btn
                    size="small"
                    variant="text"
                    :disabled="isSaving"
                    @click="$emit('cancel')">
                    {{ cancelLabel }}
                </v-btn>
                <v-btn
                    size="small"
                    color="primary"
                    variant="flat"
                    :loading="isSaving"
                    :disabled="!canSave || isSaving"
                    @click="handleSave">
                    {{ saveLabel }}
                </v-btn>
            </div>
        </div>

        <div class="text-body-2 form-subline mb-4">{{ formSubline }}</div>

        <v-text-field
            ref="titleField"
            :model-value="title"
            label="Titel *"
            placeholder="z. B. Bruchrechnen Arbeitsblatt"
            variant="outlined"
            density="comfortable"
            clearable
            required
            :autofocus="autofocusTitle"
            :disabled="isReadOnly"
            hide-details="auto"
            @update:modelValue="$emit('update:title', $event)" />

        <v-textarea
            :model-value="description"
            label="Beschreibung (optional)"
            placeholder="Kurze Beschreibung"
            variant="outlined"
            density="comfortable"
            rows="3"
            auto-grow
            hide-details="auto"
            class="mt-3"
            :disabled="isReadOnly"
            @update:modelValue="$emit('update:description', $event)" />

        <div class="mt-2">
            <div class="d-flex align-center justify-space-between mb-1">
                <div class="text-subtitle-2">{{ classificationSectionLabel }}</div>
                <div class="d-flex justify-end ga-2">
                    <v-btn
                        icon="mdi-plus"
                        size="x-small"
                        variant="tonal"
                        color="primary"
                        :disabled="isClassificationReadOnly"
                        @click="addClassificationAndOpenEditor" />
                </div>
            </div>

            <v-card
                variant="tonal"
                color="primary"
                class="pa-2 mb-2">
                <div v-if="assignedClassificationItems.length" class="d-flex flex-wrap ga-2">
                    <v-chip
                        v-for="item in assignedClassificationItems"
                        :key="`assigned-classification-${item.key}`"
                        size="small"
                        :variant="classificationEditorVisible && activeClassificationIndex === item.index ? 'flat' : 'outlined'"
                        color="primary"
                        class="assigned-chip"
                        :disabled="isClassificationReadOnly"
                        @click="openClassificationEditor(item.index)">
                        {{ item.label }}
                    </v-chip>
                </div>

                <div v-else class="text-body-2 text-medium-emphasis">Noch keine Zuordnung vorhanden.</div>
            </v-card>

            <transition name="classification-fade">
                <div v-show="classificationEditorVisible">
                    <v-row
                        v-for="entry in editableClassificationRows"
                        :key="`classification-${entry.index}`"
                        dense
                        class="classification-row mb-1"
                        :class="{ 'classification-row-active': activeClassificationIndex === entry.index }">
                        <v-col cols="12" class="py-1">
                            <div class="classification-chip-label mb-0">Fach</div>
                            <v-chip-group
                                :model-value="normalizedClassificationDraft.subject"
                                column
                                :disabled="isClassificationReadOnly"
                                selected-class="classification-option-chip--selected"
                                @update:modelValue="updateClassificationDraftField('subject', $event)">
                                <v-chip
                                    value=""
                                    size="small"
                                    variant="outlined"
                                    filter>
                                    Ohne Fach
                                </v-chip>
                                <v-chip
                                    v-for="subject in subjectOptions"
                                    :key="`classification-subject-${entry.index}-${subject}`"
                                    :value="subject"
                                    size="small"
                                    variant="outlined"
                                    filter>
                                    {{ subject }}
                                </v-chip>
                            </v-chip-group>
                        </v-col>

                        <v-col v-if="!!normalizeText(normalizedClassificationDraft.subject)" cols="12" class="py-1">
                            <div class="d-flex align-center justify-space-between mb-0">
                                <div class="classification-chip-label mb-0">Thema</div>
                                <v-btn
                                    icon="mdi-plus"
                                    size="x-small"
                                    variant="text"
                                    color="primary"
                                    :disabled="isClassificationReadOnly || isSaving || classificationCreateSaving || !canOpenTopicCreate"
                                    :title="canOpenTopicCreate ? 'Thema hinzufügen' : 'Zuerst Fach wählen'"
                                    @click="openClassificationCreateField('topic')" />
                            </div>
                            <div
                                v-if="classificationCreateField === 'topic'"
                                class="classification-inline-create d-flex flex-wrap align-center ga-2 mt-2 mb-2">
                                <v-text-field
                                    :model-value="classificationCreateValue"
                                    label="Neues Thema"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    class="classification-inline-create-field"
                                    :disabled="isClassificationReadOnly || isSaving || classificationCreateSaving"
                                    @update:modelValue="classificationCreateValue = normalizeText($event)"
                                    @keyup.enter="submitClassificationCreateField"
                                    @keydown.esc="cancelClassificationCreateField" />
                                <v-btn
                                    icon="mdi-check"
                                    size="small"
                                    variant="flat"
                                    color="primary"
                                    :loading="classificationCreateSaving"
                                    :disabled="isClassificationReadOnly || !canSubmitClassificationCreate"
                                    @click="submitClassificationCreateField" />
                                <v-btn
                                    icon="mdi-close"
                                    size="small"
                                    variant="text"
                                    color="warning"
                                    :disabled="isClassificationReadOnly || classificationCreateSaving"
                                    @click="cancelClassificationCreateField" />
                            </div>
                            <v-chip-group
                                :model-value="normalizedClassificationDraft.topic"
                                column
                                :disabled="isClassificationReadOnly || !normalizeText(normalizedClassificationDraft.subject)"
                                selected-class="classification-option-chip--selected"
                                @update:modelValue="updateClassificationDraftField('topic', $event)">
                                <v-chip
                                    value=""
                                    size="small"
                                    variant="outlined"
                                    filter>
                                    Ohne Thema
                                </v-chip>
                                <v-chip
                                    v-for="topic in topicOptionsFor(normalizedClassificationDraft)"
                                    :key="`classification-topic-${entry.index}-${topic}`"
                                    :value="topic"
                                    size="small"
                                    variant="outlined"
                                    filter>
                                    {{ topic }}
                                </v-chip>
                            </v-chip-group>
                        </v-col>

                        <v-col v-if="!!normalizeText(normalizedClassificationDraft.topic)" cols="12" class="py-1">
                            <div class="d-flex align-center justify-space-between mb-0">
                                <div class="classification-chip-label mb-0">Bereich</div>
                                <v-btn
                                    icon="mdi-plus"
                                    size="x-small"
                                    variant="text"
                                    color="primary"
                                    :disabled="isClassificationReadOnly || isSaving || classificationCreateSaving || !canOpenUnitCreate"
                                    :title="canOpenUnitCreate ? 'Bereich hinzufügen' : 'Zuerst Thema wählen'"
                                    @click="openClassificationCreateField('unit')" />
                            </div>
                            <div
                                v-if="classificationCreateField === 'unit'"
                                class="classification-inline-create d-flex flex-wrap align-center ga-2 mt-2 mb-2">
                                <v-text-field
                                    :model-value="classificationCreateValue"
                                    label="Neuer Bereich"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    class="classification-inline-create-field"
                                    :disabled="isClassificationReadOnly || isSaving || classificationCreateSaving"
                                    @update:modelValue="classificationCreateValue = normalizeText($event)"
                                    @keyup.enter="submitClassificationCreateField"
                                    @keydown.esc="cancelClassificationCreateField" />
                                <v-btn
                                    icon="mdi-check"
                                    size="small"
                                    variant="flat"
                                    color="primary"
                                    :loading="classificationCreateSaving"
                                    :disabled="isClassificationReadOnly || !canSubmitClassificationCreate"
                                    @click="submitClassificationCreateField" />
                                <v-btn
                                    icon="mdi-close"
                                    size="small"
                                    variant="text"
                                    color="warning"
                                    :disabled="isClassificationReadOnly || classificationCreateSaving"
                                    @click="cancelClassificationCreateField" />
                            </div>
                            <v-chip-group
                                :model-value="normalizedClassificationDraft.unit"
                                column
                                :disabled="isClassificationReadOnly || !normalizeText(normalizedClassificationDraft.topic)"
                                selected-class="classification-option-chip--selected"
                                @update:modelValue="updateClassificationDraftField('unit', $event)">
                                <v-chip
                                    value=""
                                    size="small"
                                    variant="outlined"
                                    filter>
                                    Ohne Bereich
                                </v-chip>
                                <v-chip
                                    v-for="unit in unitOptionsFor(normalizedClassificationDraft)"
                                    :key="`classification-unit-${entry.index}-${unit}`"
                                    :value="unit"
                                    size="small"
                                    variant="outlined"
                                    filter>
                                    {{ unit }}
                                </v-chip>
                            </v-chip-group>
                        </v-col>

                        <v-col v-if="!!normalizeText(normalizedClassificationDraft.subject)" cols="12" class="py-1">
                            <div class="classification-action-row mt-2">
                                <v-btn
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="flat"
                                    color="error"
                                    class="classification-action-btn"
                                    :title="'Zuordnung löschen'"
                                    :disabled="isClassificationReadOnly || isSaving || classificationCreateSaving || isClassificationRowEmpty(entry.row)"
                                    @click="removeClassificationRow(entry.index)" />
                                <v-btn
                                    icon="mdi-close"
                                    size="small"
                                    variant="flat"
                                    color="warning"
                                    class="classification-action-btn"
                                    @click="closeClassificationEditor" />
                                <v-btn
                                    icon="mdi-check"
                                    size="small"
                                    variant="flat"
                                    color="primary"
                                    class="classification-action-btn"
                                    :disabled="isClassificationReadOnly || !canApplyClassificationDraft"
                                    @click="applyClassificationDraft" />
                            </div>
                        </v-col>
                    </v-row>
                </div>
            </transition>
        </div>

        <div v-if="showContentTools" class="mt-3">
            <div class="text-subtitle-2 mb-2">Inhalt hinzufügen</div>
            <div class="d-flex flex-wrap ga-2">
                <v-btn
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-text-box-plus-outline"
                    :disabled="isSaving"
                    @click="openTextAttachmentDialog">
                    Text hinzufügen
                </v-btn>
                <v-btn
                    variant="tonal"
                    color="primary"
                    :prepend-icon="clipboardPasteArmed ? 'mdi-keyboard-outline' : 'mdi-clipboard-plus-outline'"
                    :loading="isReadingClipboard"
                    :disabled="isSaving || isReadingClipboard"
                    @click="importAttachmentsFromClipboard">
                    {{ clipboardPasteArmed ? 'Jetzt Strg+V' : 'Zwischenablage einfügen' }}
                </v-btn>
            </div>

            <div
                class="mt-3"
                @dragover.capture="onAttachmentDragOver"
                @drop.capture="onAttachmentDrop"
                @paste.capture="onAttachmentPaste">
                <file-pond
                    ref="pond"
                    name="file"
                    allow-multiple
                    :chunk-uploads="true"
                    :chunk-force="true"
                    :allow-revert="false"
                    :allow-remove="true"
                    :instant-upload="true"
                    :before-add-file="beforeAddFile"
                    :label-idle="'<strong>Dateien hierher ziehen oder <i>klicken</i></strong>'"
                    :label-file-processing-complete="'OK'"
                    :server="pondServerConfig"
                    @processfile="onProcessFile"
                    @processfileerror="onProcessFileError"
                    @error="onProcessFileError"
                    v-if="csrfToken" />
            </div>

            <div class="text-caption text-medium-emphasis mt-2">
                Maximale Uploadgröße je Datei: {{ maxUploadSizeLabel }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
                Du kannst auch einen Web-Link oder ein Web-Bild hierher ziehen.
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
                Oder Inhalte per Zwischenablage einfügen (Bild, Datei, Link).
            </div>

            <v-alert
                v-if="clipboardImportStatus.message && !clipboardPasteArmed"
                :type="clipboardImportStatus.type"
                variant="tonal"
                density="compact"
                class="mt-2">
                {{ clipboardImportStatus.message }}
            </v-alert>

            <v-alert
                v-if="clipboardPasteArmed"
                type="warning"
                variant="flat"
                icon="mdi-keyboard-outline"
                class="mt-2">
                Jetzt bitte <strong>STRG+V</strong> drücken.
            </v-alert>

            <v-textarea
                v-if="clipboardPasteArmed"
                ref="clipboardPasteField"
                :model-value="clipboardPasteBuffer"
                label="Jetzt Strg+V hier einfügen"
                variant="outlined"
                density="comfortable"
                rows="2"
                auto-grow
                hide-details="auto"
                class="mt-2"
                @update:modelValue="clipboardPasteBuffer = $event"
                @paste.capture="onAttachmentPaste"
                @keydown.esc="disarmClipboardPasteFallback" />

            <v-card
                v-if="normalizedPendingAttachments.length"
                variant="outlined"
                class="pa-3 mt-3">
                <div class="text-caption text-medium-emphasis mb-2">Anhänge zur Übernahme</div>

                <div
                    v-for="(item, index) in normalizedPendingAttachments"
                    :key="`pending-attachment-${item.key}`"
                    class="pending-file-row mb-2">
                    <div class="d-flex align-center ga-2 mb-1">
                        <v-chip
                            size="x-small"
                            variant="tonal"
                            :color="item.attachmentType === 'link' ? 'secondary' : 'primary'"
                            :prepend-icon="item.attachmentType === 'link' ? 'mdi-link-variant' : 'mdi-paperclip'"
                            class="link-copy-chip"
                            @click="copyPendingAttachmentChip(item)">
                            {{ item.attachmentType === 'link' ? 'Link' : 'Datei' }}
                        </v-chip>
                    </div>

                    <div class="d-flex align-start ga-2">
                        <v-text-field
                            class="flex-grow-1"
                            :model-value="item.title"
                            label="Titel"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            @update:modelValue="updatePendingAttachmentTitle(index, $event)" />

                        <v-btn
                            :icon="isPendingAttachmentDeleteArmed(item.key) ? 'mdi-delete' : 'mdi-delete-outline'"
                            size="small"
                            variant="text"
                            :color="isPendingAttachmentDeleteArmed(item.key) ? 'error' : 'warning'"
                            class="mt-1"
                            @click="removePendingAttachment(index)" />
                        <v-btn
                            v-if="isPendingAttachmentDeleteArmed(item.key)"
                            icon="mdi-undo"
                            size="small"
                            variant="text"
                            color="success"
                            class="mt-1"
                            @click="cancelPendingAttachmentDelete(item.key)" />
                    </div>

                    <div class="text-caption text-medium-emphasis mt-1 pending-source-text">
                        {{ item.attachmentType === 'link' ? (item.url || 'Link') : (item.fileName || 'Datei') }}
                    </div>
                </div>
            </v-card>
        </div>

        <v-dialog v-model="textAttachmentDialogOpen" max-width="860" persistent>
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Text als Anhang</span>
                    <v-btn icon="mdi-close" variant="text" :disabled="isSaving" @click="closeTextAttachmentDialog" />
                </v-card-title>

                <v-card-text>
                    <v-text-field
                        v-model="textAttachmentDraftTitle"
                        label="Dateiname/Titel"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-3"
                        :disabled="isSaving" />

                    <ItsRichTextEditor v-model="textAttachmentDraftContent" />

                    <div class="text-caption text-medium-emphasis mt-2">
                        Der Inhalt wird als HTML-Datei gespeichert und beim Speichern des Materials als Anhang übernommen.
                    </div>

                    <v-alert
                        v-if="textAttachmentDialogError"
                        type="warning"
                        variant="tonal"
                        density="compact"
                        class="mt-3">
                        {{ textAttachmentDialogError }}
                    </v-alert>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="isSaving" @click="closeTextAttachmentDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-content-save-outline"
                        :disabled="isSaving || !canAddTextAttachment"
                        @click="appendTextAttachment">
                        Als Anhang übernehmen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <slot name="extra-content" />

        <v-alert
            v-if="!isReadOnly && !hasClassification"
            type="warning"
            variant="tonal"
            density="compact"
            class="mt-4">
            Es muss mindestens eine Zuordnung (Fach/Thema/Bereich) angegeben werden.
        </v-alert>

        <div class="d-flex flex-wrap justify-space-between align-center ga-2 mt-5">
            <div><slot name="bottom-left" /></div>
            <div class="d-flex flex-wrap ga-2">
                <v-btn variant="text" :disabled="isSaving" @click="$emit('cancel')">{{ cancelLabel }}</v-btn>
                <v-btn color="primary" variant="flat" :loading="isSaving" :disabled="!canSave || isSaving" @click="$emit('save')">{{ saveLabel }}</v-btn>
            </div>
        </div>
    </v-card>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import 'filepond/dist/filepond.min.css'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'

const FilePond = vueFilePond(FilePondPluginFileValidateType)
const ItsRichTextEditor = defineAsyncComponent(() => import('@/components/ItsRichTextEditor.vue'))

export default {
    name: 'MaterialsCreateInlineForm',
    components: {
        FilePond,
        ItsRichTextEditor,
    },
    props: {
        title: {
            type: String,
            default: '',
        },
        description: {
            type: String,
            default: '',
        },
        materialType: {
            type: String,
            default: '',
        },
        pendingAttachments: {
            type: Array,
            default: () => [],
        },
        classifications: {
            type: Array,
            default: () => [],
        },
        typeOptions: {
            type: Array,
            default: () => [],
        },
        canManageTypes: {
            type: Boolean,
            default: false,
        },
        status: {
            type: String,
            default: 'inbox',
        },
        statusOptions: {
            type: Array,
            default: () => [],
        },
        classificationTree: {
            type: Array,
            default: () => [],
        },
        classificationEditorVisible: {
            type: Boolean,
            default: true,
        },
        classificationToggleable: {
            type: Boolean,
            default: false,
        },
        isSaving: {
            type: Boolean,
            default: false,
        },
        formTitle: {
            type: String,
            default: 'Neues Material anlegen',
        },
        formSubline: {
            type: String,
            default: 'Gib einen Titel ein, dann kann gespeichert werden.',
        },
        saveLabel: {
            type: String,
            default: 'Speichern',
        },
        cancelLabel: {
            type: String,
            default: 'Abbrechen',
        },
        autofocusTitle: {
            type: Boolean,
            default: true,
        },
        maxUploadSizeKb: {
            type: Number,
            default: 20480,
        },
        isReadOnly: {
            type: Boolean,
            default: false,
        },
        classificationReadOnly: {
            type: Boolean,
            default: false,
        },
        requireUnitClassification: {
            type: Boolean,
            default: false,
        },
        showContentTools: {
            type: Boolean,
            default: true,
        },
    },
    emits: ['update:title', 'update:description', 'update:materialType', 'update:pendingAttachments', 'add-files', 'remove-temp-upload', 'upload-error', 'update:status', 'update:classifications', 'update:classificationEditorVisible', 'manage-types', 'save', 'cancel'],
    data() {
        return {
            activeClassificationIndex: null,
            newlyAddedClassificationIndex: null,
            csrfToken: null,
            classificationDraft: {
                subject: '',
                topic: '',
                unit: '',
            },
            classificationDraftDirty: false,
            classificationCreateField: '',
            classificationCreateValue: '',
            classificationCreateSaving: false,
            pendingAttachmentDeleteArmedKeys: [],
            isReadingClipboard: false,
            clipboardPasteArmed: false,
            clipboardPasteTimeoutId: null,
            clipboardPasteBuffer: '',
            clipboardImportStatus: {
                type: 'info',
                message: '',
            },
            textAttachmentDialogOpen: false,
            textAttachmentDraftTitle: '',
            textAttachmentDraftContent: '',
            textAttachmentDialogError: '',
        }
    },
    watch: {
        pendingAttachments: {
            deep: true,
            handler() {
                const validKeys = this.normalizedPendingAttachments
                    .map((item) => this.normalizeText(item?.key))
                    .filter((key) => key !== '')
                this.resetPendingAttachmentDeleteArmed(validKeys)
            },
        },
        classificationEditorVisible(nextValue) {
            if (!nextValue) {
                this.activeClassificationIndex = null
                this.newlyAddedClassificationIndex = null
                this.classificationDraft = this.emptyClassificationRow()
                this.classificationDraftDirty = false
                this.resetClassificationCreateState()
                return
            }

            const rows = this.visibleClassificationRows
            let index = Number.isInteger(this.activeClassificationIndex) ? this.activeClassificationIndex : 0
            if (index < 0) index = 0
            if (index >= rows.length) index = rows.length - 1
            this.activeClassificationIndex = index
            this.loadClassificationDraft(index)
        },
        activeClassificationIndex(nextValue) {
            if (!this.classificationEditorVisible) return
            if (!Number.isInteger(nextValue)) return
            this.loadClassificationDraft(nextValue)
        },
        classifications: {
            deep: true,
            handler() {
                if (this.activeClassificationIndex === null) return
                const maxIndex = this.visibleClassificationRows.length - 1
                if (this.activeClassificationIndex > maxIndex) {
                    this.activeClassificationIndex = maxIndex
                }
                if (
                    this.newlyAddedClassificationIndex !== null &&
                    this.newlyAddedClassificationIndex > maxIndex
                ) {
                    this.newlyAddedClassificationIndex = null
                }

                if (this.classificationEditorVisible && !this.classificationDraftDirty) {
                    this.loadClassificationDraft(this.activeClassificationIndex)
                }
            },
        },
    },
    async beforeMount() {
        const metaToken = document?.head?.querySelector?.('meta[name=\"csrf-token\"]')?.content
        this.csrfToken = String(metaToken || '').trim() || null

        try {
            const response = await axios.get('/api/admin/token')
            const token = String(response?.data || '').trim()
            if (token) {
                this.csrfToken = token
            }
        } catch {
            // Falls Token-Refresh fehlschlägt, wird der vorhandene Meta-Token verwendet.
        }
    },
    unmounted() {
        this.disarmClipboardPasteFallback()
    },
    computed: {
        maxUploadSizeBytes() {
            const value = Number(this.maxUploadSizeKb)
            if (!Number.isFinite(value) || value <= 0) return 20480 * 1024
            return Math.max(1, Math.round(value)) * 1024
        },
        maxUploadSizeLabel() {
            const mb = this.maxUploadSizeBytes / (1024 * 1024)
            const rounded = Math.round(mb * 100) / 100
            return `${rounded} MB`
        },
        pondServerConfig() {
            return {
                process: {
                    url: '/api/admin/materials/uploads/chunk',
                    method: 'POST',
                    timeout: 120000,
                    withCredentials: true,
                    headers: this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {},
                },
                patch: {
                    url: '/api/admin/materials/uploads/chunk?patch=',
                    method: 'PATCH',
                    timeout: 120000,
                    withCredentials: true,
                    headers: this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {},
                },
                revert: null,
                restore: null,
                load: null,
                fetch: null,
            }
        },
        hasClassification() {
            return this.assignedClassificationItems.length > 0
        },
        classificationSectionLabel() {
            return this.requireUnitClassification ? 'Fach / Thema / Bereich' : 'Fach / Thema / Bereich (optional)'
        },
        hasUnitLevelClassification() {
            const hasCompleteUnitRow = (row) => {
                return this.normalizeText(row?.subject) !== ''
                    && this.normalizeText(row?.topic) !== ''
                    && this.normalizeText(row?.unit) !== ''
            }
            const rows = this.toClassificationRows(this.classifications)

            return rows.some((row) => hasCompleteUnitRow(row))
                || (this.classificationEditorVisible && hasCompleteUnitRow(this.normalizedClassificationDraft))
        },
        canSave() {
            if (this.isReadOnly) return true
            const hasTitle = String(this.title || '').trim().length > 0
            if (this.requireUnitClassification) return hasTitle && this.hasUnitLevelClassification

            const hasClassification = this.hasClassification || !!this.normalizedClassificationDraft.subject
            return hasTitle && hasClassification
        },
        isClassificationReadOnly() {
            return this.isReadOnly || this.classificationReadOnly
        },
        canAddTextAttachment() {
            const title = this.normalizeText(this.textAttachmentDraftTitle)
            if (!title) return false
            return this.extractPlainTextFromHtml(this.textAttachmentDraftContent) !== ''
        },
        normalizedStatusOptions() {
            const fallback = [
                { value: 'inbox', label: 'Neu/Idee', color: '#607d8b' },
                { value: 'in_progress', label: 'In Arbeit', color: '#f9a825' },
                { value: 'done', label: 'ok', color: '#2e7d32' },
                { value: 'update_needed', label: 'Änderung nötig', color: '#c62828' },
            ]

            const input = Array.isArray(this.statusOptions) ? this.statusOptions : []
            const result = input
                .map((option) => {
                    if (typeof option === 'string') {
                        const value = this.normalizeText(option)
                        return value ? { value, label: value, color: '' } : null
                    }

                    if (!option || typeof option !== 'object') return null

                    const value = this.normalizeText(option.value)
                    const label = this.normalizeText(option.label) || value
                    const color = this.normalizeColor(option.color)
                    if (!value) return null

                    return { value, label, color }
                })
                .filter(Boolean)

            return result.length ? result : fallback
        },
        normalizedStatusValue() {
            const value = this.normalizeText(this.status)
            if (value) {
                const matchingOption = this.normalizedStatusOptions.find(
                    (option) => option.value.toLocaleLowerCase() === value.toLocaleLowerCase()
                )
                if (matchingOption) {
                    return matchingOption.value
                }
            }
            return this.normalizedStatusOptions[0]?.value || 'inbox'
        },
        currentStatusOption() {
            return this.normalizedStatusOptions.find((option) => option.value === this.normalizedStatusValue) || null
        },
        currentStatusLabel() {
            return this.currentStatusOption?.label || 'Status'
        },
        currentStatusColor() {
            const configuredColor = this.normalizeColor(this.currentStatusOption?.color)
            if (configuredColor) {
                return configuredColor
            }

            const map = {
                inbox: 'secondary',
                in_progress: 'warning',
                done: 'success',
                update_needed: 'error',
            }
            const normalizedStatus = this.normalizeText(this.normalizedStatusValue).toLocaleLowerCase()
            return map[normalizedStatus] || 'primary'
        },
        normalizedTypeOptions() {
            const input = Array.isArray(this.typeOptions) ? this.typeOptions : []
            return input
                .map((option) => {
                    if (!option || typeof option !== 'object') return null
                    const id = Number(option.id)
                    const value = this.normalizeText(option.value)
                    const label = this.normalizeText(option.label) || value
                    if (!value) return null

                    return {
                        id: Number.isFinite(id) && id > 0 ? id : null,
                        value,
                        label,
                    }
                })
                .filter(Boolean)
        },
        normalizedMaterialTypeValue() {
            const value = this.normalizeText(this.materialType)
            if (!value) return ''

            const matchingOption = this.normalizedTypeOptions.find(
                (option) => option.value.toLocaleLowerCase() === value.toLocaleLowerCase()
            )

            return matchingOption?.value || value
        },
        currentMaterialTypeLabel() {
            if (!this.normalizedMaterialTypeValue) {
                return 'Typ'
            }

            const option = this.normalizedTypeOptions.find(
                (entry) => entry.value === this.normalizedMaterialTypeValue
            )

            return option?.label || this.normalizedMaterialTypeValue
        },
        currentMaterialTypeColor() {
            return this.normalizedMaterialTypeValue ? 'primary' : 'grey'
        },
        normalizedPendingAttachments() {
            return this.toPendingAttachments(this.pendingAttachments)
        },
        visibleClassificationRows() {
            const rows = this.toClassificationRows(this.classifications)
            return rows.length > 0 ? rows : [this.emptyClassificationRow()]
        },
        editableClassificationRows() {
            const rows = this.visibleClassificationRows
            let index = Number.isInteger(this.activeClassificationIndex) ? this.activeClassificationIndex : 0

            if (index < 0) {
                index = 0
            }
            if (index >= rows.length) {
                index = rows.length - 1
            }

            return [{
                index,
                row: rows[index],
            }]
        },
        subjectOptions() {
            return this.classificationTree
                .map((subject) => this.normalizeText(subject?.name))
                .filter((subject) => subject !== '')
        },
        assignedClassificationItems() {
            const rows = this.toClassificationRows(this.classifications)
            const result = []
            const seen = new Set()

            for (let index = 0; index < rows.length; index += 1) {
                const row = rows[index]
                const subject = this.normalizeText(row.subject)
                const topic = this.normalizeText(row.topic)
                const unit = this.normalizeText(row.unit)
                if (!subject) continue

                const normalized = {
                    subject,
                    topic,
                    unit: topic ? unit : '',
                }
                const dedupeKey = `${normalized.subject.toLocaleLowerCase()}|${normalized.topic.toLocaleLowerCase()}|${normalized.unit.toLocaleLowerCase()}`
                if (seen.has(dedupeKey)) continue
                seen.add(dedupeKey)

                let label = subject
                if (topic) label += ` / ${topic}`
                if (unit) label += ` / ${unit}`

                result.push({
                    index,
                    label,
                    key: `${dedupeKey}|${index}`,
                })
            }

            return result
        },
        normalizedClassificationDraft() {
            const subject = this.normalizeText(this.classificationDraft?.subject)
            const topic = this.normalizeText(this.classificationDraft?.topic)
            let unit = this.normalizeText(this.classificationDraft?.unit)
            if (!subject) {
                return {
                    subject: '',
                    topic: '',
                    unit: '',
                }
            }
            if (!topic) {
                unit = ''
            }
            return {
                subject,
                topic,
                unit,
            }
        },
        hasClassificationDraftChanges() {
            const index = Number(this.activeClassificationIndex)
            if (!Number.isInteger(index) || index < 0) return false

            const rows = this.visibleClassificationRows
            if (index >= rows.length) return false
            const row = this.toClassificationRows([rows[index]])[0] || this.emptyClassificationRow()
            const current = {
                subject: this.normalizeText(row.subject),
                topic: this.normalizeText(row.topic),
                unit: this.normalizeText(row.topic) ? this.normalizeText(row.unit) : '',
            }
            const draft = this.normalizedClassificationDraft
            return current.subject !== draft.subject
                || current.topic !== draft.topic
                || current.unit !== draft.unit
        },
        canApplyClassificationDraft() {
            if (this.isClassificationReadOnly) return false
            const index = Number(this.activeClassificationIndex)
            return Number.isInteger(index) && index >= 0 && this.hasClassificationDraftChanges
        },
        canOpenTopicCreate() {
            if (this.isClassificationReadOnly) return false
            return this.normalizeText(this.normalizedClassificationDraft.subject) !== ''
        },
        canOpenUnitCreate() {
            if (this.isClassificationReadOnly) return false
            return this.normalizeText(this.normalizedClassificationDraft.subject) !== ''
                && this.normalizeText(this.normalizedClassificationDraft.topic) !== ''
        },
        canSubmitClassificationCreate() {
            if (this.isClassificationReadOnly) return false
            if (this.isSaving || this.classificationCreateSaving) return false
            const field = this.normalizeText(this.classificationCreateField).toLocaleLowerCase()
            const name = this.normalizeText(this.classificationCreateValue)
            if (!field || !name) return false
            if (field === 'subject') return true
            if (field === 'topic') return this.canOpenTopicCreate
            if (field === 'unit') return this.canOpenUnitCreate
            return false
        },
    },
    methods: {
        emptyClassificationRow() {
            return {
                subject: '',
                topic: '',
                unit: '',
            }
        },
        normalizeText(value) {
            return String(value ?? '').trim().slice(0, 255)
        },
        resetClassificationCreateState() {
            this.classificationCreateField = ''
            this.classificationCreateValue = ''
            this.classificationCreateSaving = false
        },
        openClassificationCreateField(field) {
            const key = this.normalizeText(field).toLocaleLowerCase()
            if (!['subject', 'topic', 'unit'].includes(key)) return
            if (this.isClassificationReadOnly) return
            if (this.isSaving || this.classificationCreateSaving) return
            if (key === 'topic' && !this.canOpenTopicCreate) return
            if (key === 'unit' && !this.canOpenUnitCreate) return

            if (this.classificationCreateField === key) {
                this.cancelClassificationCreateField()
                return
            }

            this.classificationCreateField = key
            this.classificationCreateValue = ''
        },
        cancelClassificationCreateField() {
            if (this.classificationCreateSaving) return
            this.classificationCreateField = ''
            this.classificationCreateValue = ''
        },
        async submitClassificationCreateField() {
            const field = this.normalizeText(this.classificationCreateField).toLocaleLowerCase()
            const name = this.normalizeText(this.classificationCreateValue)
            if (this.isClassificationReadOnly) return
            if (!['subject', 'topic', 'unit'].includes(field)) return
            if (!name || !this.canSubmitClassificationCreate) return

            const materialCardStore = useMaterialCardStore()
            this.classificationCreateSaving = true

            try {
                let created = null

                if (field === 'subject') {
                    created = await materialCardStore.createSubject(name)
                } else if (field === 'topic') {
                    const subjectNode = this.subjectNodeByName(this.normalizedClassificationDraft.subject)
                    const subjectId = Number(subjectNode?.id)
                    if (!Number.isFinite(subjectId) || subjectId <= 0) return
                    created = await materialCardStore.createTopic(subjectId, name)
                } else {
                    const subjectNode = this.subjectNodeByName(this.normalizedClassificationDraft.subject)
                    const topicNode = this.topicNodeByName(subjectNode, this.normalizedClassificationDraft.topic)
                    const topicId = Number(topicNode?.id)
                    if (!Number.isFinite(topicId) || topicId <= 0) return
                    created = await materialCardStore.createUnit(topicId, name)
                }

                if (!created) return

                const nextName = this.normalizeText(created?.name) || name
                this.updateClassificationDraftField(field, nextName)
                this.cancelClassificationCreateField()
                this.$emit('update:classificationEditorVisible', false)
            } finally {
                this.classificationCreateSaving = false
            }
        },
        normalizeUrl(value) {
            const raw = String(value ?? '').trim()
            if (!raw) return ''

            try {
                const parsed = new URL(raw)
                const protocol = String(parsed.protocol || '').toLowerCase()
                if (protocol !== 'http:' && protocol !== 'https:') {
                    return ''
                }

                return String(parsed.href || '').trim().slice(0, 2048)
            } catch {
                return ''
            }
        },
        defaultLinkTitle(url) {
            const normalizedUrl = this.normalizeUrl(url)
            if (!normalizedUrl) return 'Link'

            try {
                const parsed = new URL(normalizedUrl)
                const lastPath = decodeURIComponent(
                    parsed.pathname
                        .split('/')
                        .filter((segment) => segment !== '')
                        .pop() || ''
                )
                if (lastPath) {
                    return this.defaultAttachmentTitle(lastPath)
                }

                const host = String(parsed.hostname || '').replace(/^www\./i, '')
                return this.normalizeText(host) || 'Link'
            } catch {
                return 'Link'
            }
        },
        defaultTextAttachmentTitle() {
            const now = new Date()
            const stamp = new Intl.DateTimeFormat('de-AT', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            }).format(now)
            return `Text ${stamp}`
        },
        openTextAttachmentDialog() {
            if (this.isSaving || !this.showContentTools) return
            if (!this.normalizeText(this.textAttachmentDraftTitle)) {
                this.textAttachmentDraftTitle = this.ensureHtmlAttachmentName(this.defaultTextAttachmentTitle())
            }
            this.textAttachmentDialogError = ''
            this.textAttachmentDialogOpen = true
        },
        closeTextAttachmentDialog() {
            this.textAttachmentDialogOpen = false
            this.textAttachmentDialogError = ''
            this.textAttachmentDraftTitle = ''
            this.textAttachmentDraftContent = ''
        },
        escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;')
        },
        extractPlainTextFromHtml(value) {
            const html = String(value || '').trim()
            if (!html) return ''

            if (typeof DOMParser !== 'undefined') {
                try {
                    const doc = new DOMParser().parseFromString(html, 'text/html')
                    const text = String(doc?.body?.textContent || '').replace(/\s+/g, ' ').trim()
                    if (text) return text
                } catch {
                    // Fallback below.
                }
            }

            return html
                .replace(/<[^>]*>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
        },
        sanitizeFileNameSegment(value) {
            return String(value || '')
                .trim()
                .replace(/[<>:"/\\|?*\u0000-\u001F]/g, ' ')
                .replace(/\s+/g, ' ')
                .replace(/[. ]+$/g, '')
                .slice(0, 120)
        },
        ensureHtmlAttachmentName(value) {
            const normalized = this.normalizeText(value) || 'Text'
            return /\.(html?|HTML?)$/.test(normalized)
                ? normalized
                : `${normalized}.html`
        },
        buildTextAttachmentDocumentHtml(title, editorHtml) {
            const safeTitle = this.escapeHtml(title)
            const bodyHtml = String(editorHtml || '').trim()
            return `<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${safeTitle}</title>
<style>
body { font-family: Arial, sans-serif; line-height: 1.55; color: #1a2b3b; margin: 14px; }
p { margin: 0 0 0.65rem 0; }
ul, ol { margin: 0.45rem 0 0.75rem 0; padding-inline-start: 1.4rem; }
li { margin: 0.2rem 0; }
blockquote {
  margin: 0.75rem 0;
  padding: 0.5rem 0.75rem;
  border-left: 3px solid #fd802e;
  background: rgba(253, 128, 46, 0.10);
  border-radius: 0 6px 6px 0;
}
pre {
  background: #f5f7fb;
  border: 1px solid #d9e1f3;
  border-radius: 8px;
  padding: 10px 12px;
  overflow: auto;
}
code {
  background: #f5f7fb;
  border: 1px solid #d9e1f3;
  border-radius: 4px;
  padding: 1px 4px;
}
</style>
</head>
<body>
${bodyHtml}
</body>
</html>`
        },
        appendTextAttachment() {
            if (!this.showContentTools) return
            const rawTitle = this.normalizeText(this.textAttachmentDraftTitle) || this.defaultTextAttachmentTitle()
            const title = this.ensureHtmlAttachmentName(rawTitle)
            const content = String(this.textAttachmentDraftContent || '').trim()
            const plainText = this.extractPlainTextFromHtml(content)

            if (!plainText) {
                this.textAttachmentDialogError = 'Bitte zuerst Text eingeben.'
                return
            }

            const baseName = this.sanitizeFileNameSegment(title) || `text-${Date.now()}.html`
            const fileName = /\.(html?|HTML?)$/.test(baseName) ? baseName : `${baseName}.html`
            const documentHtml = this.buildTextAttachmentDocumentHtml(title, content)
            const file = new File([documentHtml], fileName, { type: 'text/html' })

            const rows = this.toPendingAttachments(this.pendingAttachments)
            rows.push({
                attachmentType: 'file',
                tempUpload: '',
                file,
                fileName,
                title,
                url: '',
                storeImageFile: false,
                source: 'text-editor',
                key: `text|${Date.now()}|${rows.length}`,
            })
            this.emitPendingAttachments(rows)
            this.setClipboardImportStatus('success', 'Text wurde als Anhang hinzugefügt.')
            this.closeTextAttachmentDialog()
        },
        extractUrlsFromText(text) {
            const value = String(text || '')
            if (!value) return []

            const matches = []
            const httpMatches = value.match(/https?:\/\/[^\s<>"')\]]+/gi) || []
            matches.push(...httpMatches)

            const bareMatches = value.match(/\b(?:www\.)?[a-z0-9-]+(?:\.[a-z0-9-]+)+(?:\/[^\s<>"')\]]*)?/gi) || []
            matches.push(...bareMatches)

            return matches.map((entry) => String(entry || '').trim()).filter((entry) => entry !== '')
        },
        extractUrlsFromHtml(html) {
            const value = String(html || '').trim()
            if (!value || typeof DOMParser === 'undefined') return []

            try {
                const doc = new DOMParser().parseFromString(value, 'text/html')
                const urls = []

                doc.querySelectorAll('a[href], img[src]').forEach((node) => {
                    if (node instanceof HTMLAnchorElement) {
                        urls.push(node.getAttribute('href') || '')
                        return
                    }
                    if (node instanceof HTMLImageElement) {
                        urls.push(node.getAttribute('src') || '')
                    }
                })

                return urls.map((entry) => String(entry || '').trim()).filter((entry) => entry !== '')
            } catch {
                return []
            }
        },
        extractDropUrls(dataTransfer) {
            const dt = dataTransfer
            if (!dt) return []

            const candidates = []
            const uriList = String(dt.getData?.('text/uri-list') || '').trim()
            if (uriList) {
                uriList
                    .split('\n')
                    .map((line) => line.trim())
                    .filter((line) => line !== '' && !line.startsWith('#'))
                    .forEach((line) => candidates.push(line))
            }

            const plain = String(dt.getData?.('text/plain') || '').trim()
            this.extractUrlsFromText(plain).forEach((url) => candidates.push(url))

            const html = String(dt.getData?.('text/html') || '').trim()
            this.extractUrlsFromHtml(html).forEach((url) => candidates.push(url))

            const result = []
            const seen = new Set()

            for (const candidate of candidates) {
                const normalized = this.normalizeUrl(candidate)
                if (!normalized) continue
                const key = normalized.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                result.push(normalized)
            }

            const imageUrls = result.filter((url) => this.isLikelyImageUrl(url))
            if (imageUrls.length > 0) {
                return imageUrls
            }

            return result
        },
        isLikelyImageUrl(url) {
            const normalized = this.normalizeUrl(url)
            if (!normalized) return false

            try {
                const parsed = new URL(normalized)
                const path = String(parsed.pathname || '').toLocaleLowerCase()
                return /\.(png|jpe?g|gif|webp|svg|bmp|tiff?|avif|heic)$/i.test(path)
            } catch {
                return false
            }
        },
        onAttachmentDragOver(event) {
            if (!this.showContentTools) return
            const dt = event?.dataTransfer
            if (!dt) return

            const hasFiles = (dt.files && dt.files.length > 0)
                || Array.from(dt.items || []).some((item) => item?.kind === 'file')
            if (hasFiles) return

            const urls = this.extractDropUrls(dt)
            if (urls.length > 0 && typeof event.preventDefault === 'function') {
                event.preventDefault()
            }
        },
        onAttachmentDrop(event) {
            if (!this.showContentTools) return
            const dt = event?.dataTransfer
            if (!dt) return

            const hasFiles = (dt.files && dt.files.length > 0)
                || Array.from(dt.items || []).some((item) => item?.kind === 'file')
            if (hasFiles) {
                return
            }

            if (typeof event.preventDefault === 'function') {
                event.preventDefault()
            }
            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation()
            }

            const urls = this.extractDropUrls(dt)
            if (!urls.length) return

            const rows = this.toPendingAttachments(this.pendingAttachments)
            const seen = new Set(
                rows
                    .filter((row) => row.attachmentType === 'link')
                    .map((row) => this.normalizeUrl(row.url).toLocaleLowerCase())
                    .filter((url) => url !== '')
            )

            let appended = 0
            for (const url of urls) {
                const normalized = this.normalizeUrl(url)
                if (!normalized) continue
                const key = normalized.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                appended += 1

                rows.push({
                    attachmentType: 'link',
                    tempUpload: '',
                    file: null,
                    fileName: '',
                    title: this.defaultLinkTitle(normalized),
                    url: normalized,
                    storeImageFile: this.isLikelyImageUrl(normalized),
                    source: 'drop-link',
                    key: `link|${normalized}|${rows.length}`,
                })
            }

            if (appended > 0) {
                this.emitPendingAttachments(rows)
            }
        },
        fileExtensionForMime(mimeType) {
            const normalized = this.normalizeText(mimeType).toLocaleLowerCase()
            if (!normalized) return ''

            const map = {
                'image/png': 'png',
                'image/jpeg': 'jpg',
                'image/jpg': 'jpg',
                'image/gif': 'gif',
                'image/webp': 'webp',
                'image/svg+xml': 'svg',
                'image/bmp': 'bmp',
                'image/tiff': 'tif',
                'image/avif': 'avif',
                'image/heic': 'heic',
                'application/pdf': 'pdf',
                'text/csv': 'csv',
                'application/zip': 'zip',
            }

            if (map[normalized]) {
                return map[normalized]
            }

            const suffix = normalized.split('/').pop() || ''
            const cleaned = suffix.split('+')[0].replace(/[^a-z0-9]/gi, '').toLowerCase()
            return cleaned || ''
        },
        parseClipboardUrls(textValue, htmlValue, uriListValue = '') {
            const candidates = []
            this.extractUrlsFromText(textValue).forEach((url) => candidates.push(url))
            this.extractUrlsFromHtml(htmlValue).forEach((url) => candidates.push(url))
            const uriList = String(uriListValue || '').trim()
            if (uriList) {
                uriList
                    .split('\n')
                    .map((line) => line.trim())
                    .filter((line) => line !== '' && !line.startsWith('#'))
                    .forEach((line) => candidates.push(line))
            }

            const result = []
            const seen = new Set()

            for (const candidate of candidates) {
                const normalized = this.normalizeClipboardCandidateUrl(candidate)
                if (!normalized) continue
                const key = normalized.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                result.push(normalized)
            }

            return result
        },
        normalizeClipboardCandidateUrl(value) {
            const raw = this.normalizeText(value)
            if (!raw) return ''

            const direct = this.normalizeUrl(raw)
            if (direct) return direct

            if (/^www\./i.test(raw)) {
                return this.normalizeUrl(`https://${raw}`)
            }

            if (/^(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[^\s]*)?$/i.test(raw)) {
                return this.normalizeUrl(`https://${raw}`)
            }

            return ''
        },
        clipboardPayloadFromData({ text = '', html = '', uriList = '', files = [] } = {}) {
            return {
                files: Array.isArray(files) ? files.filter((file) => file instanceof File) : [],
                urls: this.parseClipboardUrls(text, html, uriList),
            }
        },
        async readClipboardPayload() {
            let text = ''
            let html = ''
            const files = []
            let readTextFailed = false

            if (navigator?.clipboard?.readText) {
                try {
                    text = await navigator.clipboard.readText()
                } catch {
                    readTextFailed = true
                }
            }

            if (navigator?.clipboard?.read) {
                try {
                    const items = await navigator.clipboard.read()
                    const now = Date.now()

                    for (const item of items) {
                        const types = Array.isArray(item?.types) ? item.types : []
                        if (!text && types.includes('text/plain')) {
                            const plainBlob = await item.getType('text/plain')
                            text = await plainBlob.text()
                        }
                        if (!html && types.includes('text/html')) {
                            const htmlBlob = await item.getType('text/html')
                            html = await htmlBlob.text()
                        }

                        for (const type of types) {
                            const normalizedType = this.normalizeText(type).toLocaleLowerCase()
                            if (!normalizedType) continue
                            if (normalizedType === 'text/plain' || normalizedType === 'text/html' || normalizedType === 'text/rtf') continue

                            let blob = null
                            try {
                                blob = await item.getType(type)
                            } catch {
                                blob = null
                            }
                            if (!(blob instanceof Blob) || Number(blob.size) <= 0) continue

                            const mime = this.normalizeText(blob.type || type).toLocaleLowerCase() || 'application/octet-stream'
                            if (mime.startsWith('text/')) continue

                            const ext = this.fileExtensionForMime(mime)
                            const prefix = mime.startsWith('image/') ? 'zwischenablage-bild' : 'zwischenablage-datei'
                            const fileName = `${prefix}-${now}-${files.length + 1}${ext ? `.${ext}` : ''}`
                            files.push(new File([blob], fileName, { type: mime }))
                        }
                    }
                } catch {
                    // Fallback for browsers/contexts where ClipboardItem read is blocked.
                    if (!text && navigator?.clipboard?.readText) {
                        text = await navigator.clipboard.readText()
                    }
                }
            } else if (!navigator?.clipboard?.readText) {
                throw new Error('clipboard_api_not_supported')
            }

            if (!text && !html && files.length === 0 && readTextFailed) {
                throw new Error('clipboard_read_failed')
            }

            return this.clipboardPayloadFromData({ text, html, files })
        },
        appendClipboardPayload(payload, source = 'clipboard') {
            const normalizedPayload = payload && typeof payload === 'object' ? payload : {}
            let rows = this.toPendingAttachments(this.pendingAttachments)

            const files = Array.isArray(normalizedPayload.files) ? normalizedPayload.files : []
            const hasPastedFiles = files.length > 0
            if (files.length > 0) {
                rows = this.mergeUniqueFileAttachments(rows, files, source)
            }

            const urls = Array.isArray(normalizedPayload.urls) ? normalizedPayload.urls : []
            if (urls.length > 0) {
                const seenUrls = new Set(
                    rows
                        .filter((row) => row.attachmentType === 'link')
                        .map((row) => this.normalizeUrl(row.url).toLocaleLowerCase())
                        .filter((entry) => entry !== '')
                )

                for (const url of urls) {
                    const normalizedUrl = this.normalizeUrl(url)
                    if (!normalizedUrl) continue
                    const isImageUrl = this.isLikelyImageUrl(normalizedUrl)
                    const shouldStoreImageFromLink = isImageUrl && !hasPastedFiles

                    const key = normalizedUrl.toLocaleLowerCase()
                    if (seenUrls.has(key)) continue
                    seenUrls.add(key)
                    rows.push({
                        attachmentType: 'link',
                        tempUpload: '',
                        file: null,
                        fileName: '',
                        title: this.defaultLinkTitle(normalizedUrl),
                        url: normalizedUrl,
                        storeImageFile: shouldStoreImageFromLink,
                        source,
                        key: `link|${normalizedUrl}|${rows.length}`,
                    })
                }
            }

            this.emitPendingAttachments(rows)
        },
        mergeUniqueFileAttachments(existingAttachments, newFiles, source = 'manual') {
            const list = this.toPendingAttachments(existingAttachments)
            const getKey = (file) => `${file?.name || ''}|${file?.size || 0}|${file?.type || ''}|${file?.lastModified || 0}`
            const seen = new Set(
                list
                    .filter((item) => item.file instanceof File)
                    .map((item) => getKey(item.file))
            )

            for (const file of newFiles || []) {
                if (!(file instanceof File)) continue
                const key = getKey(file)
                if (seen.has(key)) continue
                seen.add(key)
                list.push({
                    attachmentType: 'file',
                    tempUpload: '',
                    file,
                    fileName: this.normalizeText(file.name) || 'Datei',
                    title: this.defaultAttachmentTitle(file.name),
                    url: '',
                    storeImageFile: false,
                    source: this.normalizeText(source) || 'manual',
                    key: `${key}|${seen.size}`,
                })
            }

            return list
        },
        setClipboardImportStatus(type, message) {
            this.clipboardImportStatus = {
                type: ['success', 'info', 'warning', 'error'].includes(String(type)) ? String(type) : 'info',
                message: this.normalizeText(message),
            }
        },
        armClipboardPasteFallback(message = '') {
            this.clipboardPasteArmed = true

            if (typeof window !== 'undefined') {
                window.addEventListener('paste', this.onGlobalClipboardPaste, true)
            }

            if (this.clipboardPasteTimeoutId) {
                clearTimeout(this.clipboardPasteTimeoutId)
                this.clipboardPasteTimeoutId = null
            }

            this.clipboardPasteTimeoutId = setTimeout(() => {
                if (!this.clipboardPasteArmed) return
                this.disarmClipboardPasteFallback()
                this.setClipboardImportStatus('info', 'Zwischenablage-Bereitschaft beendet. Bei Bedarf erneut klicken.')
            }, 15000)

            const fallbackMessage = this.normalizeText(message)
                || 'Direktes Lesen blockiert. Bitte jetzt Strg+V drücken.'
            this.setClipboardImportStatus('warning', fallbackMessage)
            this.focusClipboardPasteField()
        },
        disarmClipboardPasteFallback() {
            this.clipboardPasteArmed = false
            this.clipboardPasteBuffer = ''

            if (typeof window !== 'undefined') {
                window.removeEventListener('paste', this.onGlobalClipboardPaste, true)
            }

            if (this.clipboardPasteTimeoutId) {
                clearTimeout(this.clipboardPasteTimeoutId)
                this.clipboardPasteTimeoutId = null
            }
        },
        focusClipboardPasteField() {
            this.$nextTick(() => {
                const field = this.$refs.clipboardPasteField
                const textarea = field?.$el?.querySelector?.('textarea')
                if (field && typeof field.focus === 'function') {
                    field.focus()
                }
                if (textarea && typeof textarea.focus === 'function') {
                    textarea.focus()
                }
            })
        },
        onGlobalClipboardPaste(event) {
            if (!this.clipboardPasteArmed) return
            const handled = this.onAttachmentPaste(event)
            if (handled) {
                this.disarmClipboardPasteFallback()
            }
        },
        summarizeClipboardPayload(payload) {
            const filesCount = Array.isArray(payload?.files) ? payload.files.length : 0
            const linksCount = Array.isArray(payload?.urls) ? payload.urls.length : 0
            const parts = []

            if (filesCount > 0) {
                parts.push(`${filesCount} Datei${filesCount === 1 ? '' : 'en/Bilder'}`)
            }
            if (linksCount > 0) {
                parts.push(`${linksCount} Link${linksCount === 1 ? '' : 's'}`)
            }
            return parts.join(', ')
        },
        clipboardPayloadCounts(payload) {
            return {
                filesCount: Array.isArray(payload?.files) ? payload.files.length : 0,
                linksCount: Array.isArray(payload?.urls) ? payload.urls.length : 0,
            }
        },
        clipboardPayloadHasContent(payload) {
            const { filesCount, linksCount } = this.clipboardPayloadCounts(payload)
            return filesCount > 0 || linksCount > 0
        },
        async quickRetryClipboardRead(delayMs = 120) {
            await new Promise((resolve) => {
                setTimeout(resolve, Math.max(0, Number(delayMs) || 0))
            })

            try {
                const payload = await this.readClipboardPayload()
                return this.clipboardPayloadHasContent(payload) ? payload : null
            } catch {
                return null
            }
        },
        async importAttachmentsFromClipboard() {
            if (this.isSaving || !this.showContentTools) return

            // Reliable mode: one click arms paste capture, then user presses STRG+V.
            if (!this.clipboardPasteArmed) {
                this.armClipboardPasteFallback('Jetzt bitte STRG+V drücken.')
                return
            }

            this.setClipboardImportStatus('warning', 'Jetzt bitte STRG+V drücken.')
            this.focusClipboardPasteField()
        },
        onAttachmentPaste(event) {
            if (!this.showContentTools) return false
            const data = event?.clipboardData
            if (!data) return false

            const html = String(data.getData?.('text/html') || '')
            const text = String(data.getData?.('text/plain') || '')
            const uriList = String(data.getData?.('text/uri-list') || '')
            const files = []

            const items = Array.from(data.items || [])
            for (const item of items) {
                if (item?.kind !== 'file') continue
                const file = item.getAsFile?.()
                if (file instanceof File) {
                    files.push(file)
                }
            }

            const payload = this.clipboardPayloadFromData({ text, html, uriList, files })
            const filesCount = Array.isArray(payload.files) ? payload.files.length : 0
            const linksCount = Array.isArray(payload.urls) ? payload.urls.length : 0
            if (filesCount === 0 && linksCount === 0) return false

            if (typeof event.preventDefault === 'function') {
                event.preventDefault()
            }
            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation()
            }

            this.appendClipboardPayload(payload, 'paste')
            this.disarmClipboardPasteFallback()
            this.setClipboardImportStatus('success', `Eingefügt: ${this.summarizeClipboardPayload(payload)}.`)
            return true
        },
        normalizeColor(value) {
            const text = String(value ?? '').trim()
            if (!text) return ''
            if (!/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(text)) return ''
            if (text.length === 4) {
                return `#${text[1]}${text[1]}${text[2]}${text[2]}${text[3]}${text[3]}`.toLowerCase()
            }
            return text.toLowerCase()
        },
        defaultAttachmentTitle(fileName) {
            const name = String(fileName || '').trim()
            if (!name) {
                return 'Datei'
            }

            const lastDot = name.lastIndexOf('.')
            const withoutExtension = lastDot > 0 ? name.slice(0, lastDot) : name
            return this.normalizeText(withoutExtension || name) || 'Datei'
        },
        resolveLinkStoreImageFile(flagValue, linkUrl) {
            if (typeof flagValue === 'boolean') {
                return flagValue
            }
            return this.isLikelyImageUrl(linkUrl)
        },
        toPendingAttachments(value) {
            const input = Array.isArray(value) ? value : []
            const result = []

            for (let index = 0; index < input.length; index += 1) {
                const item = input[index]
                const attachmentType = this.normalizeText(item?.attachmentType).toLocaleLowerCase()
                const linkUrl = this.normalizeUrl(item?.url)
                if ((attachmentType === 'link' || linkUrl) && linkUrl) {
                    const rawTitle = this.normalizeText(item?.title)
                    const source = this.normalizeText(item?.source)
                    const key = String(item?.key || '') || `link|${linkUrl}|${index}`

                    result.push({
                        attachmentType: 'link',
                        tempUpload: '',
                        file: null,
                        title: rawTitle || this.defaultLinkTitle(linkUrl),
                        fileName: '',
                        url: linkUrl,
                        storeImageFile: this.resolveLinkStoreImageFile(item?.storeImageFile, linkUrl),
                        source: source || 'link',
                        key,
                    })
                    continue
                }

                const tempUpload = this.normalizeText(item?.tempUpload)
                if (tempUpload) {
                    const fileName = this.normalizeText(item?.fileName) || `Datei ${index + 1}`
                    const rawTitle = item?.title
                    const title = this.normalizeText(rawTitle) || this.defaultAttachmentTitle(fileName)
                    const source = this.normalizeText(item?.source) || 'filepond'
                    const key = String(item?.key || `temp|${tempUpload}|${index}`)

                    result.push({
                        attachmentType: 'file',
                        tempUpload,
                        file: null,
                        title,
                        fileName,
                        url: '',
                        storeImageFile: false,
                        source,
                        key,
                    })
                    continue
                }

                const file = item instanceof File ? item : item?.file
                if (!(file instanceof File)) continue

                const fileName = this.normalizeText(file.name) || `Datei ${index + 1}`
                const rawTitle = item instanceof File ? '' : item?.title
                const title = this.normalizeText(rawTitle) || this.defaultAttachmentTitle(fileName)
                const source = this.normalizeText(item instanceof File ? '' : item?.source)
                const key = String(item instanceof File ? '' : item?.key) || `${fileName}|${file.size}|${file.lastModified}|${index}`

                result.push({
                    attachmentType: 'file',
                    tempUpload: '',
                    file,
                    title,
                    fileName,
                    url: '',
                    storeImageFile: false,
                    source,
                    key,
                })
            }

            return result
        },
        emitPendingAttachments(rows) {
            const nextRows = (Array.isArray(rows) ? rows : []).map((row, index) => ({
                attachmentType: this.normalizeText(row?.attachmentType).toLocaleLowerCase() === 'link' ? 'link' : 'file',
                tempUpload: this.normalizeText(row.tempUpload),
                file: row.file instanceof File ? row.file : null,
                fileName: this.normalizeText(row.fileName || row.file?.name),
                title: this.normalizeText(row.title)
                    || (
                        this.normalizeText(row?.attachmentType).toLocaleLowerCase() === 'link'
                            ? this.defaultLinkTitle(row.url)
                            : this.defaultAttachmentTitle(row.fileName || row.file?.name)
                    ),
                url: this.normalizeUrl(row.url),
                storeImageFile: this.normalizeText(row?.attachmentType).toLocaleLowerCase() === 'link'
                    ? this.resolveLinkStoreImageFile(row?.storeImageFile, row?.url)
                    : false,
                source: this.normalizeText(row.source),
                key: String(
                    row.key
                    || (
                        this.normalizeText(row?.attachmentType).toLocaleLowerCase() === 'link'
                            ? `link|${this.normalizeUrl(row.url) || 'link'}|${index}`
                            : row.tempUpload
                            ? `temp|${row.tempUpload}|${index}`
                            : `${row.file?.name || 'datei'}|${row.file?.size || 0}|${row.file?.lastModified || 0}|${index}`
                    )
                ),
            }))
            this.$emit('update:pendingAttachments', nextRows)
        },
        beforeAddFile(fileItem) {
            if (!this.showContentTools) return false
            const size = Number(fileItem?.file?.size || fileItem?.size || 0)
            if (!Number.isFinite(size) || size <= 0) return true

            if (size > this.maxUploadSizeBytes) {
                this.$emit('upload-error', `Datei ist zu groß. Maximal erlaubt: ${this.maxUploadSizeLabel}.`)
                return false
            }

            return true
        },
        onProcessFile(error, fileItem) {
            if (!this.showContentTools) return
            if (error) {
                this.onProcessFileError(error)
                return
            }

            const uploadId = this.normalizeText(fileItem?.serverId)
            if (!uploadId) {
                this.onProcessFileError()
                return
            }

            const fileName = this.normalizeText(fileItem?.filename || fileItem?.file?.name) || 'Datei'
            const rows = this.toPendingAttachments(this.pendingAttachments)
            const alreadyExists = rows.some((row) => row.tempUpload === uploadId)
            if (!alreadyExists) {
                rows.push({
                    attachmentType: 'file',
                    tempUpload: uploadId,
                    file: null,
                    title: this.defaultAttachmentTitle(fileName),
                    fileName,
                    url: '',
                    storeImageFile: false,
                    source: 'filepond',
                    key: `temp|${uploadId}|${rows.length}`,
                })
                this.emitPendingAttachments(rows)
            }

            const pond = this.$refs.pond
            if (pond && typeof pond.removeFile === 'function') {
                pond.removeFile(fileItem?.id)
            }
        },
        onProcessFileError(error) {
            const message = String(error?.main || error?.body || error?.message || '').trim()
            this.$emit('upload-error', message || 'Datei konnte nicht hochgeladen werden.')
        },
        async copyTextToClipboard(value) {
            const text = this.normalizeText(value)
            if (!text) return false

            if (navigator?.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(text)
                    return true
                } catch {
                    // Fallback below.
                }
            }

            try {
                const textarea = document.createElement('textarea')
                textarea.value = text
                textarea.setAttribute('readonly', '')
                textarea.style.position = 'fixed'
                textarea.style.left = '-9999px'
                document.body.appendChild(textarea)
                textarea.select()
                textarea.setSelectionRange(0, text.length)
                const copied = document.execCommand('copy')
                document.body.removeChild(textarea)
                return copied
            } catch {
                return false
            }
        },
        async copyPendingAttachmentChip(item) {
            const isLink = this.normalizeText(item?.attachmentType).toLocaleLowerCase() === 'link'
            const valueToCopy = isLink
                ? this.normalizeUrl(item?.url)
                : this.normalizeText(item?.fileName) || this.normalizeText(item?.title)
            if (!valueToCopy) return

            const copied = await this.copyTextToClipboard(valueToCopy)
            if (copied) {
                this.setClipboardImportStatus(
                    'success',
                    isLink
                        ? 'Link wurde in die Zwischenablage kopiert.'
                        : 'Dateiname wurde in die Zwischenablage kopiert.'
                )
                return
            }

            this.setClipboardImportStatus(
                'warning',
                isLink
                    ? 'Link konnte nicht kopiert werden.'
                    : 'Dateiname konnte nicht kopiert werden.'
            )
        },
        updatePendingAttachmentTitle(index, value) {
            if (!this.showContentTools) return
            const rows = this.toPendingAttachments(this.pendingAttachments)
            if (index < 0 || index >= rows.length) return

            rows[index] = {
                ...rows[index],
                title: this.normalizeText(value)
                    || (
                        rows[index].attachmentType === 'link'
                            ? this.defaultLinkTitle(rows[index].url)
                            : this.defaultAttachmentTitle(rows[index].fileName)
                    ),
            }

            this.emitPendingAttachments(rows)
        },
        isPendingAttachmentDeleteArmed(attachmentKey) {
            const key = this.normalizeText(attachmentKey)
            if (!key) return false
            return this.pendingAttachmentDeleteArmedKeys.includes(key)
        },
        markPendingAttachmentDeleteArmed(attachmentKey, isArmed) {
            const key = this.normalizeText(attachmentKey)
            if (!key) return

            if (isArmed) {
                if (!this.pendingAttachmentDeleteArmedKeys.includes(key)) {
                    this.pendingAttachmentDeleteArmedKeys = [...this.pendingAttachmentDeleteArmedKeys, key]
                }
                return
            }

            this.pendingAttachmentDeleteArmedKeys = this.pendingAttachmentDeleteArmedKeys.filter((item) => item !== key)
        },
        cancelPendingAttachmentDelete(attachmentKey) {
            this.markPendingAttachmentDeleteArmed(attachmentKey, false)
        },
        resetPendingAttachmentDeleteArmed(validKeys = []) {
            const normalizedKeys = Array.isArray(validKeys)
                ? validKeys.map((key) => this.normalizeText(key)).filter((key) => key !== '')
                : []

            if (normalizedKeys.length === 0) {
                if (this.pendingAttachmentDeleteArmedKeys.length) {
                    this.pendingAttachmentDeleteArmedKeys = []
                }
                return
            }

            const keySet = new Set(normalizedKeys)
            this.pendingAttachmentDeleteArmedKeys = this.pendingAttachmentDeleteArmedKeys.filter((key) => keySet.has(key))
        },
        removePendingAttachment(index) {
            if (!this.showContentTools) return
            const rows = this.toPendingAttachments(this.pendingAttachments)
            if (index < 0 || index >= rows.length) return

            const removed = rows[index]
            const key = this.normalizeText(removed?.key)
            if (!this.isPendingAttachmentDeleteArmed(key)) {
                this.markPendingAttachmentDeleteArmed(key, true)
                return
            }

            this.markPendingAttachmentDeleteArmed(key, false)
            const tempUpload = this.normalizeText(removed?.tempUpload)
            rows.splice(index, 1)
            this.emitPendingAttachments(rows)

            if (tempUpload) {
                this.$emit('remove-temp-upload', tempUpload)
            }
        },
        isClassificationRowEmpty(row) {
            const subject = this.normalizeText(row?.subject)
            const topic = this.normalizeText(row?.topic)
            const unit = this.normalizeText(row?.unit)
            return subject === '' && topic === '' && unit === ''
        },
        toClassificationRows(value) {
            const input = Array.isArray(value) ? value : []
            const result = []

            for (const row of input) {
                if (!row || typeof row !== 'object') continue
                result.push({
                    subject: this.normalizeText(row.subject),
                    topic: this.normalizeText(row.topic),
                    unit: this.normalizeText(row.unit),
                })
            }

            return result
        },
        normalizedClassificationRows(value) {
            const input = this.toClassificationRows(value)
            const seen = new Set()
            const result = []

            for (const row of input) {
                if (!row.subject) continue
                const normalized = {
                    subject: row.subject,
                    topic: row.topic,
                    unit: row.topic ? row.unit : '',
                }
                const key = `${normalized.subject.toLocaleLowerCase()}|${normalized.topic.toLocaleLowerCase()}|${normalized.unit.toLocaleLowerCase()}`
                if (seen.has(key)) continue
                seen.add(key)
                result.push(normalized)
            }

            return result
        },
        subjectNodeByName(name) {
            const subjectName = this.normalizeText(name)
            if (!subjectName) return null
            return this.classificationTree.find(
                (subject) => this.normalizeText(subject?.name).toLocaleLowerCase() === subjectName.toLocaleLowerCase()
            ) || null
        },
        topicNodeByName(subjectNode, topicName) {
            const normalizedTopic = this.normalizeText(topicName)
            if (!subjectNode || !normalizedTopic || !Array.isArray(subjectNode.topics)) return null
            return subjectNode.topics.find(
                (topic) => this.normalizeText(topic?.name).toLocaleLowerCase() === normalizedTopic.toLocaleLowerCase()
            ) || null
        },
        topicOptionsFor(row) {
            const subjectNode = this.subjectNodeByName(row?.subject)
            const topics = Array.isArray(subjectNode?.topics) ? subjectNode.topics : []
            return topics
                .map((topic) => this.normalizeText(topic?.name))
                .filter((topic) => topic !== '')
        },
        unitOptionsFor(row) {
            const subjectNode = this.subjectNodeByName(row?.subject)
            const topicNode = this.topicNodeByName(subjectNode, row?.topic)
            const units = Array.isArray(topicNode?.units) ? topicNode.units : []
            return units
                .map((unit) => this.normalizeText(unit?.name))
                .filter((unit) => unit !== '')
        },
        loadClassificationDraft(index) {
            const rows = this.visibleClassificationRows
            let safeIndex = Number(index)
            if (!Number.isInteger(safeIndex)) safeIndex = 0
            if (safeIndex < 0) safeIndex = 0
            if (safeIndex >= rows.length) safeIndex = rows.length - 1

            const row = this.toClassificationRows([rows[safeIndex]])[0] || this.emptyClassificationRow()
            const subject = this.normalizeText(row.subject)
            const topic = this.normalizeText(row.topic)
            const unit = topic ? this.normalizeText(row.unit) : ''

            this.classificationDraft = {
                subject,
                topic,
                unit,
            }
            this.classificationDraftDirty = false
            if (!this.classificationCreateSaving) {
                this.cancelClassificationCreateField()
            }
        },
        updateClassificationDraftField(field, value) {
            const key = String(field || '')
            if (!['subject', 'topic', 'unit'].includes(key)) return
            if (this.isClassificationReadOnly) return
            if (this.classificationCreateField && this.classificationCreateField !== key && !this.classificationCreateSaving) {
                this.cancelClassificationCreateField()
            }

            const nextValue = this.normalizeText(value)
            const nextDraft = {
                subject: this.normalizeText(this.classificationDraft.subject),
                topic: this.normalizeText(this.classificationDraft.topic),
                unit: this.normalizeText(this.classificationDraft.unit),
            }

            nextDraft[key] = nextValue

            if (key === 'subject') {
                if (!nextDraft.subject) {
                    nextDraft.topic = ''
                    nextDraft.unit = ''
                } else {
                    nextDraft.topic = ''
                    nextDraft.unit = ''
                }
            }

            if (key === 'topic') {
                if (!nextDraft.topic) {
                    nextDraft.unit = ''
                } else {
                    nextDraft.unit = ''
                }
            }

            this.classificationDraft = nextDraft
            this.classificationDraftDirty = true
        },
        handleSave() {
            if (this.canApplyClassificationDraft) {
                this.applyClassificationDraft()
            }
            this.$emit('save')
        },
        applyClassificationDraft() {
            if (this.isClassificationReadOnly) return
            const index = Number(this.activeClassificationIndex)
            if (!Number.isInteger(index) || index < 0) return

            const rows = this.toClassificationRows(this.classifications)
            while (rows.length <= index) {
                rows.push(this.emptyClassificationRow())
            }

            rows[index] = { ...this.normalizedClassificationDraft }

            this.activeClassificationIndex = index
            if (
                this.newlyAddedClassificationIndex === index &&
                !this.isClassificationRowEmpty(rows[index])
            ) {
                this.newlyAddedClassificationIndex = null
            }

            this.classificationDraftDirty = false
            this.$emit('update:classifications', rows)
        },
        updateClassificationField(index, field, value) {
            if (this.isClassificationReadOnly) return
            const rows = this.toClassificationRows(this.classifications)
            while (rows.length <= index) {
                rows.push(this.emptyClassificationRow())
            }

            rows[index] = {
                ...rows[index],
                [field]: this.normalizeText(value),
            }

            if (field === 'subject') {
                rows[index].topic = ''
                rows[index].unit = ''
            }
            if (field === 'topic') {
                rows[index].unit = ''
            }

            this.activeClassificationIndex = index
            if (
                this.newlyAddedClassificationIndex === index &&
                !this.isClassificationRowEmpty(rows[index])
            ) {
                this.newlyAddedClassificationIndex = null
            }
            this.$emit('update:classifications', rows)
        },
        addClassificationAndOpenEditor() {
            if (this.isClassificationReadOnly) return
            const rows = this.toClassificationRows(this.classifications)
            let targetIndex = rows.findIndex((row) => this.isClassificationRowEmpty(row))
            if (targetIndex < 0) {
                rows.push(this.emptyClassificationRow())
                targetIndex = rows.length - 1
                this.newlyAddedClassificationIndex = targetIndex
            } else {
                this.newlyAddedClassificationIndex = null
            }

            this.activeClassificationIndex = targetIndex
            this.$emit('update:classifications', rows)
            this.$emit('update:classificationEditorVisible', true)
        },
        closeClassificationEditor() {
            const rows = this.toClassificationRows(this.classifications)
            const index = Number.isInteger(this.newlyAddedClassificationIndex)
                ? this.newlyAddedClassificationIndex
                : null

            if (index !== null && index >= 0 && index < rows.length && this.isClassificationRowEmpty(rows[index])) {
                rows.splice(index, 1)
                this.$emit('update:classifications', this.normalizedClassificationRows(rows))
            }

            this.activeClassificationIndex = null
            this.newlyAddedClassificationIndex = null
            this.$emit('update:classificationEditorVisible', false)
        },
        openClassificationEditor(index) {
            if (this.isClassificationReadOnly) return
            const rows = this.visibleClassificationRows
            const parsedIndex = Number(index)
            let safeIndex = Number.isInteger(parsedIndex) ? parsedIndex : 0
            if (safeIndex < 0) safeIndex = 0
            if (safeIndex >= rows.length) safeIndex = rows.length - 1

            const sameTarget = this.classificationEditorVisible && this.activeClassificationIndex === safeIndex
            if (sameTarget) {
                this.closeClassificationEditor()
                return
            }

            this.activeClassificationIndex = safeIndex
            this.newlyAddedClassificationIndex = null
            this.$emit('update:classificationEditorVisible', true)
        },
        removeClassificationRow(index) {
            if (this.isClassificationReadOnly) return
            const rows = this.toClassificationRows(this.classifications)
            rows.splice(index, 1)
            const nextRows = rows.length ? rows : [this.emptyClassificationRow()]
            const normalizedRows = this.normalizedClassificationRows(nextRows)
            const nextVisibleCount = normalizedRows.length > 0 ? normalizedRows.length : 1

            this.activeClassificationIndex = Math.min(Math.max(index, 0), nextVisibleCount - 1)
            this.newlyAddedClassificationIndex = null
            this.$emit('update:classifications', normalizedRows)
        },
        selectStatus(value) {
            if (this.isReadOnly) return
            this.$emit('update:status', this.normalizeText(value))
        },
        selectMaterialType(value) {
            if (this.isReadOnly) return
            const normalized = this.normalizeText(value)
            this.$emit('update:materialType', normalized)
        },
        openTypeManager() {
            if (this.isReadOnly) return
            this.$emit('manage-types')
        },
        focusTitle() {
            const tryFocus = (attempt = 0) => {
                const field = this.$refs.titleField
                const input = field?.$el?.querySelector?.('input')

                if (field && typeof field.focus === 'function') {
                    field.focus()
                }
                if (input && typeof input.focus === 'function') {
                    input.focus()
                }

                const isFocused = document?.activeElement === input
                if (!isFocused && attempt < 6) {
                    setTimeout(() => tryFocus(attempt + 1), 80)
                }
            }

            this.$nextTick(() => {
                tryFocus(0)
            })
        },
    },
}
</script>

<style scoped>
.create-form-card {
    border: 1px solid rgba(253, 128, 46, 0.35);
    background: rgba(255, 255, 255, 0.96);
}

.form-subline {
    color: #314d5d;
}

.status-chip {
    cursor: pointer;
}

.type-chip {
    cursor: pointer;
}

.assigned-chip {
    cursor: pointer;
}

.pending-file-row:last-child {
    margin-bottom: 0 !important;
}

.pending-source-text {
    white-space: pre-wrap;
    word-break: break-word;
}

.link-copy-chip {
    cursor: pointer;
}

.classification-row {
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.6);
    padding: 8px 10px 0;
}

.classification-row-active {
    border: 1px solid rgba(253, 128, 46, 0.45);
}

.classification-chip-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #2f4a5d;
}

.classification-inline-create {
    width: 100%;
}

.classification-inline-create-field {
    min-width: 220px;
    flex: 1 1 220px;
}

.classification-option-chip--selected {
    background: rgba(253, 128, 46, 0.16);
    border-color: rgba(253, 128, 46, 0.85);
    color: #233d4c;
}

.classification-action-row {
    display: flex;
    width: 100%;
    justify-content: flex-end;
    gap: 8px;
}

.classification-action-btn {
    min-width: 34px;
    min-height: 34px;
}

.classification-fade-enter-active,
.classification-fade-leave-active {
    transition: opacity 0.2s ease;
}

.classification-fade-enter-from,
.classification-fade-leave-to {
    opacity: 0;
}
</style>
