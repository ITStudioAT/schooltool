<template>
    <v-card class="materials-shell pa-4 pa-md-8" rounded="xl" elevation="0">
        <div class="d-flex justify-space-between align-start flex-wrap ga-3 mb-4">
            <div>
                <div class="text-h4 font-weight-bold mb-2">Inbox</div>
                <div class="text-subtitle-1 subline">Benutzer, die etwas mit dir geteilt haben.</div>
            </div>
            <div class="d-flex align-center flex-wrap ga-2">
                <v-btn
                    flat
                    color="primary"
                    prepend-icon="mdi-refresh"
                    :loading="isLoading"
                    @click="loadInboxUsers">
                    Aktualisieren
                </v-btn>
            </div>
        </div>
        <div class="d-flex align-center flex-wrap ga-2 mb-4 inbox-filter-actions">
            <v-btn
                size="small"
                color="primary"
                :variant="inboxMaterialFilter === 'all' ? 'flat' : 'outlined'"
                @click="setInboxMaterialFilter('all')">
                Alle
            </v-btn>
            <v-btn
                size="small"
                color="primary"
                :variant="inboxMaterialFilter === 'new' ? 'flat' : 'outlined'"
                @click="setInboxMaterialFilter('new')">
                Neue Materialien
            </v-btn>
            <v-btn
                size="small"
                color="primary"
                :variant="inboxMaterialFilter === 'imported' ? 'flat' : 'outlined'"
                @click="setInboxMaterialFilter('imported')">
                Eingefächerte Materialien
            </v-btn>
        </div>

        <v-alert
            v-if="needsMigration"
            type="warning"
            variant="flat"
            class="mb-4">
            Freigaben-Tabellen sind noch nicht vorhanden. Bitte Migration ausführen (`php artisan migrate`).
        </v-alert>

        <v-alert
            v-else-if="errorMessage"
            type="error"
            variant="flat"
            class="mb-4">
            {{ errorMessage }}
        </v-alert>

        <div v-if="isLoading" class="text-body-2 text-medium-emphasis py-4">
            Lade Inbox ...
        </div>

        <v-card v-else-if="users.length === 0" variant="outlined" class="pa-4">
            <div class="text-body-2 text-medium-emphasis">
                Noch keine eingehenden Freigaben gefunden.
            </div>
        </v-card>

        <v-card v-else variant="outlined" class="pa-0">
            <v-list lines="two">
                <v-list-item
                    v-for="user in users"
                    :key="`inbox-user-${user.id}`">
                    <v-list-item-title class="font-weight-medium">
                        {{ user.label }}
                        <span v-if="user.school_label"> · {{ user.school_label }}</span>
                    </v-list-item-title>
                    <v-list-item-subtitle>
                        {{ user.email || 'ohne E-Mail' }}
                    </v-list-item-subtitle>
                    <v-expansion-panels v-if="user.shared_items.length > 0" class="inbox-shared-panels">
                        <v-expansion-panel>
                            <v-expansion-panel-title>
                                Anzeigen, was geteilt wurde ({{ filteredSharedItems(user).length }})
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <div class="inbox-shared-list">
                                    <div
                                        v-if="filteredSharedItems(user).length === 0"
                                        class="text-caption text-medium-emphasis">
                                        Keine Einträge für den gewählten Filter.
                                    </div>
                                    <div
                                        v-for="item in filteredSharedItems(user)"
                                        :key="`inbox-user-${user.id}-rule-${item.rule_id}`"
                                        class="inbox-shared-object-card">
                                        <div class="inbox-shared-object-head">
                                            <div class="inbox-shared-object-head-top">
                                                <div class="inbox-shared-object-scope">
                                                    {{ item.scope_label }}
                                                </div>
                                                <v-chip
                                                    size="x-small"
                                                    variant="flat"
                                                    :color="permissionChipColor(item.permission)">
                                                    {{ item.permission_label }}
                                                </v-chip>
                                            </div>
                                            <div
                                                v-if="item.scope_type !== 'material' || !item.materialPreview"
                                                class="inbox-shared-object-title">
                                                {{ item.scope_object_label }}
                                            </div>
                                            <div class="inbox-shared-object-path">
                                                {{ item.scope_path_label }}
                                            </div>
                                            <div
                                                v-if="item.scope_type === 'material' && item.materialPreview"
                                                class="inbox-material-overview-line">
                                                <v-icon
                                                    size="14"
                                                    :icon="item.materialPreview.icon || 'mdi-file-document-outline'"
                                                    :color="item.materialPreview.typeColor || undefined" />
                                                <span class="inbox-hierarchy-material-title">{{ item.materialPreview.title }}</span>
                                                <v-chip
                                                    v-if="item.materialPreview.typeLabel"
                                                    size="x-small"
                                                    variant="outlined"
                                                    :color="item.materialPreview.typeColor || 'primary'">
                                                    {{ item.materialPreview.typeLabel }}
                                                </v-chip>
                                                <span v-if="item.materialPreview.attachmentsCount > 0" class="inbox-hierarchy-material-count">
                                                    <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                                    {{ item.materialPreview.attachmentsCount }}
                                                </span>
                                                <v-chip
                                                    size="x-small"
                                                    variant="tonal"
                                                    :color="item.materialPreview.statusColor || materialStatusColor(item.materialPreview.status)">
                                                    {{ item.materialPreview.statusLabel || materialStatusLabel(item.materialPreview.status) }}
                                                </v-chip>
                                                <v-chip
                                                    v-if="item.is_imported"
                                                    size="x-small"
                                                    variant="flat"
                                                    color="success">
                                                    Eingefächert
                                                </v-chip>
                                            </div>
                                            <v-card
                                                v-if="item.scope_type !== 'material' && isHierarchyOpen(user.id, item.rule_id)"
                                                variant="outlined"
                                                class="inbox-hierarchy-card">
                                                <div class="inbox-shared-hierarchy">
                                                    <div v-if="item.hierarchy.length === 0" class="text-caption text-medium-emphasis">
                                                        Keine Inhalte gefunden.
                                                    </div>
                                                    <template v-if="item.scope_type === 'topic'">
                                                        <template v-for="subject in item.hierarchy" :key="`hier-topic-scope-subject-${item.rule_id}-${subject.id || subject.name}`">
                                                            <div
                                                                v-for="topic in subject.topics"
                                                                :key="`hier-topic-scope-topic-${item.rule_id}-${topic.id || topic.name}`"
                                                                class="inbox-hierarchy-topic inbox-hierarchy-topic--root">
                                                                <div class="inbox-hierarchy-topic-title">{{ topic.name }}</div>
                                                                <div
                                                                    v-for="unit in topic.units"
                                                                    :key="`hier-topic-scope-unit-${item.rule_id}-${unit.id || unit.name}`"
                                                                    class="inbox-hierarchy-unit">
                                                                    <template v-if="isTopicDirectUnit(unit)">
                                                                        <div class="inbox-hierarchy-material-lines">
                                                                            <div
                                                                                v-for="material in unit.materials"
                                                                                :key="`hier-topic-scope-material-${item.rule_id}-${material.id || material.title}`"
                                                                                class="inbox-hierarchy-material-line">
                                                                                <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                                                                <span class="inbox-hierarchy-material-title">{{ material.title }}</span>
                                                                                <v-chip
                                                                                    v-if="material.typeLabel"
                                                                                    size="x-small"
                                                                                    variant="outlined"
                                                                                    :color="material.typeColor || 'primary'">
                                                                                    {{ material.typeLabel }}
                                                                                </v-chip>
                                                                                <span v-if="material.attachmentsCount > 0" class="inbox-hierarchy-material-count">
                                                                                    <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                                                                    {{ material.attachmentsCount }}
                                                                                </span>
                                                                                <v-chip
                                                                                    size="x-small"
                                                                                    variant="tonal"
                                                                                    :color="material.statusColor || materialStatusColor(material.status)">
                                                                                    {{ material.statusLabel || materialStatusLabel(material.status) }}
                                                                                </v-chip>
                                                                            </div>
                                                                        </div>
                                                                    </template>
                                                                    <template v-else>
                                                                        <div class="inbox-hierarchy-unit-title">{{ unit.name }}</div>
                                                                        <div class="inbox-hierarchy-material-lines">
                                                                            <div
                                                                                v-for="material in unit.materials"
                                                                                :key="`hier-topic-scope-material-${item.rule_id}-${material.id || material.title}`"
                                                                                class="inbox-hierarchy-material-line">
                                                                                <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                                                                <span class="inbox-hierarchy-material-title">{{ material.title }}</span>
                                                                                <v-chip
                                                                                    v-if="material.typeLabel"
                                                                                    size="x-small"
                                                                                    variant="outlined"
                                                                                    :color="material.typeColor || 'primary'">
                                                                                    {{ material.typeLabel }}
                                                                                </v-chip>
                                                                                <span v-if="material.attachmentsCount > 0" class="inbox-hierarchy-material-count">
                                                                                    <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                                                                    {{ material.attachmentsCount }}
                                                                                </span>
                                                                                <v-chip
                                                                                    size="x-small"
                                                                                    variant="tonal"
                                                                                    :color="material.statusColor || materialStatusColor(material.status)">
                                                                                    {{ material.statusLabel || materialStatusLabel(material.status) }}
                                                                                </v-chip>
                                                                            </div>
                                                                        </div>
                                                                    </template>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </template>
                                                    <template v-else>
                                                        <div
                                                            v-for="subject in item.hierarchy"
                                                            :key="`hier-subject-${item.rule_id}-${subject.id || subject.name}`"
                                                            class="inbox-hierarchy-subject">
                                                            <div class="inbox-hierarchy-subject-title">{{ subject.name }}</div>
                                                            <div
                                                                v-for="topic in subject.topics"
                                                                :key="`hier-topic-${item.rule_id}-${topic.id || `${subject.name}-${topic.name}`}`"
                                                                class="inbox-hierarchy-topic">
                                                                <div class="inbox-hierarchy-topic-title">{{ topic.name }}</div>
                                                                <div
                                                                    v-for="unit in topic.units"
                                                                    :key="`hier-unit-${item.rule_id}-${unit.id || `${topic.name}-${unit.name}`}`"
                                                                    class="inbox-hierarchy-unit">
                                                                    <div class="inbox-hierarchy-unit-title">{{ unit.name }}</div>
                                                                    <div class="inbox-hierarchy-material-lines">
                                                                        <div
                                                                            v-for="material in unit.materials"
                                                                            :key="`hier-material-${item.rule_id}-${material.id || material.title}`"
                                                                            class="inbox-hierarchy-material-line">
                                                                            <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                                                            <span class="inbox-hierarchy-material-title">{{ material.title }}</span>
                                                                            <v-chip
                                                                                v-if="material.typeLabel"
                                                                                size="x-small"
                                                                                variant="outlined"
                                                                                :color="material.typeColor || 'primary'">
                                                                                {{ material.typeLabel }}
                                                                            </v-chip>
                                                                            <span v-if="material.attachmentsCount > 0" class="inbox-hierarchy-material-count">
                                                                                <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                                                                {{ material.attachmentsCount }}
                                                                            </span>
                                                                            <v-chip
                                                                                size="x-small"
                                                                                variant="tonal"
                                                                                :color="material.statusColor || materialStatusColor(material.status)">
                                                                                {{ material.statusLabel || materialStatusLabel(material.status) }}
                                                                            </v-chip>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </v-card>
                                            <div v-if="item.updated_at" class="text-caption text-medium-emphasis">
                                                Aktualisiert: {{ formatDateTime(item.updated_at) }}
                                            </div>
                                        </div>
                                        <div class="inbox-shared-object-actions">
                                            <v-btn
                                                v-if="item.scope_type === 'material'"
                                                size="small"
                                                variant="tonal"
                                                color="primary"
                                                @click.stop="openEinfachernDialog(item)">
                                                Einfächern
                                            </v-btn>
                                            <v-btn
                                                v-else
                                                size="small"
                                                variant="tonal"
                                                color="primary"
                                                @click.stop="toggleHierarchy(user.id, item.rule_id)">
                                                {{ isHierarchyOpen(user.id, item.rule_id) ? 'Schließen' : 'Anzeigen' }}
                                            </v-btn>
                                            <v-btn
                                                v-if="item.scope_type !== 'material'"
                                                size="small"
                                                variant="tonal"
                                                color="warning"
                                                @click.stop="onDummyObjectAction(item, 'bookmark')">
                                                Merken
                                            </v-btn>
                                            <v-btn
                                                v-if="item.scope_type !== 'material'"
                                                size="small"
                                                variant="tonal"
                                                color="secondary"
                                                @click.stop="onDummyObjectAction(item, 'more')">
                                                Mehr
                                            </v-btn>
                                        </div>
                                    </div>
                                </div>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                    <template #append>
                        <v-chip color="primary" variant="tonal" size="small">
                            {{ user.shared_rules_count }} Freigabe{{ user.shared_rules_count === 1 ? '' : 'n' }}
                        </v-chip>
                    </template>
                </v-list-item>
            </v-list>
        </v-card>

        <v-dialog
            v-model="einfachernDialog.open"
            persistent
            max-width="960">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center justify-space-between ga-2">
                    <span>Einfächern</span>
                    <v-chip size="small" variant="tonal" color="primary">
                        {{ einfachernDialog.materialTitle || 'Material' }}
                    </v-chip>
                </v-card-title>
                <v-card-text>
                    <v-alert
                        v-if="einfachernDialog.error"
                        type="error"
                        variant="tonal"
                        class="mb-3">
                        {{ einfachernDialog.error }}
                    </v-alert>

                    <div v-if="einfachernDialog.loading" class="text-body-2 text-medium-emphasis py-4">
                        Lade Fächer ...
                    </div>

                    <template v-else>
                        <div v-if="einfachernDialog.tree.length === 0" class="einfachern-empty">
                            <div class="text-body-2 text-medium-emphasis">
                                Es sind noch keine Fächer vorhanden.
                            </div>
                            <v-btn
                                size="small"
                                variant="tonal"
                                color="primary"
                                :loading="einfachernDialog.submitting"
                                @click="onOriginalEinfuegen">
                                Als Original einfügen
                            </v-btn>
                        </div>

                        <div v-else class="einfachern-columns">
                            <div class="einfachern-column">
                                <div class="einfachern-column-title">Fächer</div>
                                <div class="einfachern-list">
                                    <v-btn
                                        v-for="subject in einfachernDialog.tree"
                                        :key="`einfachern-subject-${subject.id || subject.name}`"
                                        size="small"
                                        block
                                        :variant="Number(einfachernDialog.subjectId) === Number(subject.id) ? 'flat' : 'tonal'"
                                        :color="Number(einfachernDialog.subjectId) === Number(subject.id) ? 'primary' : 'secondary'"
                                        class="justify-start"
                                        @click="selectEinfachernSubject(subject)">
                                        {{ subject.name }}
                                    </v-btn>
                                </div>
                            </div>
                            <div class="einfachern-column">
                                <div class="einfachern-column-title">Themen</div>
                                <div v-if="einfachernTopics.length === 0" class="text-caption text-medium-emphasis">
                                    Kein Thema ausgewählt.
                                </div>
                                <div v-else class="einfachern-list">
                                    <v-btn
                                        v-for="topic in einfachernTopics"
                                        :key="`einfachern-topic-${topic.id || topic.name}`"
                                        size="small"
                                        block
                                        :variant="Number(einfachernDialog.topicId) === Number(topic.id) ? 'flat' : 'tonal'"
                                        :color="Number(einfachernDialog.topicId) === Number(topic.id) ? 'primary' : 'secondary'"
                                        class="justify-start"
                                        @click="selectEinfachernTopic(topic)">
                                        {{ topic.name }}
                                    </v-btn>
                                </div>
                            </div>
                            <div class="einfachern-column">
                                <div class="einfachern-column-title">Einheiten</div>
                                <div v-if="einfachernUnits.length === 0" class="text-caption text-medium-emphasis">
                                    Keine Einheit ausgewählt.
                                </div>
                                <div v-else class="einfachern-list">
                                    <v-btn
                                        v-for="unit in einfachernUnits"
                                        :key="`einfachern-unit-${unit.id || unit.name}`"
                                        size="small"
                                        block
                                        :variant="Number(einfachernDialog.unitId) === Number(unit.id) ? 'flat' : 'tonal'"
                                        :color="Number(einfachernDialog.unitId) === Number(unit.id) ? 'primary' : 'secondary'"
                                        class="justify-start"
                                        @click="selectEinfachernUnit(unit)">
                                        {{ unit.name }}
                                    </v-btn>
                                </div>
                            </div>
                        </div>
                    </template>
                </v-card-text>
                <v-card-actions class="d-flex justify-space-between">
                    <div class="text-caption text-medium-emphasis">
                        {{ einfachernSelectionLabel }}
                    </div>
                    <div class="d-flex ga-2">
                        <v-btn
                            v-if="einfachernSelectedTarget"
                            size="small"
                            variant="tonal"
                            color="primary"
                            @click="onDummyHierEinfachern">
                            Hier einfächern
                        </v-btn>
                        <v-btn size="small" variant="text" @click="closeEinfachernDialog">
                            Schließen
                        </v-btn>
                    </div>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>

