<template>
    <section class="materials-v2-calendar" aria-label="Terminkalender">
        <div v-if="loading" class="materials-v2-calendar-loading" role="status" aria-live="polite">
            <span class="materials-v2-calendar-loading-spinner" aria-hidden="true" />
            Termine werden geladen …
        </div>

        <div class="materials-v2-calendar-toolbar">
            <div class="materials-v2-calendar-navigation">
                <v-btn
                    class="materials-v2-calendar-previous"
                    icon="mdi-chevron-left"
                    size="small"
                    variant="text"
                    :title="previousPeriodLabel"
                    :aria-label="previousPeriodLabel"
                    @click="$emit('move', -1)" />
                <v-btn
                    class="materials-v2-calendar-today"
                    icon="mdi-calendar-today"
                    size="small"
                    variant="tonal"
                    title="Heute"
                    aria-label="Heute"
                    @click="$emit('today')" />
                <v-btn
                    class="materials-v2-calendar-next"
                    icon="mdi-chevron-right"
                    size="small"
                    variant="text"
                    :title="nextPeriodLabel"
                    :aria-label="nextPeriodLabel"
                    @click="$emit('move', 1)" />
            </div>
            <div class="materials-v2-calendar-item-navigation" aria-label="Zwischen Terminen springen">
                <v-btn
                    class="materials-v2-calendar-previous-item"
                    icon="mdi-calendar-arrow-left"
                    size="small"
                    variant="outlined"
                    title="Vorheriger Termin"
                    aria-label="Vorheriger Termin"
                    :loading="adjacentReminderLoading === 'previous'"
                    :disabled="adjacentReminderLoading !== ''"
                    @click="$emit('jump-adjacent', 'previous')" />
                <v-btn
                    class="materials-v2-calendar-next-item"
                    icon="mdi-calendar-arrow-right"
                    size="small"
                    variant="outlined"
                    title="Nächster Termin"
                    aria-label="Nächster Termin"
                    :loading="adjacentReminderLoading === 'next'"
                    :disabled="adjacentReminderLoading !== ''"
                    @click="$emit('jump-adjacent', 'next')" />
            </div>
            <h2 class="materials-v2-calendar-period">{{ periodLabel }}</h2>
        </div>

        <div class="materials-v2-calendar-weekdays" aria-hidden="true">
            <span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span>
        </div>

        <div :class="['materials-v2-calendar-grid', `materials-v2-calendar-grid--${displayMode}`]">
            <article
                v-for="day in days"
                :key="day.key"
                :class="[
                    'materials-v2-calendar-day',
                    { 'materials-v2-calendar-day--outside': !day.isCurrentMonth },
                    { 'materials-v2-calendar-day--today': day.isToday },
                ]">
                <div class="materials-v2-calendar-day-heading">
                    <span v-if="displayMode === 'week'" class="materials-v2-calendar-day-weekday">
                        {{ day.weekday }}
                    </span>
                    <time :datetime="day.key">{{ day.dayNumber }}</time>
                </div>

                <div class="materials-v2-calendar-events">
                    <button
                        v-for="item in day.items"
                        :key="item.id"
                        type="button"
                        class="materials-v2-calendar-event"
                        :title="calendarEventTitle(item)"
                        @click="$emit('edit', item)">
                        <span class="materials-v2-calendar-event-time">
                            {{ item.reminder_time || 'Ganztägig' }}
                        </span>
                        <span class="materials-v2-calendar-event-title">{{ item.title }}</span>
                    </button>
                    <span v-if="!day.items.length" class="materials-v2-calendar-day-empty">Keine Termine</span>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { calendarEventTitle } from '@/domains/materialsV2/calendar'

defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    periodLabel: {
        type: String,
        required: true,
    },
    previousPeriodLabel: {
        type: String,
        required: true,
    },
    nextPeriodLabel: {
        type: String,
        required: true,
    },
    adjacentReminderLoading: {
        type: String,
        default: '',
    },
    weekdays: {
        type: Array,
        required: true,
    },
    displayMode: {
        type: String,
        required: true,
    },
    days: {
        type: Array,
        required: true,
    },
})

defineEmits(['move', 'today', 'jump-adjacent', 'edit'])
</script>

<style scoped>
.materials-v2-calendar {
    position: relative;
    margin-top: 0.5rem;
    overflow-x: auto;
    border: 1px solid rgba(23, 45, 59, 0.1);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 12px 40px rgba(23, 45, 59, 0.06);
}

