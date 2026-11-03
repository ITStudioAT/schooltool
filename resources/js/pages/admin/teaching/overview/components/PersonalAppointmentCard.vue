<template>
    <button type="button" class="personal-appointment-card" :class="{ 'personal-appointment-card--break': appointment.kind === 'break_supervision' }"
        :title="`${personalAppointmentLabel(appointment)}, ${personalAppointmentKinds.find((kind) => kind.value === appointment.kind)?.title}, ${personalAppointmentTimeLabel(appointment)}`"
        :aria-label="`${personalAppointmentLabel(appointment)}, ${personalAppointmentTimeLabel(appointment)}, bearbeiten`" @click="$emit('edit', appointment)">
        <template v-if="appointment.kind === 'break_supervision'">
            <strong class="personal-appointment-break-title">{{ personalAppointmentLabel(appointment) }}</strong>
            <span class="personal-appointment-break-time">{{ personalAppointmentSegments(appointment).map((segment) => `${segment.starts_at}–${segment.ends_at}`).join(' · ') }}</span>
        </template>
        <template v-else>
        <span v-if="showDate" class="personal-appointment-date">{{ appointment.occurrenceDate.split('-').reverse().join('.') }}</span>
        <strong>{{ personalAppointmentLabel(appointment) }}</strong>
        <span v-for="(segment, index) in personalAppointmentSegments(appointment)" :key="index">{{ segment.hour ? `${segment.hour}. Std · ` : '' }}{{ segment.starts_at }}–{{ segment.ends_at }}</span>
        <span v-if="appointment.title" class="personal-appointment-kind">{{ personalAppointmentKinds.find((kind) => kind.value === appointment.kind)?.title }}</span>
        </template>
    </button>
</template>

<script setup>
import { personalAppointmentKinds, personalAppointmentLabel, personalAppointmentSegments, personalAppointmentTimeLabel } from '@/helpers/teachingPersonalAppointments'

defineProps({ appointment: { type: Object, required: true }, showDate: { type: Boolean, default: false } })
defineEmits(['edit'])
</script>

<style scoped>
.personal-appointment-card {
    display: flex;
    flex-direction: column;
    gap: 2px;
    width: 100%;
    padding: 8px;
    color: #513c78;
    background: #eee5f8;
    border-radius: 6px;
    text-align: left;
    font-size: 0.75rem;
    overflow-wrap: anywhere;
    cursor: pointer;
}
.personal-appointment-card:hover { background: #e5d7f5; }
.personal-appointment-card:focus-visible { outline: 2px solid #76549e; outline-offset: 2px; }
.personal-appointment-kind, .personal-appointment-date { font-size: 0.65rem; }
.personal-appointment-card--break { flex-direction: row; align-items: center; justify-content: space-between; padding: 4px 5px; gap: 6px; }
.personal-appointment-break-title { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.personal-appointment-break-time { margin-left: auto; flex-shrink: 0; max-width: calc(100% - 12px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.65rem; }
</style>
