<template>
    <v-col cols="12">
        <div class="online-settings">
            <div class="online-settings__intro">
                <div>
                    <p class="online-settings__eyebrow">Online-Bestellung</p>
                    <h2 class="online-settings__title">Bestellzeitraum f&uuml;r Men&uuml;pl&auml;ne</h2>
                    <p class="online-settings__copy">
                        Legen Sie fest, ab wann ein Men&uuml;plan online bestellbar ist, bis wann Bestellungen m&ouml;glich bleiben
                        und wie lange der Plan sichtbar bleibt.
                    </p>
                </div>

                <div class="online-settings__intro-badges">
                    <span class="online-settings__badge">Live-Vorschau</span>
                    <span class="online-settings__badge online-settings__badge--muted">f&uuml;r alle User der Schule</span>
                </div>
            </div>

            <div v-if="!canManageOnlineSettings" class="online-settings__message online-settings__message--warning">
                Diese Einstellungen sind erst nach der aktuellen Migration verf&uuml;gbar.
            </div>

            <template v-else>
                <div class="online-settings__configuration-grid">
                    <section class="online-settings__panel" data-testid="order-panel">
                        <div class="online-settings__panel-header">
                            <div>
                                <p class="online-settings__panel-eyebrow">Bestellung</p>
                                <h3 class="online-settings__panel-title">Bestellbar</h3>
                            </div>
                            <span class="online-settings__panel-status">{{ orderPanelStatusLabel }}</span>
                        </div>

                        <div class="online-settings__panel-block">
                            <div class="online-settings__panel-subhead">
                                <strong>Ab wann bestellbar?</strong>
                                <span>{{ startStatusLabel }}</span>
                            </div>

                            <div class="online-settings__field online-settings__field--stacked">
                                <span>Bestellstart</span>
                                <div class="online-settings__mode-toggle">
                                    <button
                                        type="button"
                                        class="online-settings__mode-option"
                                        :class="{ 'is-active': form.order_start_mode === 'when_available' }"
                                        @click="setStartMode('when_available')">
                                        Sobald verf&uuml;gbar
                                    </button>
                                    <button
                                        type="button"
                                        class="online-settings__mode-option"
                                        :class="{ 'is-active': form.order_start_mode === 'scheduled' }"
                                        @click="setStartMode('scheduled')">
                                        Fester Tag
                                    </button>
                                    <button
                                        v-if="form.order_start_mode === 'scheduled'"
                                        type="button"
                                        class="online-settings__timeline-action"
                                        data-testid="open-start-day-dialog"
                                        @click="openStartSelectionDialog">
                                        Starttag festlegen
                                    </button>
                                </div>
                            </div>

                        </div>

                        <div class="online-settings__panel-block online-settings__panel-block--divided">
                            <div class="online-settings__panel-subhead">
                                <strong>Bis wann bestellbar?</strong>
                                <span>{{ endStatusLabel }}</span>
                            </div>

                            <div class="online-settings__field online-settings__field--stacked">
                                <span>Bestellende</span>
                                <div class="online-settings__mode-toggle">
                                    <button
                                        type="button"
                                        class="online-settings__mode-option is-active"
                                        disabled>
                                        Fixer Tag
                                    </button>
                                    <button
                                        type="button"
                                        class="online-settings__timeline-action"
                                        data-testid="open-end-day-dialog"
                                        @click="openEndSelectionDialog">
                                        Endtag festlegen
                                    </button>
                                </div>
                            </div>

                        </div>
                    </section>

                    <section class="online-settings__panel" data-testid="visibility-panel">
                        <div class="online-settings__panel-header">
                            <div>
                                <p class="online-settings__panel-eyebrow">Sichtbarkeit</p>
                                <h3 class="online-settings__panel-title">Sichtbar</h3>
                            </div>
                            <span class="online-settings__panel-status">{{ visibilityPanelStatusLabel }}</span>
                        </div>

                        <div class="online-settings__panel-block">
                            <div class="online-settings__panel-subhead">
                                <strong>Ab wann sichtbar?</strong>
                                <span>{{ visibilityStartStatusLabel }}</span>
                            </div>

                            <div class="online-settings__field online-settings__field--stacked">
                                <span>Sichtbarkeitsstart</span>
                                <div class="online-settings__mode-toggle">
                                    <button
                                        type="button"
                                        class="online-settings__mode-option"
                                        :class="{ 'is-active': form.visibility_start_mode === 'when_available' }"
                                        @click="setVisibilityStartMode('when_available')">
                                        Sobald verf&uuml;gbar
                                    </button>
                                    <button
                                        type="button"
                                        class="online-settings__mode-option"
                                        :class="{ 'is-active': form.visibility_start_mode === 'when_orderable' }"
                                        @click="setVisibilityStartMode('when_orderable')">
                                        Sobald bestellbar
                                    </button>
                                    <button
                                        type="button"
                                        class="online-settings__mode-option"
                                        :class="{ 'is-active': form.visibility_start_mode === 'scheduled' }"
                                        @click="setVisibilityStartMode('scheduled')">
                                        Fester Tag
                                    </button>
                                    <button
                                        v-if="form.visibility_start_mode === 'scheduled'"
                                        type="button"
                                        class="online-settings__timeline-action"
                                        data-testid="open-visibility-start-day-dialog"
                                        @click="openVisibilitySelectionDialog">
                                        Starttag festlegen
                                    </button>
                                </div>
                            </div>

                        </div>

                        <div class="online-settings__panel-block online-settings__panel-block--divided">
                            <div class="online-settings__panel-subhead">
                                <strong>Sichbarkeitsende</strong>
                                <span>{{ visibilityStatusLabel }}</span>
                            </div>

                            <div class="online-settings__field online-settings__field--stacked">
                                <span>Sichbarkeitsende</span>
                                <div class="online-settings__choice-row">
                                    <button
                                        v-for="option in visibilityOptions"
                                        :key="`visibility-${option.value}`"
                                        type="button"
                                        class="online-settings__choice-chip"
                                        :class="{ 'is-active': form.visibility_end_mode === option.value }"
                                        @click="form.visibility_end_mode = option.value">
                                        {{ option.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="online-settings__preview">
                    <div class="online-settings__timeline">
                        <div
                            v-for="week in scheduleWeeks"
                            :key="`week-${week.value}`"
                            class="online-settings__timeline-week-card">
                            <div class="online-settings__timeline-week-label">{{ week.label }}</div>

                            <div class="online-settings__timeline-week-days">
                                <div
                                    v-for="day in week.days"
                                    :key="`${week.value}-${day.value}`"
                                    class="online-settings__day-chip"
                                    :class="{
                                        'has-visible-day': day.hasVisibleDay,
                                        'has-orderable-day': day.hasOrderableDay,
                                        'has-visibility-start': day.hasVisibilityStart,
                                        'has-start': day.hasStart,
                                        'has-end': day.hasEnd,
                                        'is-highlighted': day.hasMarker,
                                    }">
                                    <span v-if="day.hasVisibleDay || day.hasOrderableDay" class="online-settings__icon-row">
                                        <span
                                            v-if="day.hasVisibleDay"
                                            class="online-settings__visibility-icon"
                                            aria-label="Sichtbar">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M12 5C6.5 5 2.06 8.36 1 12c1.06 3.64 5.5 7 11 7s9.94-3.36 11-7c-1.06-3.64-5.5-7-11-7Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2.2a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Z" />
                                            </svg>
                                        </span>
                                        <span
                                            v-if="day.hasOrderableDay"
                                            class="online-settings__orderable-icon"
                                            aria-label="Bestellbar">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2Zm10 0c-1.1 0-1.99.9-1.99 2S15.9 22 17 22s2-.9 2-2-.9-2-2-2ZM7.17 14h9.95c.75 0 1.41-.41 1.75-1.03L22.44 6.5A1 1 0 0 0 21.56 5H6.21l-.57-2.43A1 1 0 0 0 4.67 2H2v2h1.88l2.4 10.1A2 2 0 0 0 8.22 16H20v-2H8.22l-.25-1Z" />
                                            </svg>
                                        </span>
                                    </span>
                                    <span class="online-settings__day-label">{{ day.short }}</span>
                                    <span v-if="day.hasStart || day.hasEnd" class="online-settings__marker-stack">
                                        <span v-if="day.hasStart" class="online-settings__marker online-settings__marker--start">
                                            Start {{ form.order_start_time }}
                                        </span>
                                        <span v-if="day.hasEnd" class="online-settings__marker online-settings__marker--end">
                                            Ende {{ form.order_end_time }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="online-settings__preview-control-card" data-testid="plan-status-card">
                        <div class="online-settings__preview-control-header">
                            <div>
                                <strong>Men&uuml;pl&auml;ne im gezeigten Zeitraum</strong>
                                <div class="online-settings__preview-range">{{ previewRangeLabel }}</div>
                            </div>
                            <span class="online-settings__panel-status">{{ menuPlansInPreviewRange.length }} {{ menuPlansInPreviewRange.length === 1 ? 'Plan' : 'Pläne' }}</span>
                        </div>

                        <div v-if="menuPlansInPreviewRange.length" class="online-settings__status-list">
                            <article
                                v-for="plan in menuPlansInPreviewRange"
                                :key="`status-plan-${plan.id}`"
                                class="online-settings__status-card"
                                :data-testid="`plan-status-${plan.id}`">
                                <div class="online-settings__status-card-head">
                                    <div>
                                        <div class="online-settings__status-title">{{ planRangeLabel(plan) }}</div>
                                        <div class="online-settings__status-subtitle">
                                            Men&uuml;woche {{ formatRangeDate(plan.start_date) }} bis {{ formatRangeDate(plan.end_date) }}
                                        </div>
                                    </div>
                                    <span
                                        v-if="plan.is_available !== true"
                                        class="online-settings__state-pill online-settings__state-pill--muted">
                                        Nicht freigegeben
                                    </span>
                                </div>

                                <div class="online-settings__status-grid">
                                    <div class="online-settings__status-group">
                                        <span class="online-settings__status-label">Sichtbarkeit</span>
                                        <span
                                            class="online-settings__state-pill"
                                            :class="planVisibilityState(plan).className">
                                            {{ planVisibilityState(plan).label }}
                                        </span>
                                    </div>

                                    <div class="online-settings__status-group">
                                        <span class="online-settings__status-label">Bestellbarkeit</span>
                                        <span
                                            class="online-settings__state-pill"
                                            :class="planOrderState(plan).className">
                                            {{ planOrderState(plan).label }}
                                        </span>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div
                            v-else
                            class="online-settings__message online-settings__message--compact"
                            data-testid="plan-status-empty">
                            Im gezeigten Zeitraum liegt kein Men&uuml;plan.
                        </div>
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

                <div
                    v-if="startSelectionDialog"
                    class="online-settings__dialog-shell"
                    data-testid="start-day-dialog">
                    <div class="online-settings__dialog-card">
                        <div class="online-settings__dialog-header">
                            <div>
                                <p class="online-settings__panel-eyebrow">Bestellstart</p>
                                <h3 class="online-settings__panel-title">Starttag festlegen</h3>
                            </div>
                        </div>

                        <div class="online-settings__field-grid online-settings__field-grid--timeline">
                            <div class="online-settings__field">
                                <span>Woche</span>
                                <div class="online-settings__choice-row">
                                    <button
                                        v-for="week in weekOptions"
                                        :key="`dialog-start-week-${week.value}`"
                                        type="button"
                                        class="online-settings__choice-chip"
                                        :class="{ 'is-active': startDialogDraft.week_offset === week.value }"
                                        @click="startDialogDraft.week_offset = week.value">
                                        {{ week.label }}
                                    </button>
                                </div>
                            </div>

                            <div class="online-settings__field-grid online-settings__field-grid--daytime">
                                <div class="online-settings__field">
                                    <span>Tag</span>
                                    <div class="online-settings__choice-row">
                                        <button
                                            v-for="day in dayOptions"
                                            :key="`dialog-start-day-${day.value}`"
                                            type="button"
                                            class="online-settings__choice-chip"
                                            :class="{ 'is-active': startDialogDraft.day_of_week === day.value }"
                                            @click="startDialogDraft.day_of_week = day.value">
                                            {{ day.short }}
                                        </button>
                                    </div>
                                </div>

                                <label class="online-settings__field">
                                    <span>Uhrzeit</span>
                                    <input v-model="startDialogDraft.time" type="time">
                                </label>
                            </div>
                        </div>

                        <div class="online-settings__dialog-actions">
                            <button
                                type="button"
                                class="online-settings__ghost-button"
                                @click="closeStartSelectionDialog">
                                Abbrechen
                            </button>
                            <button
                                type="button"
                                class="online-settings__save-button online-settings__save-button--compact"
                                data-testid="apply-start-day-dialog"
                                @click="applyStartSelectionDialog">
                                Übernehmen
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="endSelectionDialog"
                    class="online-settings__dialog-shell"
                    data-testid="end-day-dialog">
                    <div class="online-settings__dialog-card">
                        <div class="online-settings__dialog-header">
                            <div>
                                <p class="online-settings__panel-eyebrow">Bestellende</p>
                                <h3 class="online-settings__panel-title">Endtag festlegen</h3>
                            </div>
                        </div>

                        <div class="online-settings__field-grid online-settings__field-grid--timeline">
                            <div class="online-settings__field">
                                <span>Woche</span>
                                <div class="online-settings__choice-row">
                                    <button
                                        v-for="week in weekOptions"
                                        :key="`dialog-end-week-${week.value}`"
                                        type="button"
                                        class="online-settings__choice-chip"
                                        :class="{ 'is-active': endDialogDraft.week_offset === week.value }"
                                        @click="endDialogDraft.week_offset = week.value">
                                        {{ week.label }}
                                    </button>
                                </div>
                            </div>

                            <div class="online-settings__field-grid online-settings__field-grid--daytime">
                                <div class="online-settings__field">
                                    <span>Tag</span>
                                    <div class="online-settings__choice-row">
                                        <button
                                            v-for="day in dayOptions"
                                            :key="`dialog-end-day-${day.value}`"
                                            type="button"
                                            class="online-settings__choice-chip"
                                            :class="{ 'is-active': endDialogDraft.day_of_week === day.value }"
                                            @click="endDialogDraft.day_of_week = day.value">
                                            {{ day.short }}
                                        </button>
                                    </div>
                                </div>

                                <label class="online-settings__field">
                                    <span>Uhrzeit</span>
                                    <input v-model="endDialogDraft.time" type="time">
                                </label>
                            </div>
                        </div>

                        <div class="online-settings__dialog-actions">
                            <button
                                type="button"
                                class="online-settings__ghost-button"
                                @click="closeEndSelectionDialog">
                                Abbrechen
                            </button>
                            <button
                                type="button"
                                class="online-settings__save-button online-settings__save-button--compact"
                                data-testid="apply-end-day-dialog"
                                @click="applyEndSelectionDialog">
                                Übernehmen
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="visibilitySelectionDialog"
                    class="online-settings__dialog-shell"
                    data-testid="visibility-start-day-dialog">
                    <div class="online-settings__dialog-card">
                        <div class="online-settings__dialog-header">
                            <div>
                                <p class="online-settings__panel-eyebrow">Sichtbar von</p>
                                <h3 class="online-settings__panel-title">Starttag festlegen</h3>
                            </div>
                        </div>

                        <div class="online-settings__field-grid online-settings__field-grid--timeline">
                            <div class="online-settings__field">
                                <span>Woche</span>
                                <div class="online-settings__choice-row">
                                    <button
                                        v-for="week in weekOptions"
                                        :key="`dialog-visibility-week-${week.value}`"
                                        type="button"
                                        class="online-settings__choice-chip"
                                        :class="{
                                            'is-active': visibilityDialogDraft.week_offset === week.value,
                                            'is-disabled': isVisibilityDialogWeekDisabled(week.value),
                                        }"
                                        :disabled="isVisibilityDialogWeekDisabled(week.value)"
                                        @click="visibilityDialogDraft.week_offset = week.value">
                                        {{ week.label }}
                                    </button>
                                </div>
                            </div>

                            <div class="online-settings__field-grid online-settings__field-grid--daytime">
                                <div class="online-settings__field">
                                    <span>Tag</span>
                                    <div class="online-settings__choice-row">
                                        <button
                                            v-for="day in dayOptions"
                                            :key="`dialog-visibility-day-${day.value}`"
                                            type="button"
                                            class="online-settings__choice-chip"
                                            :class="{
                                                'is-active': visibilityDialogDraft.day_of_week === day.value,
                                                'is-disabled': isVisibilityDialogDayDisabled(day.value),
                                            }"
                                            :disabled="isVisibilityDialogDayDisabled(day.value)"
                                            @click="visibilityDialogDraft.day_of_week = day.value">
                                            {{ day.short }}
                                        </button>
                                    </div>
                                </div>

                                <label class="online-settings__field">
                                    <span>Uhrzeit</span>
                                    <input v-model="visibilityDialogDraft.time" type="time">
                                </label>
                            </div>
                        </div>

                        <p
                            v-if="visibilitySelectionExceedsOrderStart"
                            class="online-settings__message online-settings__message--warning online-settings__message--compact"
                            data-testid="visibility-start-order-warning">
                            Der Starttag der Sichtbarkeit darf nicht nach dem Bestellstart liegen.
                        </p>

                        <div class="online-settings__dialog-actions">
                            <button
                                type="button"
                                class="online-settings__ghost-button"
                                @click="closeVisibilitySelectionDialog">
                                Abbrechen
                            </button>
                            <button
                                v-if="!visibilitySelectionExceedsOrderStart"
                                type="button"
                                class="online-settings__save-button online-settings__save-button--compact"
                                data-testid="apply-visibility-start-day-dialog"
                                @click="applyVisibilitySelectionDialog">
                                Übernehmen
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import { useMenuPlanStore } from '@/stores/admin/restaurant/MenuPlanStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