.materials-v2-calendar-loading {
    position: absolute;
    z-index: 3;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    border-radius: inherit;
    background: rgba(255, 255, 255, 0.82);
    color: var(--materials-v2-ink);
    font-size: 0.82rem;
    font-weight: 750;
    backdrop-filter: blur(2px);
}

.materials-v2-calendar-loading-spinner {
    width: 22px;
    height: 22px;
    border: 3px solid rgba(255, 122, 50, 0.2);
    border-top-color: var(--materials-v2-accent);
    border-radius: 999px;
    animation: materials-v2-calendar-spin 700ms linear infinite;
}

@keyframes materials-v2-calendar-spin {
    to {
        transform: rotate(360deg);
    }
}

.materials-v2-calendar-toolbar {
    display: flex;
    min-width: 720px;
    padding: 0.8rem 1rem;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-bottom: 1px solid rgba(23, 45, 59, 0.08);
}

.materials-v2-calendar-navigation,
.materials-v2-calendar-item-navigation {
    display: flex;
    align-items: center;
}

.materials-v2-calendar-navigation {
    gap: 0.15rem;
}

.materials-v2-calendar-item-navigation {
    gap: 0.4rem;
}

.materials-v2-calendar-period {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    text-transform: capitalize;
}

.materials-v2-calendar-weekdays,
.materials-v2-calendar-grid {
    display: grid;
    min-width: 720px;
    grid-template-columns: repeat(7, minmax(0, 1fr));
}

.materials-v2-calendar-weekdays {
    border-bottom: 1px solid rgba(23, 45, 59, 0.08);
    background: rgba(247, 249, 252, 0.86);
}

.materials-v2-calendar-weekdays span {
    padding: 0.55rem 0.7rem;
    color: var(--materials-v2-muted);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-align: center;
    text-transform: uppercase;
}

.materials-v2-calendar-day {
    min-width: 0;
    min-height: 128px;
    padding: 0.55rem;
    border-right: 1px solid rgba(23, 45, 59, 0.07);
    border-bottom: 1px solid rgba(23, 45, 59, 0.07);
}

.materials-v2-calendar-grid--week .materials-v2-calendar-day {
    min-height: 420px;
}

.materials-v2-calendar-day:nth-child(7n) {
    border-right: 0;
}

.materials-v2-calendar-day--outside {
    background: rgba(247, 249, 252, 0.72);
    color: rgba(97, 116, 130, 0.6);
}

.materials-v2-calendar-day-heading {
    display: flex;
    min-height: 28px;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 800;
}

.materials-v2-calendar-day-weekday {
    margin-right: auto;
    color: var(--materials-v2-muted);
    text-transform: capitalize;
}

.materials-v2-calendar-day-heading time {
    display: grid;
    width: 28px;
    height: 28px;
    place-items: center;
    border-radius: 999px;
}

.materials-v2-calendar-day--today .materials-v2-calendar-day-heading time {
    background: var(--materials-v2-accent);
    color: #fff;
}

.materials-v2-calendar-events {
    display: flex;
    margin-top: 0.35rem;
    flex-direction: column;
    gap: 0.3rem;
}

.materials-v2-calendar-event {
    display: flex;
    width: 100%;
    padding: 0.38rem 0.45rem;
    align-items: flex-start;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(255, 122, 50, 0.18);
    border-radius: 8px;
    background: rgba(255, 122, 50, 0.09);
    color: var(--materials-v2-ink);
    cursor: pointer;
    font: inherit;
    text-align: left;
}

.materials-v2-calendar-event:hover,
.materials-v2-calendar-event:focus-visible {
    border-color: rgba(255, 122, 50, 0.45);
    background: rgba(255, 122, 50, 0.15);
    outline: none;
}

.materials-v2-calendar-event-time {
    color: var(--materials-v2-accent);
    font-size: 0.64rem;
    font-weight: 800;
}

.materials-v2-calendar-event-title {
    max-width: 100%;
    overflow: hidden;
    font-size: 0.72rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.materials-v2-calendar-day-empty {
    display: none;
    color: var(--materials-v2-muted);
    font-size: 0.72rem;
}

.materials-v2-calendar-grid--week .materials-v2-calendar-day-empty {
    display: inline;
    padding: 0.4rem 0.2rem;
}
</style>
