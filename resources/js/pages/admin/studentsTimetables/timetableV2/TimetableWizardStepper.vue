<template>
    <nav class="timetable-wizard-stepper" aria-label="Stundenplanfortschritt">
        <ol class="timetable-wizard-stepper__list">
            <li
                v-for="(item, index) in items"
                :key="item.value"
                class="timetable-wizard-stepper__item"
                :class="{
                    'timetable-wizard-stepper__item--active': item.value === modelValue,
                    'timetable-wizard-stepper__item--complete': item.value < modelValue,
                }"
                :aria-current="item.value === modelValue ? 'step' : undefined">
                <span class="timetable-wizard-stepper__circle">{{ item.value }}</span>
                <span class="timetable-wizard-stepper__title">{{ item.title }}</span>
                <span
                    v-if="index < items.length - 1"
                    class="timetable-wizard-stepper__connector"
                    aria-hidden="true"></span>
            </li>
        </ol>
    </nav>
</template>

<script>
export default {
    props: {
        items: {
            type: Array,
            required: true,
        },
        modelValue: {
            type: Number,
            required: true,
        },
    },
}
</script>

<style scoped>
.timetable-wizard-stepper {
    width: 100%;
    padding: 20px 0 8px;
    background: #ffffff;
}

.timetable-wizard-stepper__list {
    display: flex;
    align-items: center;
    width: 100%;
    margin: 0;
    padding: 0;
    list-style: none;
}

.timetable-wizard-stepper__item {
    display: flex;
    align-items: center;
    min-width: 0;
    color: var(--schedule-muted);
    flex: 1 1 0;
    gap: 10px;
}

.timetable-wizard-stepper__item:last-child {
    flex: 0 0 auto;
}

.timetable-wizard-stepper__circle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e0e3eb;
    color: var(--schedule-muted);
    flex: 0 0 28px;
    font-size: 0.8rem;
    font-weight: 700;
}

.timetable-wizard-stepper__title {
    font-size: 0.86rem;
    font-weight: 600;
    white-space: nowrap;
}

.timetable-wizard-stepper__connector {
    height: 2px;
    margin: 0 16px;
    background: #e0e3eb;
    flex: 1 1 auto;
}

.timetable-wizard-stepper__item--active {
    color: var(--schedule-heading);
}

.timetable-wizard-stepper__item--active .timetable-wizard-stepper__circle {
    background: var(--schedule-accent);
    color: #ffffff;
}

.timetable-wizard-stepper__item--active .timetable-wizard-stepper__title {
    font-weight: 700;
}

.timetable-wizard-stepper__item--complete .timetable-wizard-stepper__connector {
    background: var(--schedule-accent);
}

@media (max-width: 700px) {
    .timetable-wizard-stepper {
        overflow-x: auto;
        padding-top: 14px;
    }

    .timetable-wizard-stepper__list {
        min-width: 560px;
    }
}
</style>