const defaultSettings = () => ({
    visibility_start_mode: 'when_available',
    visibility_start_week_offset: 2,
    visibility_start_day_of_week: 0,
    visibility_start_time: '15:00',
    order_start_mode: 'when_available',
    order_start_week_offset: 2,
    order_start_day_of_week: 0,
    order_start_time: '15:00',
    order_end_week_offset: 1,
    order_end_day_of_week: 5,
    order_end_time: '17:00',
    visibility_end_mode: 'plan_end',
})

const weekOptions = [
    { value: 2, label: 'Vorvorwoche' },
    { value: 1, label: 'Vorwoche' },
    { value: 0, label: 'Men\u00fcwoche' },
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

const visibilityOptions = [
    { value: 'plan_end', label: 'Bis zum letzten Tag des Men\u00fcplans' },
    { value: 'week_end', label: 'Bis zum Ende der Woche' },
]

export default {
    data() {
        return {
            form: defaultSettings(),
            isSaving: false,
            previewNow: new Date(),
            startSelectionDialog: false,
            endSelectionDialog: false,
            visibilitySelectionDialog: false,
            startDialogDraft: {
                week_offset: 2,
                day_of_week: 0,
                time: '15:00',
            },
            endDialogDraft: {
                week_offset: 1,
                day_of_week: 5,
                time: '17:00',
            },
            visibilityDialogDraft: {
                week_offset: 2,
                day_of_week: 0,
                time: '15:00',
            },
        }
    },

    computed: {
        ...mapState(useRestaurantStore, ['onlineSettings', 'canManageOnlineSettings']),
        availableMenuPlans() {
            return useMenuPlanStore().plans.filter((plan) => plan.is_available === true)
        },
        weekOptions() {
            return weekOptions
        },
        dayOptions() {
            return dayOptions
        },
        visibilityOptions() {
            return visibilityOptions
        },
        menuPlans() {
            return useMenuPlanStore().plans
        },
        visibilityStartStatusLabel() {
            if (this.form.visibility_start_mode === 'scheduled') {
                return this.visibilityStartSummary
            }

            if (this.form.visibility_start_mode === 'when_orderable') {
                return 'Sobald bestellbar'
            }

            return 'Sobald verf\u00fcgbar'
        },
        startStatusLabel() {
            return this.form.order_start_mode === 'scheduled' ? this.startSummary : 'Automatisch'
        },
        orderPanelStatusLabel() {
            return `${this.startStatusLabel} bis ${this.endStatusLabel}`
        },
        endStatusLabel() {
            return this.endSummary
        },
        visibilityStatusLabel() {
            return this.form.visibility_end_mode === 'week_end'
                ? 'Bis zum Ende der Woche'
                : 'Bis zum letzten Tag des Men\u00fcplans'
        },
        visibilityPanelStatusLabel() {
            return this.form.visibility_end_mode === 'week_end'
                ? `${this.visibilityStartStatusLabel} bis zum Ende der Woche`
                : `${this.visibilityStartStatusLabel} bis zum letzten Tag des Men\u00fcplans`
        },
        visibilitySelectionExceedsOrderStart() {
            if (this.form.visibility_start_mode !== 'scheduled') {
                return false
            }

            return this.isScheduledSelectionAfterOrderStart(
                this.visibilityDialogDraft.week_offset,
                this.visibilityDialogDraft.day_of_week,
                this.visibilityDialogDraft.time
            )
        },
        startSummary() {
            if (this.form.order_start_mode !== 'scheduled') {
                return 'Automatisch bei Verf\u00fcgbarkeit'
            }

            return `${this.dayLabel(this.form.order_start_day_of_week)} ${this.form.order_start_time} ${this.weekPhrase(this.form.order_start_week_offset)}`
        },
        visibilityStartSummary() {
            if (this.form.visibility_start_mode === 'when_orderable') {
                return 'Sobald bestellbar'
            }

            if (this.form.visibility_start_mode !== 'scheduled') {
                return 'Sobald verf\u00fcgbar'
            }

            return `${this.dayLabel(this.form.visibility_start_day_of_week)} ${this.form.visibility_start_time} ${this.weekPhrase(this.form.visibility_start_week_offset)}`
        },
        endSummary() {
            return `${this.dayLabel(this.form.order_end_day_of_week)} ${this.form.order_end_time} ${this.weekPhrase(this.form.order_end_week_offset)}`
        },
        previewText() {
            const visibilityStart = this.form.visibility_start_mode === 'scheduled'
                ? `Sichtbar ab ${this.dayLabel(this.form.visibility_start_day_of_week)} ${this.form.visibility_start_time} ${this.weekPhrase(this.form.visibility_start_week_offset)}.`
                : this.form.visibility_start_mode === 'when_orderable'
                    ? 'Sichtbar sobald bestellbar.'
                    : 'Sichtbar sobald verf\u00fcgbar.'
            const orderStart = this.form.order_start_mode === 'scheduled'
                ? `${this.dayLabel(this.form.order_start_day_of_week)} ${this.form.order_start_time} ${this.weekPhrase(this.form.order_start_week_offset)}`
                : 'Sobald verf\u00fcgbar und vollst\u00e4ndig'

            return `${visibilityStart} Bestellbar ab ${orderStart} bis ${this.dayLabel(this.form.order_end_day_of_week)} ${this.form.order_end_time} ${this.weekPhrase(this.form.order_end_week_offset)}. Sichtbar ${this.visibilityPhrase()}.`
        },
        scheduleWeeks() {
            const previewPlan = this.previewTimelinePlan()
            const visibilityStart = this.visibilityStartDateTime(previewPlan)
            const visibilityEnd = this.visibilityEndDateTime(previewPlan)
            const orderStart = this.orderStartDateTime(previewPlan)
            const orderEnd = this.orderEndDateTime(previewPlan)

            return weekOptions.map((week) => {
                return {
                    ...week,
                    days: dayOptions.map((day) => {
                        const dayIso = this.previewDayIso(previewPlan, week.value, day.value)
                        const dayStart = this.isoAtTime(dayIso)
                        const dayEnd = this.isoAtTime(dayIso, '23:59', true)
                        const hasVisibleDay = visibilityStart <= dayEnd && dayStart <= visibilityEnd
                        const hasOrderableDay = orderStart <= dayEnd && dayStart <= orderEnd
                        const hasVisibilityStart = this.form.visibility_start_mode === 'scheduled'
                            && week.value === this.form.visibility_start_week_offset
                            && day.value === this.form.visibility_start_day_of_week
                        const hasStart = this.form.order_start_mode === 'scheduled'
                            && week.value === this.form.order_start_week_offset
                            && day.value === this.form.order_start_day_of_week

                        const hasEnd = week.value === this.form.order_end_week_offset
                            && day.value === this.form.order_end_day_of_week

                        return {
                            ...day,
                            weekValue: week.value,
                            hasVisibleDay,
                            hasOrderableDay,
                            hasVisibilityStart,
                            hasStart,
                            hasEnd,
                            hasMarker: hasVisibilityStart || hasStart || hasEnd,
                        }
                    }),
                }
            })
        },
        previewRange() {
            const previewPlan = this.previewTimelinePlan()

            return {
                start: this.previewDayIso(previewPlan, 2, 1),
                end: this.previewDayIso(previewPlan, 0, 0),
            }
        },
        previewRangeLabel() {
            return `${this.formatRangeDate(this.previewRange.start)} - ${this.formatRangeDate(this.previewRange.end)}`
        },
        menuPlansInPreviewRange() {
            return this.menuPlans
                .filter((plan) => plan.start_date <= this.previewRange.end && plan.end_date >= this.previewRange.start)
                .sort((left, right) => String(left.start_date).localeCompare(String(right.start_date)))
        },
        currentVisiblePlan() {
            return this.pickPrimaryPlan(this.availableMenuPlans.filter((plan) => this.isPlanVisibleNow(plan)))
        },
        currentOrderablePlan() {
            return this.pickPrimaryPlan(this.availableMenuPlans.filter((plan) => this.isPlanOrderableNow(plan)))
        },
        currentVisiblePlanLabel() {
            if (! this.currentVisiblePlan) {
                return 'Aktuell wird kein verfügbarer Menüplan aus den Einstellungen abgeleitet.'
            }

            return this.planRangeLabel(this.currentVisiblePlan)
        },
        currentOrderablePlanLabel() {
            if (! this.currentOrderablePlan) {
                return 'Aktuell ist kein verfügbarer Menüplan bestellbar.'
            }

            return this.planRangeLabel(this.currentOrderablePlan)
        },
    },

    created() {
        this.syncForm(this.onlineSettings)

        const menuPlanStore = useMenuPlanStore()

        if (! menuPlanStore.isLoaded) {
            menuPlanStore.load()
        }
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
            this.ensureVisibilityStartDoesNotExceedOrderStart()
            this.resetStartDialogDraft()
            this.resetEndDialogDraft()
            this.resetVisibilityDialogDraft()
        },
        setStartMode(mode) {
            this.form.order_start_mode = mode
            this.ensureVisibilityStartDoesNotExceedOrderStart()
        },
        resetStartDialogDraft() {
            this.startDialogDraft = {
                week_offset: this.form.order_start_week_offset,
                day_of_week: this.form.order_start_day_of_week,
                time: this.form.order_start_time,
            }
        },
        openStartSelectionDialog() {
            this.resetStartDialogDraft()
            this.startSelectionDialog = true
        },
        closeStartSelectionDialog() {
            this.startSelectionDialog = false
            this.resetStartDialogDraft()
        },
        applyStartSelectionDialog() {
            this.form.order_start_week_offset = this.startDialogDraft.week_offset
            this.form.order_start_day_of_week = this.startDialogDraft.day_of_week
            this.form.order_start_time = this.startDialogDraft.time
            this.ensureVisibilityStartDoesNotExceedOrderStart()
            this.startSelectionDialog = false
        },
        resetEndDialogDraft() {
            this.endDialogDraft = {
                week_offset: this.form.order_end_week_offset,
                day_of_week: this.form.order_end_day_of_week,
                time: this.form.order_end_time,
            }
        },
        openEndSelectionDialog() {
            this.resetEndDialogDraft()
            this.endSelectionDialog = true
        },
        closeEndSelectionDialog() {
            this.endSelectionDialog = false
            this.resetEndDialogDraft()
        },
        applyEndSelectionDialog() {
            this.form.order_end_week_offset = this.endDialogDraft.week_offset
            this.form.order_end_day_of_week = this.endDialogDraft.day_of_week
            this.form.order_end_time = this.endDialogDraft.time
            this.endSelectionDialog = false
        },
        resetVisibilityDialogDraft() {
            this.visibilityDialogDraft = {
                week_offset: this.form.visibility_start_week_offset,
                day_of_week: this.form.visibility_start_day_of_week,
                time: this.form.visibility_start_time,
            }
        },
        openVisibilitySelectionDialog() {
            this.resetVisibilityDialogDraft()
            this.visibilitySelectionDialog = true
        },
        closeVisibilitySelectionDialog() {
            this.visibilitySelectionDialog = false
            this.resetVisibilityDialogDraft()
        },
        applyVisibilitySelectionDialog() {
            if (this.visibilitySelectionExceedsOrderStart) {
                return
            }

            this.form.visibility_start_week_offset = this.visibilityDialogDraft.week_offset
            this.form.visibility_start_day_of_week = this.visibilityDialogDraft.day_of_week
            this.form.visibility_start_time = this.visibilityDialogDraft.time
            this.visibilitySelectionDialog = false
        },
        setVisibilityStartMode(mode) {
            this.form.visibility_start_mode = mode
            this.ensureVisibilityStartDoesNotExceedOrderStart()
        },
        dayLabel(day) {
            return dayOptions.find((entry) => entry.value === Number(day))?.label || 'Unbekannter Tag'
        },
        weekPhrase(weekOffset) {
            return {
                2: 'der Vorvorwoche',
                1: 'vor der Men\u00fcwoche',
                0: 'der Men\u00fcwoche',
            }[Number(weekOffset)] || 'der Men\u00fcwoche'
        },
        visibilityPhrase() {
            return this.form.visibility_end_mode === 'week_end'
                ? 'bis zum Ende der Woche'
                : 'bis zum letzten Tag des Men\u00fcplans'
        },
        previewNowDate() {
            return this.previewNow instanceof Date ? this.previewNow : new Date(this.previewNow)
        },
        toDate(isoString) {
            return new Date(`${isoString}T00:00:00`)
        },
        toIso(date) {
            return [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, '0'),
                String(date.getDate()).padStart(2, '0'),
            ].join('-')
        },
        addDaysIso(isoString, days) {
            const date = this.toDate(isoString)
            date.setDate(date.getDate() + days)

            return this.toIso(date)
        },
        startOfWeekIso(isoString) {
            const date = this.toDate(isoString)
            const dayIndex = date.getDay()
            const distanceToMonday = dayIndex === 0 ? -6 : 1 - dayIndex
            date.setDate(date.getDate() + distanceToMonday)

            return this.toIso(date)
        },
        endOfWeekIso(isoString) {
            return this.addDaysIso(this.startOfWeekIso(isoString), 6)
        },
        isoAtTime(isoString, timeString = '00:00', useEndOfDay = false) {
            const date = this.toDate(isoString)

            if (useEndOfDay) {
                date.setHours(23, 59, 59, 999)
                return date
            }

            const [hours, minutes] = String(timeString || '00:00').split(':').map((part) => parseInt(part, 10) || 0)
            date.setHours(hours, minutes, 0, 0)

            return date
        },
        dayOffsetFromMonday(dayOfWeek) {
            const normalized = Number(dayOfWeek)

            return normalized === 0 ? 6 : normalized - 1
        },
        previewTimelinePlan() {
            if (this.availableMenuPlans.length > 0) {
                return this.pickPrimaryPlan(this.availableMenuPlans) || this.availableMenuPlans[0]
            }

            const todayIso = this.toIso(this.previewNowDate())
            const menuWeekStartIso = this.startOfWeekIso(todayIso)

            return {
                start_date: menuWeekStartIso,
                end_date: this.addDaysIso(menuWeekStartIso, 4),
            }
        },
        previewDayIso(plan, weekOffset, dayOfWeek) {
            const menuWeekStartIso = this.startOfWeekIso(plan.start_date)

            return this.addDaysIso(menuWeekStartIso, this.dayOffsetFromMonday(dayOfWeek) - (Number(weekOffset) * 7))
        },
        scheduledSelectionPosition(weekOffset, dayOfWeek, timeString) {
            const [hours, minutes] = String(timeString || '00:00')
                .split(':')
                .map((value) => Number.parseInt(value, 10) || 0)

            return (this.dayOffsetFromMonday(dayOfWeek) * 1440)
                - (Number(weekOffset) * 7 * 1440)
                + (hours * 60)
                + minutes
        },
        isScheduledSelectionAfterOrderStart(weekOffset, dayOfWeek, timeString) {
            if (this.form.order_start_mode !== 'scheduled') {
                return false
            }

            return this.scheduledSelectionPosition(weekOffset, dayOfWeek, timeString)
                > this.scheduledSelectionPosition(
                    this.form.order_start_week_offset,
                    this.form.order_start_day_of_week,
                    this.form.order_start_time
                )
        },
        isVisibilityDialogWeekDisabled(weekOffset) {
            if (this.form.order_start_mode !== 'scheduled') {
                return false
            }

            return !dayOptions.some((day) => !this.isScheduledSelectionAfterOrderStart(
                weekOffset,
                day.value,
                this.visibilityDialogDraft.time
            ))
        },
        isVisibilityDialogDayDisabled(dayOfWeek) {
            if (this.form.order_start_mode !== 'scheduled') {
                return false
            }

            return this.isScheduledSelectionAfterOrderStart(
                this.visibilityDialogDraft.week_offset,
                dayOfWeek,
                this.visibilityDialogDraft.time
            )
        },
        ensureVisibilityStartDoesNotExceedOrderStart() {
            if (this.form.visibility_start_mode !== 'scheduled' || this.form.order_start_mode !== 'scheduled') {
                return
            }

            if (! this.isScheduledSelectionAfterOrderStart(
                this.form.visibility_start_week_offset,
                this.form.visibility_start_day_of_week,
                this.form.visibility_start_time
            )) {
                return
            }

            this.form.visibility_start_week_offset = this.form.order_start_week_offset
            this.form.visibility_start_day_of_week = this.form.order_start_day_of_week
            this.form.visibility_start_time = this.form.order_start_time
        },
        scheduledDateTime(plan, weekOffset, dayOfWeek, timeString) {
            const targetIso = this.previewDayIso(plan, weekOffset, dayOfWeek)

            return this.isoAtTime(targetIso, timeString)
        },
        visibilityEndDateTime(plan) {
            const visibilityEndIso = this.form.visibility_end_mode === 'week_end'
                ? this.endOfWeekIso(plan.end_date)
                : plan.end_date

            return this.isoAtTime(visibilityEndIso, '23:59', true)
        },
        visibilityStartDateTime(plan) {
            if (this.form.visibility_start_mode === 'scheduled') {
                return this.scheduledDateTime(plan, this.form.visibility_start_week_offset, this.form.visibility_start_day_of_week, this.form.visibility_start_time)
            }

            if (this.form.visibility_start_mode === 'when_orderable') {
                return this.orderStartDateTime(plan)
            }

            return new Date(0)
        },
        orderStartDateTime(plan) {
            return this.form.order_start_mode === 'scheduled'
                ? this.scheduledDateTime(plan, this.form.order_start_week_offset, this.form.order_start_day_of_week, this.form.order_start_time)
                : new Date(0)
        },
        orderEndDateTime(plan) {
            return this.scheduledDateTime(plan, this.form.order_end_week_offset, this.form.order_end_day_of_week, this.form.order_end_time)
        },
        isPlanVisibleNow(plan) {
            const now = this.previewNowDate()
            const visibilityStart = this.visibilityStartDateTime(plan)
            const visibilityEnd = this.visibilityEndDateTime(plan)

            return visibilityStart <= now && now <= visibilityEnd
        },
        isPlanOrderableNow(plan) {
            const now = this.previewNowDate()
            const orderStart = this.orderStartDateTime(plan)
            const orderEnd = this.orderEndDateTime(plan)

            return orderStart <= now && now <= orderEnd
        },
        statusState(isCurrent, isPast, currentLabel, futureLabel, pastLabel) {
            if (isCurrent) {
                return {
                    label: currentLabel,
                    className: 'online-settings__state-pill--active',
                }
            }

            if (isPast) {
                return {
                    label: pastLabel,
                    className: 'online-settings__state-pill--past',
                }
            }

            return {
                label: futureLabel,
                className: 'online-settings__state-pill--muted',
            }
        },
        planVisibilityState(plan) {
            if (plan.is_available !== true) {
                return {
                    label: 'Nicht sichtbar',
                    className: 'online-settings__state-pill--muted',
                }
            }

            const now = this.previewNowDate()
            const visibilityStart = this.visibilityStartDateTime(plan)
            const visibilityEnd = this.visibilityEndDateTime(plan)

            return this.statusState(
                visibilityStart <= now && now <= visibilityEnd,
                now > visibilityEnd,
                'Sichtbar',
                'Nicht sichtbar',
                'Nicht mehr sichtbar',
            )
        },
        planOrderState(plan) {
            if (plan.is_available !== true) {
                return {
                    label: 'Nicht bestellbar',
                    className: 'online-settings__state-pill--muted',
                }
            }

            const now = this.previewNowDate()
            const orderStart = this.orderStartDateTime(plan)
            const orderEnd = this.orderEndDateTime(plan)

            return this.statusState(
                orderStart <= now && now <= orderEnd,
                now > orderEnd,
                'Bestellbar',
                'Nicht bestellbar',
                'Nicht mehr bestellbar',
            )
        },
        pickPrimaryPlan(plans) {
            if (! Array.isArray(plans) || plans.length === 0) {
                return null
            }

            const todayIso = this.toIso(this.previewNowDate())
            const containingToday = [...plans]
                .filter((plan) => plan.start_date <= todayIso && plan.end_date >= todayIso)
                .sort((left, right) => String(right.start_date).localeCompare(String(left.start_date)))

            if (containingToday.length > 0) {
                return containingToday[0]
            }

            const upcoming = [...plans]
                .filter((plan) => plan.start_date > todayIso)
                .sort((left, right) => String(left.start_date).localeCompare(String(right.start_date)))

            if (upcoming.length > 0) {
                return upcoming[0]
            }

            return [...plans]
                .sort((left, right) => String(right.start_date).localeCompare(String(left.start_date)))[0]
        },
        formatRangeDate(isoString) {
            return this.toDate(isoString).toLocaleDateString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
        },
        planRangeLabel(plan) {
            return `${this.formatRangeDate(plan.start_date)} - ${this.formatRangeDate(plan.end_date)}`
        },
        applyExamplePreset() {
            this.form = {
                visibility_start_mode: 'scheduled',
                visibility_start_week_offset: 2,
                visibility_start_day_of_week: 0,
                visibility_start_time: '15:00',
                order_start_mode: 'scheduled',
                order_start_week_offset: 2,
                order_start_day_of_week: 0,
                order_start_time: '15:00',
                order_end_week_offset: 1,
                order_end_day_of_week: 5,
                order_end_time: '17:00',
                visibility_end_mode: 'plan_end',
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
.online-settings__message,
.online-settings__field--stacked span,
.online-settings__field--stacked select {
    color: rgba(67, 20, 7, 0.84);
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

.online-settings__configuration-grid,
.online-settings__timeline {
    display: grid;
    gap: 16px;
    margin-top: 22px;
}

.online-settings__configuration-grid {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

.online-settings__panel,
.online-settings__preview {
    border: 1px solid rgba(180, 83, 9, 0.14);
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.72);
}

.online-settings__panel,
.online-settings__preview {
    padding: 18px;
}

.online-settings__panel-block + .online-settings__panel-block {
    margin-top: 16px;
}

.online-settings__panel-block--divided {
    border-top: 1px solid rgba(180, 83, 9, 0.12);
    padding-top: 16px;
}

.online-settings__panel-subhead {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    font-size: 0.88rem;
    color: rgba(67, 20, 7, 0.76);
}

.online-settings__panel-subhead strong {
    color: rgb(120, 53, 15);
    font-size: 0.92rem;
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

.online-settings__field-grid--timeline {
    grid-template-columns: 1fr;
    align-items: start;
}

.online-settings__field-grid--daytime {
    grid-template-columns: minmax(320px, 1fr) minmax(140px, 0.45fr);
    align-items: start;
    margin-top: 0;
}

.online-settings__field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    font-size: 0.9rem;
    font-weight: 600;
}

.online-settings__field--stacked {
    margin-top: 16px;
}

.online-settings__mode-toggle {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 8px;
}

.online-settings__choice-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.online-settings__mode-option {
    border-radius: 999px;
    border: 1px solid rgba(180, 83, 9, 0.18);
    background: rgba(255, 255, 255, 0.92);
    padding: 10px 14px;
    color: rgb(120, 53, 15);
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.16s ease, border-color 0.16s ease, background-color 0.16s ease;
}

.online-settings__mode-option:hover {
    transform: translateY(-1px);
    border-color: rgba(180, 83, 9, 0.36);
}

.online-settings__mode-option.is-active {
    border-color: rgba(180, 83, 9, 0.44);
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(249, 115, 22, 0.16));
    color: rgb(154, 52, 18);
}

.online-settings__choice-chip {
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.24);
    background: rgba(255, 255, 255, 0.92);
    padding: 9px 12px;
    color: rgb(67, 20, 7);
    font: inherit;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.16s ease, border-color 0.16s ease, background-color 0.16s ease;
}

.online-settings__choice-chip:hover {
    transform: translateY(-1px);
    border-color: rgba(180, 83, 9, 0.32);
}

.online-settings__choice-chip.is-active {
    border-color: rgba(180, 83, 9, 0.44);
    background: rgba(255, 237, 213, 0.92);
    color: rgb(154, 52, 18);
    font-weight: 700;
}

.online-settings__choice-chip.is-disabled,
.online-settings__choice-chip:disabled {
    cursor: not-allowed;
    opacity: 0.45;
    transform: none;
}

.online-settings__choice-chip.is-disabled:hover,
.online-settings__choice-chip:disabled:hover {
    transform: none;
    border-color: rgba(148, 163, 184, 0.24);
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

.online-settings__message--compact {
    margin-top: 12px;
    padding: 12px 14px;
}

.online-settings__preview-copy {
    margin-top: 16px;
    font-size: 1rem;
    font-weight: 700;
}

.online-settings__preview-control-card {
    margin-top: 16px;
    border: 1px solid rgba(180, 83, 9, 0.14);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.84);
    padding: 14px;
}

.online-settings__preview-control-header,
.online-settings__timeline-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    font-size: 0.88rem;
    color: rgba(67, 20, 7, 0.78);
}

.online-settings__preview-control-header {
    margin-bottom: 12px;
}

.online-settings__preview-control-header strong {
    display: block;
    color: rgb(120, 53, 15);
    font-size: 0.92rem;
}

.online-settings__preview-range {
    margin-top: 4px;
    font-size: 0.82rem;
    color: rgba(67, 20, 7, 0.72);
}

.online-settings__timeline-toolbar {
    margin-top: 16px;
}

.online-settings__timeline-toolbar-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.online-settings__timeline-toolbar-group--meta {
    justify-content: flex-end;
}

.online-settings__timeline-action {
    border-radius: 999px;
    border: 1px solid rgba(180, 83, 9, 0.24);
    background: rgba(255, 255, 255, 0.92);
    padding: 10px 14px;
    color: rgb(120, 53, 15);
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.online-settings__timeline-toolbar-note {
    align-self: center;
    font-weight: 600;
}

.online-settings__status-list {
    display: grid;
    gap: 12px;
}

.online-settings__status-card {
    border: 1px solid rgba(180, 83, 9, 0.12);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.88);
    padding: 14px;
}

.online-settings__status-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.online-settings__status-title {
    font-size: 0.94rem;
    font-weight: 700;
    color: rgb(120, 53, 15);
}

.online-settings__status-subtitle {
    margin-top: 3px;
    font-size: 0.82rem;
    color: rgba(67, 20, 7, 0.72);
}

.online-settings__status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
    margin-top: 12px;
}

