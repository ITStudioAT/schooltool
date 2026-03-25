<template>
    <v-col cols="12">
        <div class="online-settings">
            <div class="online-settings__intro">
                <div>
                    <p class="online-settings__eyebrow">Online-Bestellung</p>
                    <h2 class="online-settings__title">Bestellzeitraum für Menüpläne</h2>
                    <p class="online-settings__copy">
                        Legen Sie fest, ab wann ein Menüplan online bestellbar ist und bis wann Bestellungen möglich bleiben.
                    </p>
                </div>

                <div class="online-settings__intro-badges">
                    <span class="online-settings__badge">Live-Vorschau</span>
                    <span class="online-settings__badge online-settings__badge--muted">für alle User der Schule</span>
                </div>
            </div>

            <div class="online-settings__calendar-note">
                Wochen werden hier von Montag bis Sonntag gedacht. Sonntag ist also immer der letzte Tag der Vorwoche.
            </div>

            <div v-if="!canManageOnlineSettings" class="online-settings__message online-settings__message--warning">
                Diese Einstellungen sind erst nach der aktuellen Migration verfügbar.
            </div>

            <template v-else>
                <div class="online-settings__mode-grid">
                    <button
                        type="button"
                        class="online-settings__mode-card"
                        :class="{ 'is-active': form.order_start_mode === 'when_available' }"
                        @click="setStartMode('when_available')">
                        <span class="online-settings__mode-kicker">Einfach</span>
                        <strong>Sobald verfügbar und vollständig</strong>
                        <span>Der Bestellstart erfolgt automatisch, sobald der Menüplan bereit ist.</span>
                    </button>

                    <button
                        type="button"
                        class="online-settings__mode-card"
                        :class="{ 'is-active': form.order_start_mode === 'scheduled' }"
                        @click="setStartMode('scheduled')">
                        <span class="online-settings__mode-kicker">Planbar</span>
                        <strong>An einem festen Tag mit Uhrzeit</strong>
                        <span>Perfekt, wenn die Bestellung immer nach einem klaren Rhythmus starten soll.</span>
                    </button>
                </div>

                <div class="online-settings__configuration-grid">
                    <section class="online-settings__panel">
                        <div class="online-settings__panel-header">
                            <div>
                                <p class="online-settings__panel-eyebrow">Start</p>
                                <h3 class="online-settings__panel-title">Ab wann bestellbar?</h3>
                            </div>
                            <span class="online-settings__panel-status">{{ startStatusLabel }}</span>
                        </div>

                        <div v-if="form.order_start_mode === 'scheduled'" class="online-settings__field-grid">
                            <label class="online-settings__field">
                                <span>Woche</span>
                                <select v-model.number="form.order_start_week_offset">
                                    <option v-for="week in weekOptions" :key="`start-week-${week.value}`" :value="week.value">
                                        {{ week.label }}
                                    </option>
                                </select>
                            </label>

                            <label class="online-settings__field">
                                <span>Tag</span>
                                <select v-model.number="form.order_start_day_of_week">
                                    <option v-for="day in dayOptions" :key="`start-day-${day.value}`" :value="day.value">
                                        {{ day.label }}
                                    </option>
                                </select>
                            </label>

                            <label class="online-settings__field">
                                <span>Uhrzeit</span>
                                <input v-model="form.order_start_time" type="time">
                            </label>
                        </div>

                        <div v-else class="online-settings__message">
                            Bestellstart automatisch, sobald der Menüplan verfügbar und vollständig ist.
                        </div>
                    </section>

                    <section class="online-settings__panel">
                        <div class="online-settings__panel-header">
                            <div>
                                <p class="online-settings__panel-eyebrow">Ende</p>
                                <h3 class="online-settings__panel-title">Bis wann bestellbar?</h3>
                            </div>
                            <span class="online-settings__panel-status">{{ endStatusLabel }}</span>
                        </div>

                        <div class="online-settings__field-grid">
                            <label class="online-settings__field">
                                <span>Woche</span>
                                <select v-model.number="form.order_end_week_offset">
                                    <option v-for="week in weekOptions" :key="`end-week-${week.value}`" :value="week.value">
                                        {{ week.label }}
                                    </option>
                                </select>
                            </label>

                            <label class="online-settings__field">
                                <span>Tag</span>
                                <select v-model.number="form.order_end_day_of_week">
                                    <option v-for="day in dayOptions" :key="`end-day-${day.value}`" :value="day.value">
                                        {{ day.label }}
                                    </option>
                                </select>
                            </label>

                            <label class="online-settings__field">
                                <span>Uhrzeit</span>
                                <input v-model="form.order_end_time" type="time">
                            </label>
                        </div>
                    </section>
                </div>

                <section class="online-settings__preview">
                    <div class="online-settings__preview-header">
                        <div>
                            <p class="online-settings__panel-eyebrow">Vorschau</p>
                            <h3 class="online-settings__panel-title">So wirkt der Zeitraum für Ihre Menüwoche</h3>
                        </div>
                        <button type="button" class="online-settings__ghost-button" @click="applyExamplePreset">
                            Beispiel übernehmen
                        </button>
                    </div>

                    <div class="online-settings__preview-copy">
                        {{ previewText }}
                    </div>

                    <div class="online-settings__week-strip">
                        <section
                            v-for="week in scheduleWeeks"
                            :key="week.value"
                            class="online-settings__week-card">
                            <div class="online-settings__week-title">{{ week.label }}</div>

                            <div class="online-settings__day-grid">
                                <div
                                    v-for="day in week.days"
                                    :key="`${week.value}-${day.value}`"
                                    class="online-settings__day-chip"
                                    :class="{
                                        'has-start': day.hasStart,
                                        'has-end': day.hasEnd,
                                        'is-highlighted': day.hasMarker,
                                    }">
                                    <span class="online-settings__day-label">{{ day.short }}</span>
                                    <span v-if="day.hasStart" class="online-settings__marker online-settings__marker--start">
                                        Start {{ form.order_start_time }}
                                    </span>
                                    <span v-if="day.hasEnd" class="online-settings__marker online-settings__marker--end">
                                        Ende {{ form.order_end_time }}
                                    </span>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div v-if="form.order_start_mode === 'when_available'" class="online-settings__auto-start-note">
                        Automatischer Start: Sobald der Menüplan verfügbar und vollständig ist, beginnt das Bestellfenster.
                    </div>
                </section>

                <div class="online-settings__actions">
                    <button
                        type="button"
                        class="online-settings__save-button"
                        :disabled="isSaving"
                        @click="save">
                        {{ isSaving ? 'Speichert ...' : 'Einstellungen speichern' }}
                    </button>
                </div>
            </template>
        </div>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

