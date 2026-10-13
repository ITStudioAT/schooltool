<template>
    <span class="d-inline-flex align-center ga-1">
        <v-icon v-for="item in statuses" :key="item.purpose" :icon="item.icon" size="16"
            :color="item.color" :aria-label="item.label" role="img" aria-hidden="false"
            :data-work-status="item.purpose === 'submission' ? undefined : item.purpose"
            :data-submission-state="item.purpose === 'submission' ? item.state : undefined"
            :data-evaluation-state="item.purpose === 'evaluation' ? item.state : undefined" />
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { workCellStatuses } from '@/helpers/workCellStatus'

const props = defineProps({
    work: { type: Object, default: null },
    studentId: { type: [Number, String], default: null },
    now: { type: Number, default: undefined },
})
const statuses = computed(() => workCellStatuses(props.work, props.studentId, props.now))
</script>