.online-settings__status-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.online-settings__status-label {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(180, 83, 9);
}

.online-settings__state-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: fit-content;
    max-width: 100%;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 0.82rem;
    font-weight: 700;
}

.online-settings__state-pill--active {
    background: rgba(34, 197, 94, 0.14);
    color: rgb(21, 128, 61);
}

.online-settings__state-pill--muted {
    background: rgba(148, 163, 184, 0.16);
    color: rgb(71, 85, 105);
}

.online-settings__state-pill--past {
    background: rgba(249, 115, 22, 0.14);
    color: rgb(154, 52, 18);
}

.online-settings__dialog-shell {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.42);
}

.online-settings__dialog-card {
    width: min(720px, 100%);
    border-radius: 24px;
    border: 1px solid rgba(180, 83, 9, 0.16);
    background: rgba(255, 251, 235, 0.98);
    padding: 20px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
}

.online-settings__dialog-header {
    margin-bottom: 14px;
}

.online-settings__dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 18px;
}

.online-settings__save-button--compact {
    min-width: 0;
}

.online-settings__timeline {
    display: grid;
    grid-template-columns: repeat(3, minmax(360px, 1fr));
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 4px;
    align-items: start;
}

.online-settings__timeline-week-card {
    border: 1px solid rgba(180, 83, 9, 0.16);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.78);
    padding: 12px;
    min-width: 360px;
}