const defaultSettings = () => ({
    order_start_mode: 'when_available',
    order_start_week_offset: 2,
    order_start_day_of_week: 0,
    order_start_time: '15:00',
    order_end_week_offset: 1,
    order_end_day_of_week: 5,
    order_end_time: '17:00',
})

const weekOptions = [
    { value: 2, label: 'Vorvorwoche' },
    { value: 1, label: 'Vorwoche' },
    { value: 0, label: 'Menüwoche' },
]

const dayOptions = [
    { value: 1, label: 'Montag', short: 'Mo' },
    { value: 2, label: 'Dienstag', short: 'Di' },
    { value: 3, label: 'Mittwoch', short: 'Mi' },
    { value: 4, label: 'Donnerstag', short: 'Do' },
    { value: 5, label: 'Freitag', short: 'Fr' },
    { value: 6, label: 'Samstag', short: 'Sa' },
    { value: 0, label: 'Sonntag', short: 'So' },
]

export default {
    data() {
        return {
            form: defaultSettings(),
            isSaving: false,
        }
    },

    computed: {
        ...mapState(useRestaurantStore, ['onlineSettings', 'canManageOnlineSettings']),
        weekOptions() {
            return weekOptions
        },
        dayOptions() {
            return dayOptions
        },
        startStatusLabel() {
            return this.form.order_start_mode === 'scheduled' ? this.startSummary : 'Automatisch'
        },
        endStatusLabel() {
            return this.endSummary
        },
        startSummary() {
            if (this.form.order_start_mode !== 'scheduled') {
                return 'Automatisch bei Verfügbarkeit'
            }

            return `${this.dayLabel(this.form.order_start_day_of_week)} ${this.form.order_start_time} ${this.weekPhrase(this.form.order_start_week_offset)}`
        },
        endSummary() {
            return `${this.dayLabel(this.form.order_end_day_of_week)} ${this.form.order_end_time} ${this.weekPhrase(this.form.order_end_week_offset)}`
        },
        previewText() {
            if (this.form.order_start_mode === 'scheduled') {
                return `${this.dayLabel(this.form.order_start_day_of_week)} ${this.form.order_start_time} ${this.weekPhrase(this.form.order_start_week_offset)} bis ${this.dayLabel(this.form.order_end_day_of_week)} ${this.form.order_end_time} ${this.weekPhrase(this.form.order_end_week_offset)}`
            }

            return `Sobald verfügbar und vollständig bis ${this.dayLabel(this.form.order_end_day_of_week)} ${this.form.order_end_time} ${this.weekPhrase(this.form.order_end_week_offset)}`
        },
        scheduleWeeks() {
            return weekOptions.map((week) => {
                return {
                    ...week,
                    days: dayOptions.map((day) => {
                        const hasStart = this.form.order_start_mode === 'scheduled'
                            && week.value === this.form.order_start_week_offset
                            && day.value === this.form.order_start_day_of_week

                        const hasEnd = week.value === this.form.order_end_week_offset
                            && day.value === this.form.order_end_day_of_week

                        return {
                            ...day,
                            hasStart,
                            hasEnd,
                            hasMarker: hasStart || hasEnd,
                        }
                    }),
                }
            })
        },
    },

    created() {
        this.syncForm(this.onlineSettings)
    },

    watch: {
        onlineSettings: {
            handler(newValue) {
                this.syncForm(newValue)
            },
            deep: true,
        },
    },

    methods: {
        syncForm(settings) {
            this.form = {
                ...defaultSettings(),
                ...(settings || {}),
            }
        },
        setStartMode(mode) {
            this.form.order_start_mode = mode
        },
        dayLabel(day) {
            return dayOptions.find((entry) => entry.value === Number(day))?.label || 'Unbekannter Tag'
        },
        weekPhrase(weekOffset) {
            return {
                2: 'der Vorvorwoche',
                1: 'vor der Menüwoche',
                0: 'der Menüwoche',
            }[Number(weekOffset)] || 'der Menüwoche'
        },
        applyExamplePreset() {
            this.form = {
                order_start_mode: 'scheduled',
                order_start_week_offset: 2,
                order_start_day_of_week: 0,
                order_start_time: '15:00',
                order_end_week_offset: 1,
                order_end_day_of_week: 5,
                order_end_time: '17:00',
            }
        },
        async save() {
            if (!this.canManageOnlineSettings || this.isSaving) {
                return
            }

            this.isSaving = true

            try {
                const saved = await useRestaurantStore().updateOnlineSettings({ ...this.form })
                if (saved) {
                    this.syncForm(saved)
                }
            } finally {
                this.isSaving = false
            }
        },
    },
}
</script>