<script>
import axios from 'axios'

export default {
    name: 'MaterialsInboxView',
    data() {
        return {
            isLoading: false,
            users: [],
            openHierarchyCards: {},
            inboxMaterialFilter: 'all',
            einfachernDialog: {
                open: false,
                loading: false,
                submitting: false,
                error: '',
                ruleId: 0,
                materialId: 0,
                materialTitle: '',
                tree: [],
                subjectId: 0,
                topicId: 0,
                unitId: 0,
            },
            needsMigration: false,
            errorMessage: '',
        }
    },
    mounted() {
        this.loadInboxUsers()
    },
    computed: {
        einfachernSelectedSubject() {
            return this.einfachernDialog.tree.find((subject) => Number(subject?.id || 0) === Number(this.einfachernDialog.subjectId || 0)) || null
        },
        einfachernTopics() {
            return Array.isArray(this.einfachernSelectedSubject?.topics) ? this.einfachernSelectedSubject.topics : []
        },
        einfachernSelectedTopic() {
            return this.einfachernTopics.find((topic) => Number(topic?.id || 0) === Number(this.einfachernDialog.topicId || 0)) || null
        },
        einfachernUnits() {
            return Array.isArray(this.einfachernSelectedTopic?.units) ? this.einfachernSelectedTopic.units : []
        },
        einfachernSelectedUnit() {
            return this.einfachernUnits.find((unit) => Number(unit?.id || 0) === Number(this.einfachernDialog.unitId || 0)) || null
        },
        einfachernSelectedTarget() {
            if (this.einfachernSelectedUnit) {
                return {
                    level: 'unit',
                    id: Number(this.einfachernSelectedUnit.id || 0),
                    label: String(this.einfachernSelectedUnit.name || '').trim() || 'Einheit',
                }
            }
            if (this.einfachernSelectedTopic) {
                return {
                    level: 'topic',
                    id: Number(this.einfachernSelectedTopic.id || 0),
                    label: String(this.einfachernSelectedTopic.name || '').trim() || 'Thema',
                }
            }
            if (this.einfachernSelectedSubject) {
                return {
                    level: 'subject',
                    id: Number(this.einfachernSelectedSubject.id || 0),
                    label: String(this.einfachernSelectedSubject.name || '').trim() || 'Fach',
                }
            }
            return null
        },
        einfachernSelectionLabel() {
            const labels = [
                String(this.einfachernSelectedSubject?.name || '').trim(),
                String(this.einfachernSelectedTopic?.name || '').trim(),
                String(this.einfachernSelectedUnit?.name || '').trim(),
            ].filter((value) => value !== '')
            return labels.length > 0 ? labels.join(' - ') : 'Kein Ziel ausgewählt.'
        },
    },
    methods: {
        setInboxMaterialFilter(mode) {
            const normalized = String(mode || '').trim()
            if (normalized !== 'all' && normalized !== 'new' && normalized !== 'imported') {
                this.inboxMaterialFilter = 'all'
                return
            }
            this.inboxMaterialFilter = normalized
        },
        filteredSharedItems(user) {
            const list = Array.isArray(user?.shared_items) ? user.shared_items : []
            if (this.inboxMaterialFilter === 'imported') {
                return list.filter((item) => !!item?.is_imported)
            }
            if (this.inboxMaterialFilter === 'new') {
                return list.filter((item) => !item?.is_imported)
            }
            return list
        },
        async loadInboxUsers() {
            this.isLoading = true
            this.errorMessage = ''
            try {
                const response = await axios.get('/api/admin/materials/shares/inbox-users')
                const rows = Array.isArray(response.data?.data) ? response.data.data : []
                this.openHierarchyCards = {}
                this.users = rows.map((row) => ({
                    id: Number(row?.id || 0),
                    label: String(row?.label || '').trim() || 'Benutzer',
                    email: String(row?.email || '').trim(),
                    school_label: String(row?.school_label || '').trim(),
                    shared_rules_count: Math.max(0, Number(row?.shared_rules_count || 0)),
                    shared_items: Array.isArray(row?.shared_items)
                        ? row.shared_items.map((item) => ({
                            rule_id: Number(item?.rule_id || 0),
                            scope_type: String(item?.scope_type || '').trim() || 'all',
                            scope_label: String(item?.scope_label || '').trim() || 'Bereich',
                            scope_object_label: String(item?.scope_object_label || '').trim() || 'Unbekannt',
                            scope_path_label: String(item?.scope_path_label || '').trim() || 'Fach - Thema - Einheit',
                            permission: String(item?.permission || '').trim() || 'read_only',
                            permission_label: String(item?.permission_label || '').trim() || 'NUR LESEN',
                            is_imported: !!item?.is_imported,
                            hierarchy: Array.isArray(item?.hierarchy)
                                ? item.hierarchy.map((subject) => ({
                                    id: Number(subject?.id || 0),
                                    name: String(subject?.name || '').trim() || 'Ohne Fach',
                                    topics: Array.isArray(subject?.topics)
                                        ? subject.topics.map((topic) => ({
                                            id: Number(topic?.id || 0),
                                            name: String(topic?.name || '').trim() || 'Ohne Thema',
                                            units: Array.isArray(topic?.units)
                                                ? topic.units.map((unit) => ({
                                                    id: Number(unit?.id || 0),
                                                    name: String(unit?.name || '').trim() || 'Ohne Einheit',
                                                    materials: Array.isArray(unit?.materials)
                                                        ? unit.materials.map((material) => ({
                                                            id: Number(material?.id || 0),
                                                            title: String(material?.title || '').trim() || 'Material',
                                                            icon: String(material?.icon || '').trim(),
                                                            type: String(material?.type || '').trim(),
                                                            typeLabel: String(material?.type_label || material?.typeLabel || material?.type || '').trim(),
                                                            typeColor: String(material?.type_color || material?.typeColor || '').trim(),
                                                            status: String(material?.status || '').trim(),
                                                            statusLabel: String(material?.status_label || material?.statusLabel || '').trim(),
                                                            statusColor: String(material?.status_color || material?.statusColor || '').trim(),
                                                            attachmentsCount: Math.max(0, Number(material?.attachments_count ?? material?.attachmentsCount ?? 0) || 0),
                                                        }))
                                                        : [],
                                                }))
                                                : [],
                                        }))
                                        : [],
                                }))
                                : [],
                            materialPreview: this.extractFirstHierarchyMaterial(item?.hierarchy),
                            updated_at: String(item?.updated_at || '').trim(),
                        })).filter((item) => item.rule_id > 0)
                        : [],
                })).filter((row) => row.id > 0)
                this.needsMigration = !!response.data?.meta?.needs_migration
            } catch (error) {
                this.users = []
                this.openHierarchyCards = {}
                this.needsMigration = false
                this.errorMessage = error?.response?.data?.message || 'Inbox konnte nicht geladen werden.'
            } finally {
                this.isLoading = false
            }
        },
        formatDateTime(value) {
            if (!value) return ''
            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return ''
            return new Intl.DateTimeFormat('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date)
        },
        permissionChipColor(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 'error'
            if (normalized === 'read_write') return 'warning'
            return 'primary'
        },
        isTopicDirectUnit(unit) {
            const name = String(unit?.name || '').trim().toLocaleLowerCase()
            const id = Number(unit?.id || 0)
            return id <= 0 && name === 'ohne einheit'
        },
        hierarchyKey(userId, ruleId) {
            return `${Number(userId || 0)}-${Number(ruleId || 0)}`
        },
        isHierarchyOpen(userId, ruleId) {
            const key = this.hierarchyKey(userId, ruleId)
            return !!this.openHierarchyCards[key]
        },
        toggleHierarchy(userId, ruleId) {
            const key = this.hierarchyKey(userId, ruleId)
            this.openHierarchyCards = {
                ...this.openHierarchyCards,
                [key]: !this.openHierarchyCards[key],
            }
        },
        async openEinfachernDialog(item) {
            const preview = item?.materialPreview || null
            this.einfachernDialog.open = true
            this.einfachernDialog.error = ''
            this.einfachernDialog.ruleId = Number(item?.rule_id || 0)
            this.einfachernDialog.materialId = Number(preview?.id || 0)
            this.einfachernDialog.materialTitle = String(preview?.title || item?.scope_object_label || 'Material').trim() || 'Material'
            this.einfachernDialog.subjectId = 0
            this.einfachernDialog.topicId = 0
            this.einfachernDialog.unitId = 0
            await this.loadEinfachernTree()
        },
        closeEinfachernDialog() {
            this.einfachernDialog.open = false
            this.einfachernDialog.submitting = false
        },
        async loadEinfachernTree() {
            this.einfachernDialog.loading = true
            this.einfachernDialog.error = ''
            try {
                const response = await axios.get('/api/admin/materials/config')
                const rawTree = Array.isArray(response?.data?.classification_tree) ? response.data.classification_tree : []
                this.einfachernDialog.tree = rawTree.map((subject) => ({
                    id: Number(subject?.id || 0),
                    name: String(subject?.name || '').trim() || 'Fach',
                    topics: Array.isArray(subject?.topics)
                        ? subject.topics.map((topic) => ({
                            id: Number(topic?.id || 0),
                            name: String(topic?.name || '').trim() || 'Thema',
                            units: Array.isArray(topic?.units)
                                ? topic.units.map((unit) => ({
                                    id: Number(unit?.id || 0),
                                    name: String(unit?.name || '').trim() || 'Einheit',
                                }))
                                : [],
                        }))
                        : [],
                })).filter((subject) => Number(subject.id || 0) > 0)
            } catch (error) {
                this.einfachernDialog.tree = []
                this.einfachernDialog.error = error?.response?.data?.message || 'Fächer konnten nicht geladen werden.'
            } finally {
                this.einfachernDialog.loading = false
            }
        },
        selectEinfachernSubject(subject) {
            this.einfachernDialog.subjectId = Number(subject?.id || 0)
            this.einfachernDialog.topicId = 0
            this.einfachernDialog.unitId = 0
        },
        selectEinfachernTopic(topic) {
            this.einfachernDialog.topicId = Number(topic?.id || 0)
            this.einfachernDialog.unitId = 0
        },
        selectEinfachernUnit(unit) {
            this.einfachernDialog.unitId = Number(unit?.id || 0)
        },
        onDummyHierEinfachern() {
            // Placeholder for future classification action.
        },
        async onOriginalEinfuegen() {
            const ruleId = Number(this.einfachernDialog.ruleId || 0)
            const materialId = Number(this.einfachernDialog.materialId || 0)
            if (ruleId <= 0 || materialId <= 0 || this.einfachernDialog.submitting) return

            this.einfachernDialog.submitting = true
            this.einfachernDialog.error = ''
            try {
                await axios.post('/api/admin/materials/shares/inbox/material-original-copy', {
                    rule_id: ruleId,
                    material_id: materialId,
                })
                this.closeEinfachernDialog()
                await this.loadInboxUsers()
            } catch (error) {
                this.einfachernDialog.error = error?.response?.data?.message || 'Original konnte nicht eingefügt werden.'
            } finally {
                this.einfachernDialog.submitting = false
            }
        },
        extractFirstHierarchyMaterial(hierarchy) {
            if (!Array.isArray(hierarchy)) return null

            for (const subject of hierarchy) {
                const topics = Array.isArray(subject?.topics) ? subject.topics : []
                for (const topic of topics) {
                    const units = Array.isArray(topic?.units) ? topic.units : []
                    for (const unit of units) {
                        const materials = Array.isArray(unit?.materials) ? unit.materials : []
                        if (materials.length > 0) {
                            const material = materials[0]
                            return {
                                id: Number(material?.id || 0),
                                title: String(material?.title || '').trim() || 'Material',
                                icon: String(material?.icon || '').trim(),
                                type: String(material?.type || '').trim(),
                                typeLabel: String(material?.type_label || material?.typeLabel || material?.type || '').trim(),
                                typeColor: String(material?.type_color || material?.typeColor || '').trim(),
                                status: String(material?.status || '').trim(),
                                statusLabel: String(material?.status_label || material?.statusLabel || '').trim(),
                                statusColor: String(material?.status_color || material?.statusColor || '').trim(),
                                attachmentsCount: Math.max(0, Number(material?.attachments_count ?? material?.attachmentsCount ?? 0) || 0),
                            }
                        }
                    }
                }
            }

            return null
        },
        materialStatusLabel(status) {
            const normalized = String(status || '').trim().toLocaleLowerCase()
            const map = {
                inbox: 'Neu/Idee',
                in_progress: 'In Arbeit',
                done: 'ok',
                update_needed: 'Änderung nötig',
            }
            return map[normalized] || 'Unbekannt'
        },
        materialStatusColor(status) {
            const normalized = String(status || '').trim().toLocaleLowerCase()
            const map = {
                inbox: 'secondary',
                in_progress: 'warning',
                done: 'success',
                update_needed: 'error',
            }
            return map[normalized] || 'primary'
        },
        onDummyObjectAction() {
            // Placeholder for future object actions.
        },
    },
}
</script>

<style scoped>
.inbox-shared-panels {
    margin-top: 6px;
}

.inbox-shared-list {
    display: grid;
    gap: 10px;
}

.inbox-shared-object-card {
    border: 1px solid rgba(35, 61, 76, 0.16);
    border-radius: 10px;
    background: rgba(248, 239, 231, 0.42);
    padding: 10px 12px;
}

.inbox-shared-object-head {
    margin-bottom: 10px;
}

.inbox-shared-object-head-top {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.inbox-shared-object-scope {
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    color: #1f6f8b;
    text-transform: uppercase;
}

.inbox-shared-object-title {
    font-size: 1rem;
    font-weight: 700;
    color: #233d4c;
    line-height: 1.25;
    margin-top: 2px;
}

.inbox-shared-object-path {
    margin-top: 4px;
    font-size: 0.84rem;
    color: #3c5a6d;
}

.inbox-shared-hierarchy {
    margin-top: 8px;
    padding: 10px;
}

.inbox-hierarchy-card {
    margin-top: 8px;
    background: rgba(255, 255, 255, 0.68);
    border-color: rgba(35, 61, 76, 0.16);
}

.inbox-hierarchy-subject + .inbox-hierarchy-subject {
    margin-top: 10px;
}

.inbox-hierarchy-subject-title {
    font-size: 0.86rem;
    font-weight: 700;
    color: #1f4f89;
}

.inbox-hierarchy-topic {
    margin-top: 6px;
    margin-left: 10px;
}

.inbox-hierarchy-topic--root {
    margin-left: 0;
}

.inbox-hierarchy-topic-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #2f607e;
}

.inbox-hierarchy-unit {
    margin-top: 6px;
    margin-left: 10px;
}

.inbox-hierarchy-unit-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #3a5668;
}

.inbox-hierarchy-material-lines {
    margin-top: 4px;
    display: grid;
    gap: 3px;
}

.inbox-hierarchy-material-line {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: #233d4c;
}

.inbox-hierarchy-material-title {
    line-height: 1.2;
}

.inbox-hierarchy-material-count {
    display: inline-flex;
    align-items: center;
    padding: 0 6px;
    border-radius: 999px;
    border: 1px solid rgba(35, 61, 76, 0.2);
    background: rgba(35, 61, 76, 0.06);
    font-size: 0.72rem;
    line-height: 1.2;
}

.inbox-material-overview-line {
    margin-top: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.einfachern-empty {
    display: grid;
    gap: 10px;
    justify-items: start;
}

.einfachern-columns {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.einfachern-column {
    border: 1px solid rgba(35, 61, 76, 0.16);
    border-radius: 10px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.7);
    min-height: 160px;
}

.einfachern-column-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: #1f6f8b;
    margin-bottom: 8px;
}

.einfachern-list {
    display: grid;
    gap: 6px;
}

.inbox-shared-object-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

@media (max-width: 960px) {
    .einfachern-columns {
        grid-template-columns: 1fr;
    }
}
</style>