.online-settings__timeline-week-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: rgb(120, 53, 15);
    text-align: center;
    padding: 0 0 10px;
}

.online-settings__timeline-week-days {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 8px;
}

.online-settings__day-chip {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
    min-height: 78px;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.82);
    padding: 8px 6px 10px;
}

.online-settings__day-chip.is-highlighted {
    outline: 2px solid rgba(180, 83, 9, 0.26);
}

.online-settings__day-chip.has-start {
    background: rgba(219, 234, 254, 0.9);
}

.online-settings__day-chip.has-visibility-start {
    background: rgba(220, 252, 231, 0.9);
}

.online-settings__day-chip.has-visible-day {
    border: 1px solid rgba(34, 197, 94, 0.18);
}

.online-settings__day-chip.has-end {
    background: rgba(220, 252, 231, 0.9);
}

.online-settings__visibility-icon {
    display: inline-flex;
    justify-content: center;
    width: 22px;
    height: 22px;
    color: rgb(21, 128, 61);
}

.online-settings__visibility-icon svg {
    width: 100%;
    height: 100%;
    fill: currentColor;
}

.online-settings__icon-row {
    display: inline-flex;
    flex-direction: column;
    align-self: center;
    justify-content: center;
    align-items: center;
    gap: 4px;
    min-height: 48px;
}