<style scoped>
.online-settings {
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 28px;
    background:
        radial-gradient(circle at top right, rgba(250, 204, 21, 0.22), transparent 28%),
        linear-gradient(160deg, rgba(255, 251, 235, 0.98), rgba(255, 237, 213, 0.92));
    padding: 24px;
    color: rgb(67, 20, 7);
}

.online-settings__intro,
.online-settings__preview-header,
.online-settings__panel-header,
.online-settings__actions {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}

.online-settings__eyebrow,
.online-settings__panel-eyebrow {
    margin: 0 0 6px;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgb(180, 83, 9);
}

.online-settings__title {
    margin: 0 0 10px;
    font-size: clamp(1.45rem, 2vw, 1.95rem);
    line-height: 1.08;
}

.online-settings__copy,
.online-settings__preview-copy,
.online-settings__mode-card span:last-child,
.online-settings__message,
.online-settings__calendar-note {
    color: rgba(67, 20, 7, 0.84);
}

.online-settings__calendar-note {
    margin-top: 16px;
    border-left: 4px solid rgba(217, 119, 6, 0.45);
    padding-left: 12px;
    font-size: 0.92rem;
}

.online-settings__intro-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.online-settings__badge,
.online-settings__panel-status {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    background: rgba(180, 83, 9, 0.12);
    padding: 6px 10px;
    font-size: 0.78rem;
    font-weight: 700;
    color: rgb(154, 52, 18);
}

