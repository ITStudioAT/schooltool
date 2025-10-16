<!-- components/ItsNotification.vue -->
<template>
    <v-snackbar :timer="notificationStore.type + '-lighten-4'" v-model="notificationStore.show"
        :timeout="notificationStore.timeout" :color="notificationStore.type" min-height="70">

        <div class="d-flex flex-row align-center ga-2">
            <v-icon :icon="icon(notificationStore.type)" />
            <div v-if="notificationStore.status">
                {{ notificationStore.message + ' (' + notificationStore.status + ')' }}
            </div>
            <div v-else>
                {{ notificationStore.message }}
            </div>
        </div>

    </v-snackbar>
</template>

<script setup>
import { useNotificationStore } from "@/stores/spa/NotificationStore";
const notificationStore = useNotificationStore();

function icon(type) {
    switch (type) {
        case 'error':
            return 'mdi-alert-circle-outline';
        case 'success':
            return 'mdi-check-bold';
        default:
            return 'mdi-alert-circle-outline';
    }
}
</script>
