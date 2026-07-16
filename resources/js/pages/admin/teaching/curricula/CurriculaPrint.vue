<template>
    <v-col cols="12">
        <v-sheet rounded="xl" class="curricula-print pa-6">
            <div class="curricula-print__header d-flex align-center ga-3 mb-4">
                <v-btn
                    variant="flat"
                    color="orange"
                    size="small"
                    rounded="xl"
                    prepend-icon="mdi-arrow-left"
                    class="text-none"
                    @click="$emit('back')">
                    {{ curriculum ? 'Zurück zum Curriculum' : 'Zurück zur Übersicht' }}
                </v-btn>
                <v-spacer />
                <v-icon size="28" color="primary">mdi-printer-outline</v-icon>
                <h3 class="curricula-print__title">Ausdruck</h3>
            </div>

            <p v-if="!selectedOption" class="curricula-print__subtitle mb-4">
                Wähle eine Druckvorlage für <strong>{{ curriculum.title }}</strong>:
            </p>

            <v-row v-if="!selectedOption">
                <v-col
                    v-for="option in printOptions"
                    :key="option.key"
                    cols="12"
                    sm="6"
                    md="4">
                    <v-sheet
                        rounded="xl"
                        class="curricula-print__option pa-5"
                        @click="selectedOption = option.key">
                        <v-icon size="32" color="primary" class="mb-3">
                            {{ option.icon }}
                        </v-icon>
                        <h4 class="curricula-print__option-title">{{ option.label }}</h4>
                        <p class="curricula-print__option-desc">{{ option.description }}</p>
                    </v-sheet>
                </v-col>
            </v-row>

            <div v-if="selectedOption" class="curricula-print__preview">
                <div class="curricula-print__preview-toolbar d-flex align-center ga-3 mb-4">
                    <v-spacer />
                    <v-btn
                        color="primary"
                        variant="flat"
                        size="small"
                        rounded="xl"
                        prepend-icon="mdi-file-word"
                        class="text-none"
                        :loading="exporting === 'word'"
                        :disabled="!!exporting"
                        @click="handleWord">
                        Word
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        size="small"
                        rounded="xl"
                        prepend-icon="mdi-file-pdf-box"
                        class="text-none"
                        :loading="exporting === 'pdf'"
                        :disabled="!!exporting"
                        @click="handlePdf">
                        PDF
                    </v-btn>
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        rounded="lg"
                        density="comfortable"
                        @click="selectedOption = null" />
                </div>

                <div v-if="selectedOption === 'overview'" class="curricula-print__overview">
                    <h3 class="curricula-print__overview-title mb-1">{{ curriculum.title }}</h3>
                    <p class="curricula-print__overview-meta mb-4">
                        {{ curriculumTopics.length }} Themen
                    </p>

                    <div
                        v-for="(topic, topicIndex) in curriculumTopics"
                        :key="topic.id"
                        class="curricula-print__topic">
                        <div class="curricula-print__topic-header">
                            <span class="curricula-print__topic-number">{{ topicIndex + 1 }}.</span>
                            <div class="curricula-print__topic-header-content">
                                <span class="curricula-print__topic-title">{{ topic.title }}</span>
                            </div>
                        </div>

                        <div v-if="topic.units && topic.units.length" class="curricula-print__units">
                            <div
                                v-for="unit in topic.units"
                                :key="unit.id"
                                class="curricula-print__unit"
                                :class="{ 'curricula-print__unit--exam': unit.is_exam }">
                                <div class="curricula-print__unit-title">
                                    <v-icon v-if="unit.is_exam" size="14" color="error" class="mr-1">mdi-clipboard-check-outline</v-icon>
                                    {{ unit.title }}
                                    <v-chip v-if="unit.is_exam" size="x-small" color="error" variant="tonal" class="ml-2">Prüfung</v-chip>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="!curriculumTopics.length" class="curricula-print__empty">
                        Keine Inhalte vorhanden.
                    </div>
                </div>

                <div v-else-if="selectedOption === 'semester'" class="curricula-print__placeholder">
                    <v-icon size="48" color="grey-lighten-1" class="mb-3">mdi-book-open-page-variant-outline</v-icon>
                    <p>Semesterplan – wird noch implementiert.</p>
                </div>

                <div v-else-if="selectedOption === 'weekly'" class="curricula-print__placeholder">
                    <v-icon size="48" color="grey-lighten-1" class="mb-3">mdi-calendar-week-outline</v-icon>
                    <p>Wochenplan – wird noch implementiert.</p>
                </div>
            </div>
        </v-sheet>
    </v-col>
</template>

