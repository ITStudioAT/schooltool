<template>
    <button
        v-if="emailAddress"
        type="button"
        class="copy-email-button d-inline-flex align-center ga-1"
        :title="`E-Mail-Adresse kopieren: ${emailAddress}`"
        :aria-label="`E-Mail-Adresse kopieren: ${emailAddress}`"
        @click.stop="copyEmail"
        @keydown.stop>
        <span>{{ emailAddress }}</span>
        <v-icon icon="mdi-content-copy" size="14" aria-hidden="true" />
    </button>
    <span v-else>-</span>
</template>

<script>
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export default {
    props: {
        email: {
            type: String,
            default: '',
        },
    },

    computed: {
        emailAddress() {
            return (this.email || '').trim()
        },
    },

    methods: {
        async copyEmail() {
            if (!this.emailAddress) { return }

            const notification = useNotificationStore()

            try {
                await navigator.clipboard.writeText(this.emailAddress)
                notification.notify({ message: 'E-Mail-Adresse kopiert.', type: 'success', timeout: 2000 })
            } catch {
                notification.notify({ message: 'Die E-Mail-Adresse konnte nicht kopiert werden.', type: 'error' })
            }
        },
    },
}
</script>

<style scoped>
.copy-email-button {
    max-width: 100%;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: pointer;
}

.copy-email-button span {
    min-width: 0;
    overflow-wrap: anywhere;
}

.copy-email-button:hover span,
.copy-email-button:focus-visible span {
    text-decoration: underline;
}
</style>
