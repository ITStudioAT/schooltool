<template>
    <section
        class="timetable-student-summary"
        :class="`timetable-student-summary--${variant}`">
        <div class="timetable-student-summary__identity-row">
            <div v-if="studentVisible" class="timetable-student-summary__student">
                <div class="timetable-student-summary__avatar" aria-hidden="true">
                    {{ initials }}
                </div>
                <strong class="timetable-student-summary__name">{{ studentLabel }}</strong>
                <span
                    v-if="variant === 'review' && (religionMeta || email)"
                    class="timetable-student-summary__divider"
                    aria-hidden="true"></span>
                <span v-if="religionMeta" class="timetable-student-summary__religion">
                    {{ religionMeta }}
                </span>
                <button
                    v-if="email"
                    type="button"
                    class="timetable-student-summary__email"
                    :class="{ 'timetable-student-summary__email--copied': emailCopied }"
                    :aria-label="emailCopied ? `E-Mail-Adresse kopiert: ${email}` : `E-Mail-Adresse kopieren: ${email}`"
                    :title="emailCopied ? 'Kopiert!' : `E-Mail kopieren: ${email}`"
                    @click.stop="$emit('copy-email')">
                    <v-icon icon="mdi-email-outline" size="14" />
                    <span>{{ email }}</span>
                    <v-icon :icon="emailCopied ? 'mdi-check' : 'mdi-content-copy'" size="13" />
                </button>
                <div v-if="showStudentActions" class="timetable-student-summary__actions">
                    <v-btn
                        icon="mdi-pencil"
                        variant="text"
                        density="compact"
                        size="x-small"
                        title="Student bearbeiten"
                        @click.stop="$emit('edit-student')" />
                    <v-btn
                        v-if="studentRemovable"
                        icon="mdi-close"
                        variant="text"
                        density="compact"
                        size="x-small"
                        title="Student entfernen"
                        @click.stop="$emit('remove-student')" />
                </div>
            </div>

            <div v-else class="timetable-student-summary__no-student">
                <v-icon icon="mdi-account-off-outline" size="18" />
                <span>Ohne Studierenden</span>
            </div>
        </div>

        <div
            v-if="selectionVisible"
            class="timetable-student-summary__selections"
            :class="{ 'timetable-student-summary__selections--editable': editableSelections }">
            <template v-if="editableSelections">
                <div
                    v-for="item in selectionItems"
                    :key="item.key"
                    class="timetable-student-summary__selection-group"
                    role="group"
                    :aria-label="item.label"
                    :title="item.label">
                    <span class="timetable-student-summary__selection-label">{{ item.label }}</span>
                    <div v-if="item.options?.length" class="timetable-student-summary__selection-options">
                        <v-chip
                            v-for="option in item.options"
                            :key="option.value"
                            size="small"
                            density="default"
                            variant="flat"
                            class="timetable-student-summary__selection-option"
                            :class="{
                                'timetable-student-summary__selection-option--selected': optionSelected(item, option),
                            }"
                            :aria-pressed="optionSelected(item, option) ? 'true' : 'false'"
                            @click="$emit('select-option', item.key, option.value)">
                            <v-icon v-if="optionSelected(item, option)" icon="mdi-check" size="14" />
                            {{ option.title }}
                        </v-chip>
                    </div>
                    <span v-if="item.meta" class="timetable-student-summary__selection-meta">{{ item.meta }}</span>
                    <strong
                        v-else-if="!item.options?.length"
                        class="timetable-student-summary__selection-value"
                        :class="{ 'timetable-student-summary__selection-value--unknown': !item.known }">
                        {{ item.value }}
                    </strong>
                </div>
            </template>
            <template v-else>
                <v-chip
                    v-for="item in selectionItems"
                    :key="item.key"
                    size="small"
                    variant="flat"
                    class="timetable-student-summary__readonly-chip">
                    {{ item.label }}: {{ item.value }}
                </v-chip>
            </template>
        </div>
    </section>
</template>

<script>
export default {
    props: {
        editableSelections: {
            type: Boolean,
            default: false,
        },
        email: {
            type: String,
            default: '',
        },
        emailCopied: {
            type: Boolean,
            default: false,
        },
        initials: {
            type: String,
            default: '',
        },
        religionMeta: {
            type: String,
            default: '',
        },
        selectionItems: {
            type: Array,
            default: () => [],
        },
        selectionValues: {
            type: Object,
            default: () => ({}),
        },
        selectionVisible: {
            type: Boolean,
            default: true,
        },
        showStudentActions: {
            type: Boolean,
            default: false,
        },
        studentLabel: {
            type: String,
            default: '',
        },
        studentRemovable: {
            type: Boolean,
            default: false,
        },
        studentVisible: {
            type: Boolean,
            default: true,
        },
        variant: {
            type: String,
            default: 'selection',
            validator: (variant) => ['selection', 'review'].includes(variant),
        },
    },

    emits: ['copy-email', 'edit-student', 'remove-student', 'select-option'],

    methods: {
        optionSelected(item, option) {
            return String(this.selectionValues?.[item.key] || '') === String(option.value)
        },
    },
}
</script>