<script>
export default {
    name: 'CurriculaPrint',
    emits: ['back'],
    props: {
        curriculum: { type: Object, default: null },
    },
    data() {
        return {
            selectedOption: null,
            exporting: null,
            printOptions: [
                {
                    key: 'overview',
                    label: 'Themenübersicht',
                    description: 'Kompakte Übersicht aller Themen und Einheiten.',
                    icon: 'mdi-format-list-bulleted',
                },
            ],
        }
    },
    computed: {
        curriculumTopics() {
            return Array.isArray(this.curriculum?.topics) ? this.curriculum.topics : []
        },
    },
    methods: {
        topicDateRange(topic) {
            const allWeekKeys = []
            const allMonthKeys = []

            const units = Array.isArray(topic.units) ? topic.units : []
            units.forEach((unit) => {
                const type = unit.assignment_type || 'none'
                if (type === 'weeks' && Array.isArray(unit.week_keys)) {
                    allWeekKeys.push(...unit.week_keys)
                }
                if (type === 'month' && Array.isArray(unit.month_keys)) {
                    allMonthKeys.push(...unit.month_keys)
                }
            })

            if (topic.assignment_type === 'weeks' && Array.isArray(topic.week_keys)) {
                allWeekKeys.push(...topic.week_keys)
            }
            if (topic.assignment_type === 'month' && Array.isArray(topic.month_keys)) {
                allMonthKeys.push(...topic.month_keys)
            }

            const monthNames = new Set()

            if (allMonthKeys.length) {
                const unique = [...new Set(allMonthKeys)].sort()
                unique.forEach((k) => monthNames.add(this.monthKeyToFullLabel(k)))
            }

            let kwLabel = null

            if (allWeekKeys.length) {
                const unique = [...new Set(allWeekKeys)].sort()
                const parsed = unique.map((k) => {
                    const monday = new Date(`${k}T00:00:00`)
                    return { monday, kw: this.getISOWeek(monday) }
                }).sort((a, b) => a.monday - b.monday)

                parsed.forEach((p) => monthNames.add(this.monthNameFromDate(p.monday)))

                const first = parsed[0]
                const last = parsed[parsed.length - 1]

                if (first.kw === last.kw) {
                    kwLabel = `KW ${first.kw}`
                } else {
                    kwLabel = `KW ${first.kw}–${last.kw}`
                }
            }

            const parts = []
            if (monthNames.size) {
                const names = [...monthNames]
                if (names.length === 1) {
                    parts.push(names[0])
                } else {
                    parts.push(`${names[0]} – ${names[names.length - 1]}`)
                }
            }
            if (kwLabel) parts.push(kwLabel)

            return parts.length ? parts.join(' · ') : null
        },
        dateRangeLabel(item) {
            const type = item.assignment_type || 'none'
            if (type === 'none' || type === 'all_weeks') return null

            if (type === 'weeks') {
                const weekKeys = Array.isArray(item.week_keys) ? item.week_keys : []
                if (!weekKeys.length) return null
                const sorted = [...weekKeys].sort()
                const firstMonday = new Date(`${sorted[0]}T00:00:00`)
                const lastMonday = new Date(`${sorted[sorted.length - 1]}T00:00:00`)
                const lastSunday = this.sundayOf(lastMonday)
                return `${this.formatDate(firstMonday)} – ${this.formatDate(lastSunday)}`
            }

            return null
        },
        weekKeysToLabel(weekKeys) {
            if (!weekKeys.length) return null

            const parsed = weekKeys.map((k) => {
                const monday = new Date(`${k}T00:00:00`)
                return { key: k, monday, kw: this.getISOWeek(monday) }
            }).sort((a, b) => a.monday - b.monday)

            const first = parsed[0]
            const last = parsed[parsed.length - 1]

            const firstLabel = this.weekLabel(first.monday, first.kw)

            if (parsed.length === 1) {
                return firstLabel
            }

            const lastLabel = this.weekLabel(last.monday, last.kw)

            const firstMonth = this.monthNameFromDate(first.monday)
            const lastMonth = this.monthNameFromDate(last.monday)

            if (firstMonth === lastMonth) {
                return `${firstMonth} (KW ${first.kw}–${last.kw})`
            }

            return `${firstLabel} – ${lastLabel}`
        },
        weekLabel(monday, kw) {
            return `${this.monthNameFromDate(monday)} (KW ${kw})`
        },
        weekDateRange(monday) {
            const sunday = this.sundayOf(monday)
            return `${this.formatDate(monday)} – ${this.formatDate(sunday)}`
        },
        sundayOf(monday) {
            const sunday = new Date(monday)
            sunday.setDate(sunday.getDate() + 6)
            return sunday
        },
        monthNameFromDate(date) {
            const monthNames = ['Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']
            return monthNames[date.getMonth()]
        },
        formatDate(date) {
            return `${date.getDate()}.${date.getMonth() + 1}.`
        },
        getISOWeek(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
            const dayNum = d.getUTCDay() || 7
            d.setUTCDate(d.getUTCDate() + 4 - dayNum)
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
            return Math.ceil(((d - yearStart) / 86400000 + 1) / 7)
        },
        async handleWord() {
            if (!this.curriculum?.id) return
            this.exporting = 'word'
            try {
                const response = await fetch(`/api/admin/teaching/curricula/${this.curriculum.id}/export/word`, {
                    credentials: 'include',
                })
                if (!response.ok) throw new Error('Export fehlgeschlagen')
                const blob = await response.blob()
                this.downloadBlob(blob, `Curriculum_${this.curriculum.title}.docx`)
            } finally {
                this.exporting = null
            }
        },
        async handlePdf() {
            if (!this.curriculum?.id) return
            this.exporting = 'pdf'
            try {
                const response = await fetch(`/api/admin/teaching/curricula/${this.curriculum.id}/export/pdf`, {
                    credentials: 'include',
                })
                if (!response.ok) throw new Error('Export fehlgeschlagen')
                const blob = await response.blob()
                this.downloadBlob(blob, `Curriculum_${this.curriculum.title}.pdf`)
            } finally {
                this.exporting = null
            }
        },
        downloadBlob(blob, filename) {
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = filename
            document.body.appendChild(a)
            a.click()
            document.body.removeChild(a)
            URL.revokeObjectURL(url)
        },
    },
}
</script>

