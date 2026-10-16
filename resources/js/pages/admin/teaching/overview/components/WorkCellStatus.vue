<template>
    <span class="d-inline-flex align-center ga-1">
        <v-icon v-for="item in statuses" :key="item.purpose" :icon="item.icon" size="16"
            :color="item.color" :aria-label="item.label" role="img" aria-hidden="false"
            :title="nativeTitle ? item.label : undefined"
            :data-work-status="item.purpose === 'submission' ? undefined : item.purpose"
            :data-submission-state="item.purpose === 'submission' ? item.state : undefined"
            :data-evaluation-state="item.purpose === 'evaluation' ? item.state : undefined" />
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { workAggregateStatuses, workCellDeviationStatuses, workCellStatuses } from '@/helpers/workCellStatus'

const props = defineProps({
    work: { type: Object, default: null },
    studentId: { type: [Number, String], default: null },
    now: { type: Number, default: undefined },
    students: { type: Array, default: () => [] },
    aggregate: { type: Boolean, default: false },
    deviationsOnly: { type: Boolean, default: false },
    nativeTitle: { type: Boolean, default: false },
})
const statuses = computed(() => props.aggregate ? workAggregateStatuses(props.work, props.students, props.now)
    : props.deviationsOnly ? workCellDeviationStatuses(props.work, props.studentId, props.students, props.now)
        : workCellStatuses(props.work, props.studentId, props.now))
</script>
