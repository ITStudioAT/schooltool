<template>
    <ul class="notifications-list">
        <li v-for="notification in items" :key="notification.id" class="notification-item"
            :class="notification.is_open ? 'notification-open' : 'notification-closed'">
            <v-icon size="20" class="notification-icon" :color="notification.is_open ? '#f44336' : '#4caf50'" aria-hidden="true">
                {{ notification.is_open ? 'mdi-bell-alert' : 'mdi-bell-check' }}
            </v-icon>
            <div class="notification-content">
                <div class="notification-header">
                    <time v-if="notification.date" :datetime="notification.date">{{ formatDate(notification.date) }}</time>
                    <strong v-if="notification.type">{{ typeLabels[notification.type] || notification.type }}</strong>
                    <span>{{ notification.is_open ? 'Ungelesen' : notification.read_at ? 'Gelesen' : 'Erledigt' }}</span>
                </div>
                <p class="notification-description">{{ notification.description }}</p>
                <div v-if="notification.due_date || notification.done_date" class="notification-dates">
                    <span v-if="notification.due_date">Fällig: {{ formatDate(notification.due_date) }}</span>
                    <span v-if="notification.done_date">Erledigt: {{ formatDate(notification.done_date) }}</span>
                </div>
                <v-btn v-if="canMark && notification.is_open && notification.read_key" variant="tonal" min-height="44" class="mt-2"
                    :disabled="Boolean(marking)" :loading="marking === notification.read_key"
                    @click="$emit('mark-read', notification)">Als gelesen markieren</v-btn>
            </div>
        </li>
    </ul>
</template>

<script setup>
defineProps({ items: { type: Array, required: true }, formatDate: { type: Function, required: true },
    typeLabels: { type: Object, required: true }, canMark: Boolean, marking: String })
defineEmits(['mark-read'])
</script>

<style scoped>
.notifications-list { display: grid; gap: 12px; margin: 0; padding: 0; list-style: none; }
.notification-item { display: flex; align-items: flex-start; gap: 12px; padding: 14px; border-radius: 8px; }
.notification-open { background: #ffebee; border: 2px solid #f44336; }
.notification-closed { background: #f1f7f2; border: 1px solid #cee4d1; }
.notification-icon { flex-shrink: 0; margin-top: 2px; }
.notification-content { min-width: 0; flex: 1; }
.notification-header, .notification-dates { display: flex; flex-wrap: wrap; gap: 6px 12px; font-size: .85rem; }
.notification-header { align-items: baseline; margin-bottom: 6px; }
.notification-description { color: #314d5d; line-height: 1.5; overflow-wrap: anywhere; }
.notification-dates { margin-top: 6px; }
@media (max-width: 520px) {
    .notification-item { padding: 12px; }
}
</style>