<style scoped>
.curricula-print,
.curricula-print * {
    font-family: Arial, Helvetica, sans-serif !important;
}

.curricula-print {
    border: 1px solid rgba(99, 102, 241, 0.12);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92));
}

.curricula-print__title {
    color: #1e293b;
    font-weight: 700;
    font-size: 1.25rem;
}

.curricula-print__subtitle {
    color: #475569;
    font-size: 0.95rem;
}

.curricula-print__subtitle strong {
    color: #1e293b;
}

.curricula-print__option {
    border: 1px solid rgba(99, 102, 241, 0.12);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92));
    cursor: pointer;
    transition: all 0.2s ease;
    height: 100%;
}

.curricula-print__option:hover {
    border-color: rgba(99, 102, 241, 0.35);
    background: linear-gradient(180deg, rgba(255, 255, 255, 1), rgba(240, 245, 255, 0.96));
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.12);
}

.curricula-print__option-title {
    color: #1e293b;
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 4px;
}

.curricula-print__option-desc {
    color: #475569;
    font-size: 0.82rem;
    line-height: 1.4;
    margin: 0;
}

/* Preview / Overview */
.curricula-print__overview-title {
    color: #1e293b;
    font-size: 1.1rem;
    font-weight: 700;
}

.curricula-print__overview-meta {
    color: #64748b;
    font-size: 0.85rem;
}

.curricula-print__topic {
    margin-bottom: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.curricula-print__topic-header {
    background: #f1f5f9;
    padding: 10px 14px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.curricula-print__topic-header-content {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.curricula-print__topic-number {
    color: #64748b;
    font-weight: 700;
    font-size: 0.85rem;
}

.curricula-print__topic-title {
    color: #1e293b;
    font-weight: 700;
    font-size: 0.9rem;
}

.curricula-print__topic-assignment {
    color: #6366f1;
    font-size: 0.78rem;
    font-weight: 600;
}

.curricula-print__units {
    padding: 6px 14px 10px 32px;
}

.curricula-print__unit {
    padding: 6px 0;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.curricula-print__unit:last-child {
    border-bottom: none;
}

.curricula-print__unit-title {
    color: #334155;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.curricula-print__unit--exam .curricula-print__unit-title {
    color: #dc2626;
    font-weight: 600;
}

.curricula-print__unit-assignment {
    color: #6366f1;
    font-size: 0.75rem;
    font-weight: 500;
    padding-left: 2px;
}

.curricula-print__unit-daterange {
    color: #64748b;
    font-weight: 400;
}

.curricula-print__empty {
    text-align: center;
    padding: 2rem;
    color: #64748b;
}

.curricula-print__placeholder {
    text-align: center;
    padding: 3rem 1rem;
    color: #64748b;
}

@media print {
    .curricula-print__header,
    .curricula-print__preview-toolbar {
        display: none !important;
    }

    .curricula-print {
        border: none;
        background: white;
        box-shadow: none;
        padding: 0 !important;
    }
}
</style>
