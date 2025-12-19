<!-- components/ItsNotification.vue -->
<template>
    <v-snackbar :timer="notificationStore.type + '-lighten-4'" v-model="notificationStore.show"
        :timeout="notificationStore.persistent ? -1 : notificationStore.timeout" :color="notificationStore.type"
        min-height="70" :location="notificationStore.persistent ? 'top right' : 'bottom'">

        <div class="d-flex flex-row align-center ga-2">
            <v-icon :icon="icon(notificationStore.type)" />
            <div v-if="notificationStore.status">
                {{ notificationStore.message + ' (' + notificationStore.status + ')' }}
            </div>
            <div v-else>
                {{ notificationStore.message }}
            </div>
        </div>

        <template v-slot:actions v-if="notificationStore.persistent">
            <v-btn icon="mdi-close" variant="text" @click="notificationStore.close()"></v-btn>
        </template>

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