.online-settings__orderable-icon {
    display: inline-flex;
    justify-content: center;
    width: 22px;
    height: 22px;
    color: rgb(37, 99, 235);
}

.online-settings__orderable-icon svg {
    width: 100%;
    height: 100%;
    fill: currentColor;
}

.online-settings__day-label {
    font-size: 0.78rem;
    font-weight: 700;
    text-align: center;
}

.online-settings__marker-stack {
    display: grid;
    gap: 4px;
    width: 100%;
}

.online-settings__marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    border-radius: 999px;
    padding: 4px 6px;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.25;
    text-align: center;
}

.online-settings__marker--start {
    background: rgba(59, 130, 246, 0.16);
    color: rgb(30, 64, 175);
}

.online-settings__marker--end {
    background: rgba(34, 197, 94, 0.16);
    color: rgb(21, 128, 61);
}

.online-settings__visibility-note,
.online-settings__auto-start-note {
    margin-top: 14px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.84);
    padding: 12px 14px;
    font-size: 0.9rem;
    color: rgba(67, 20, 7, 0.82);
}

.online-settings__current-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.online-settings__current-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-radius: 16px;
    border: 1px solid rgba(180, 83, 9, 0.14);
    background: rgba(255, 255, 255, 0.84);
    padding: 14px;
}

.online-settings__current-label {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(180, 83, 9);
}

.online-settings__current-value {
    font-size: 0.95rem;
    line-height: 1.4;
    color: rgb(67, 20, 7);
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

    .online-settings__field-grid--timeline {
        grid-template-columns: 1fr;
    }

    .online-settings__field-grid--daytime {
        grid-template-columns: 1fr;
    }

    .online-settings__timeline {
        grid-template-columns: repeat(3, minmax(340px, 1fr));
    }

    .online-settings__timeline-week-card {
        min-width: 340px;
    }
}
</style>