<style scoped>
.timetable-student-summary {
    display: grid;
    gap: 18px;
    width: 100%;
    border-radius: 12px;
}

.timetable-student-summary--review {
    gap: 14px;
    border: 1px solid var(--schedule-border);
    padding: 18px 20px;
    background: #f7f8fb;
}

.timetable-student-summary__identity-row {
    display: flex;
    align-items: center;
    min-height: 76px;
    border: 1px solid var(--schedule-border);
    border-radius: 12px;
    padding: 16px 20px;
    background: #f7f8fb;
}

.timetable-student-summary--review .timetable-student-summary__identity-row {
    min-height: 40px;
    border: 0;
    padding: 0;
    background: transparent;
}

.timetable-student-summary__student {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-width: 0;
}

.timetable-student-summary__avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--schedule-accent);
    color: #ffffff;
    flex: 0 0 40px;
    font-size: 0.84rem;
    font-weight: 700;
}

.timetable-student-summary__name {
    min-width: 0;
    overflow: hidden;
    color: var(--schedule-heading);
    font-size: 0.94rem;
    font-weight: 700;
    line-height: 1.2;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.timetable-student-summary__divider {
    width: 1px;
    height: 20px;
    background: #d7dae2;
    flex: 0 0 1px;
}

.timetable-student-summary__religion {
    color: var(--schedule-muted);
    font-size: 0.78rem;
    font-weight: 500;
    white-space: nowrap;
}

.timetable-student-summary__email {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 0;
    padding: 0;
    background: transparent;
    color: var(--schedule-muted);
    font: inherit;
    font-size: 0.78rem;
    font-weight: 500;
    cursor: pointer;
}

.timetable-student-summary--review .timetable-student-summary__email {
    border-radius: 8px;
    padding: 6px 10px;
    background: #eef1ff;
    color: var(--schedule-accent-dark);
    font-weight: 600;
}

.timetable-student-summary__email:hover,
.timetable-student-summary__email:focus-visible {
    color: var(--schedule-accent-dark);
}

.timetable-student-summary__email--copied {
    color: #15803d !important;
}

.timetable-student-summary__actions {
    display: flex;
    align-items: center;
    gap: 2px;
    margin-left: auto;
    color: #475569;
}

.timetable-student-summary__no-student {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
}

.timetable-student-summary__selections {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.timetable-student-summary__selections--editable {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    align-items: start;
    gap: 16px;
}

.timetable-student-summary__selection-group {
    display: grid;
    align-items: start;
    gap: 8px;
    min-width: 0;
}

.timetable-student-summary__selection-label {
    color: var(--schedule-muted);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.timetable-student-summary__selection-options {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.timetable-student-summary__selection-option {
    min-height: 30px;
    border: 1.5px solid transparent !important;
    border-radius: 8px !important;
    background: #f1f2f6 !important;
    color: #5b6472 !important;
    font-size: 0.78rem !important;
    font-weight: 600;
    cursor: pointer;
    box-shadow: none !important;
}

.timetable-student-summary__selection-option:hover {
    border-color: #c7c9f5 !important;
    color: var(--schedule-accent-dark) !important;
}

.timetable-student-summary__selection-option--selected {
    border-color: var(--schedule-accent-dark) !important;
    background: var(--schedule-accent) !important;
    color: #ffffff !important;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35) !important;
}

.timetable-student-summary__readonly-chip {
    border: 1px solid #d7dae2 !important;
    border-radius: 8px !important;
    background: #ffffff !important;
    color: #3d4451 !important;
    font-size: 0.78rem;
    font-weight: 600;
}

.timetable-student-summary__selection-meta {
    width: fit-content;
    border-radius: 999px;
    padding: 1px 7px;
    background: #f1f2f6;
    color: #5b6472;
    font-size: 0.7rem;
    font-weight: 700;
}

.timetable-student-summary__selection-value {
    color: var(--schedule-heading);
    font-size: 0.82rem;
    font-weight: 700;
}

.timetable-student-summary__selection-value--unknown {
    width: fit-content;
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 999px;
    padding: 1px 8px;
    background: rgba(148, 163, 184, 0.1);
    color: #64748b;
}

@media (max-width: 700px) {
    .timetable-student-summary--review,
    .timetable-student-summary__identity-row {
        padding: 14px;
    }

    .timetable-student-summary__student {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .timetable-student-summary__divider {
        display: none;
    }

    .timetable-student-summary__selections--editable {
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
    }
}

@media (min-width: 701px) and (max-width: 1100px) {
    .timetable-student-summary__selections--editable {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