.online-settings__badge--muted {
    background: rgba(67, 20, 7, 0.08);
    color: rgba(67, 20, 7, 0.78);
}

.online-settings__mode-grid,
.online-settings__configuration-grid,
.online-settings__week-strip {
    display: grid;
    gap: 16px;
    margin-top: 22px;
}

.online-settings__mode-grid,
.online-settings__configuration-grid {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

.online-settings__mode-card,
.online-settings__panel,
.online-settings__week-card,
.online-settings__preview {
    border: 1px solid rgba(180, 83, 9, 0.14);
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.72);
}

.online-settings__mode-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 18px;
    text-align: left;
    cursor: pointer;
    transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
}

.online-settings__mode-card:hover,
.online-settings__mode-card.is-active {
    transform: translateY(-1px);
    border-color: rgba(180, 83, 9, 0.4);
    box-shadow: 0 16px 32px rgba(120, 53, 15, 0.12);
}

.online-settings__mode-kicker {
    font-size: 0.78rem;
    font-weight: 700;
    color: rgb(180, 83, 9);
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.online-settings__panel,
.online-settings__preview {
    padding: 18px;
}

.online-settings__panel-title {
    margin: 0;
    font-size: 1rem;
}

.online-settings__field-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-top: 16px;
}

.online-settings__field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    font-size: 0.9rem;
    font-weight: 600;
}

.online-settings__field select,
.online-settings__field input,
.online-settings__ghost-button,
.online-settings__save-button {
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.28);
    padding: 11px 12px;
    font: inherit;
}

.online-settings__field select,
.online-settings__field input {
    background: rgba(255, 255, 255, 0.92);
    color: rgb(15, 23, 42);
}

.online-settings__message {
    margin-top: 16px;
    border-radius: 16px;
    background: rgba(255, 247, 237, 0.8);
    padding: 14px 16px;
}

.online-settings__message--warning {
    border: 1px dashed rgba(217, 119, 6, 0.38);
}

.online-settings__preview-copy {
    margin-top: 16px;
    font-size: 1rem;
    font-weight: 700;
}

.online-settings__week-strip {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.online-settings__week-card {
    padding: 16px;
}

.online-settings__week-title {
    margin-bottom: 12px;
    font-size: 0.92rem;
    font-weight: 700;
    color: rgb(120, 53, 15);
}

.online-settings__day-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.online-settings__day-chip {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 58px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.82);
    padding: 10px 12px;
}

.online-settings__day-chip.is-highlighted {
    outline: 2px solid rgba(180, 83, 9, 0.26);
}

.online-settings__day-chip.has-start {
    background: rgba(219, 234, 254, 0.9);
}

.online-settings__day-chip.has-end {
    background: rgba(220, 252, 231, 0.9);
}

.online-settings__day-label {
    font-size: 0.82rem;
    font-weight: 700;
}

.online-settings__marker {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    border-radius: 999px;
    padding: 4px 8px;
    font-size: 0.72rem;
    font-weight: 700;
}

.online-settings__marker--start {
    background: rgba(59, 130, 246, 0.16);
    color: rgb(30, 64, 175);
}

.online-settings__marker--end {
    background: rgba(34, 197, 94, 0.16);
    color: rgb(21, 128, 61);
}

.online-settings__auto-start-note {
    margin-top: 14px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.84);
    padding: 12px 14px;
    font-size: 0.9rem;
    color: rgba(67, 20, 7, 0.82);
}

.online-settings__ghost-button,
.online-settings__save-button {
    background: rgba(255, 255, 255, 0.82);
    cursor: pointer;
}

.online-settings__ghost-button {
    color: rgb(120, 53, 15);
}

.online-settings__save-button {
    min-width: 220px;
    border-color: rgba(180, 83, 9, 0.5);
    background: linear-gradient(135deg, rgb(217, 119, 6), rgb(234, 88, 12));
    color: white;
    font-weight: 700;
}

.online-settings__save-button:disabled {
    cursor: wait;
    opacity: 0.72;
}

@media (max-width: 960px) {
    .online-settings {
        padding: 18px;
    }

    .online-settings__intro,
    .online-settings__preview-header,
    .online-settings__panel-header,
    .online-settings__actions {
        flex-direction: column;
    }

    .online-settings__actions {
        align-items: stretch;
    }

    .online-settings__save-button {
        width: 100%;
    }
}
</style>
