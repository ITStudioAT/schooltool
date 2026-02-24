<template>
    <v-dialog :model-value="modelValue" max-width="720" persistent @update:modelValue="$emit('update:modelValue', $event)">
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2 py-4">
                <v-avatar color="primary" variant="tonal" size="32">
                    <v-icon icon="mdi-share-variant-outline" />
                </v-avatar>
                <span class="text-h6">Freigabe (Dummy)</span>
                <v-spacer />
                <v-btn icon="mdi-close" variant="text" @click="$emit('update:modelValue', false)" />
            </v-card-title>

            <v-card-text class="pt-2">
                <div class="share-dummy-shell">
                    <section class="share-dummy-panel">
                        <div class="text-caption text-medium-emphasis mb-2">Ausgewählt zum Freigeben</div>
                        <div v-if="target?.level" class="d-flex flex-wrap ga-2 mb-2">
                            <v-chip color="primary" variant="flat" size="small">
                                Ebene: {{ levelLabel(target.level) }}
                            </v-chip>
                        </div>
                        <div class="text-body-1 font-weight-medium">
                            {{ target?.label || '-' }}
                        </div>
                        <div v-if="showMaterialMeta(target)" class="d-flex flex-wrap ga-2 mt-2">
                            <v-chip
                                v-if="target?.kindLabel"
                                size="x-small"
                                variant="outlined"
                                :color="target?.kindColor || 'primary'">
                                Art: {{ target.kindLabel }}
                            </v-chip>
                            <v-chip
                                v-if="target?.statusLabel"
                                size="x-small"
                                variant="flat"
                                :color="target?.statusColor || 'primary'">
                                Status: {{ target.statusLabel }}
                            </v-chip>
                            <v-chip
                                v-if="hasAttachmentCount(target)"
                                size="x-small"
                                variant="flat"
                                color="primary"
                                prepend-icon="mdi-paperclip">
                                Anhänge: {{ Number(target.attachmentsCount || 0) }}
                            </v-chip>
                        </div>
                        <div v-if="target?.parentLabel" class="text-caption text-medium-emphasis mt-1">
                            Kontext: {{ target.parentLabel }}
                        </div>
                    </section>

                    <section class="share-dummy-panel share-dummy-panel--soft">
                        <div class="d-flex align-center flex-wrap ga-2 mb-2">
                            <div class="text-subtitle-2">Freigabeart</div>
                            <v-chip size="x-small" color="primary" variant="flat">
                                {{ shareModeLabel(shareMode) }}
                            </v-chip>
                        </div>
                        <div class="text-caption text-medium-emphasis mb-3">
                            Dummy-Auswahl für die spätere Freigabe-Logik.
                        </div>

                        <v-btn-toggle
                            v-model="shareMode"
                            mandatory
                            color="primary"
                            variant="outlined"
                            class="share-dummy-mode-toggle">
                            <v-btn value="full_access" class="share-dummy-mode-btn">
                                Vollzugriff
                            </v-btn>
                            <v-btn value="read_write" class="share-dummy-mode-btn">
                                Lesen/Schreiben
                            </v-btn>
                            <v-btn value="read_only" class="share-dummy-mode-btn">
                                Nur Lesen
                            </v-btn>
                        </v-btn-toggle>
                    </section>

                    <section class="share-dummy-panel">
                        <div class="text-subtitle-2 mb-2">Bereits freigegeben an</div>
                        <div v-if="loading" class="text-body-2 text-medium-emphasis">
                            Lade vorhandene Freigaben ...
                        </div>
                        <v-alert
                            v-else-if="error"
                            type="warning"
                            variant="tonal"
                            density="compact"
                            class="mb-0">
                            {{ error }}
                        </v-alert>
                        <div v-else-if="!assignments.length" class="text-body-2 text-medium-emphasis">
                            Noch keine Freigabe für dieses Objekt vorhanden.
                        </div>
                        <div v-else class="d-grid ga-2">
                            <div
                                v-for="assignment in assignments"
                                :key="`share-dummy-assignment-${assignment.id}`"
                                class="share-dummy-assignment">
                                <div class="d-flex align-center flex-wrap ga-2 mb-1">
                                    <v-chip
                                        :color="assignment.is_active ? 'success' : 'secondary'"
                                        variant="flat"
                                        size="x-small">
                                        {{ assignment.is_active ? 'aktiv' : 'inaktiv' }}
                                    </v-chip>
                                    <span class="text-caption text-medium-emphasis">
                                        von {{ assignment.created_by_label || 'Unbekannt' }}
                                    </span>
                                </div>
                                <div class="d-flex flex-wrap ga-1">
                                    <v-chip
                                        v-for="shareTarget in assignment.targets || []"
                                        :key="`share-dummy-target-${assignment.id}-${shareTarget.id}`"
                                        :color="targetChipColor(shareTarget)"
                                        variant="flat"
                                        size="x-small">
                                        {{ shareTarget.label }}
                                    </v-chip>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </v-card-text>

            <v-card-actions class="px-6 pb-5 pt-1 d-flex justify-end ga-2">
                <v-btn variant="text" @click="$emit('update:modelValue', false)">
                    Abbrechen
                </v-btn>
                <v-btn color="primary" variant="flat" @click="$emit('update:modelValue', false)">
                    Schließen
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
export default {
    name: 'MaterialShareDummyDialog',
    props: {
        modelValue: {
            type: Boolean,
            required: true,
        },
        target: {
            type: Object,
            required: true,
        },
        assignments: {
            type: Array,
            default: () => [],
        },
        loading: {
            type: Boolean,
            default: false,
        },
        error: {
            type: String,
            default: '',
        },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            shareMode: 'read_only',
        }
    },
    methods: {
        levelLabel(level) {
            return ({
                subject: 'Fach',
                topic: 'Thema',
                unit: 'Einheit',
                material: 'Material',
            })[String(level || '').trim()] || String(level || '-')
        },
        targetChipColor(target) {
            if (target?.target_type === 'everyone') return 'success'
            if (target?.target_type === 'group') return 'primary'
            return 'secondary'
        },
        shareModeLabel(mode) {
            return ({
                full_access: 'Vollzugriff',
                read_write: 'Lesen/Schreiben',
                read_only: 'Nur Lesen',
            })[String(mode || '').trim()] || 'Nur Lesen'
        },
        hasAttachmentCount(target) {
            const value = Number(target?.attachmentsCount)
            return Number.isFinite(value) && value >= 0
        },
        showMaterialMeta(target) {
            if (String(target?.level || '').trim() !== 'material') return false
            return Boolean(
                String(target?.kindLabel || '').trim() ||
                String(target?.statusLabel || '').trim() ||
                this.hasAttachmentCount(target),
            )
        },
    },
}
</script>

<style scoped>
.share-dummy-shell {
    display: grid;
    gap: 12px;
}

.share-dummy-panel {
    border: 1px solid rgba(35, 61, 76, 0.12);
    background: rgba(255, 255, 255, 0.74);
    border-radius: 12px;
    padding: 12px;
}

.share-dummy-panel--soft {
    background: linear-gradient(180deg, rgba(25, 118, 210, 0.05) 0%, rgba(25, 118, 210, 0.02) 100%);
    border-color: rgba(25, 118, 210, 0.16);
}

.share-dummy-mode-toggle {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.share-dummy-mode-toggle :deep(.v-btn-toggle) {
    width: 100%;
}

.share-dummy-mode-toggle :deep(.v-btn) {
    min-width: 0;
}

.share-dummy-mode-btn {
    text-transform: none;
}

.share-dummy-assignment {
    border: 1px solid rgba(35, 61, 76, 0.1);
    background: rgba(255, 255, 255, 0.7);
    border-radius: 10px;
    padding: 8px 10px;
}

@media (max-width: 640px) {
    .share-dummy-mode-toggle {
        grid-template-columns: 1fr;
    }
}
</style>
