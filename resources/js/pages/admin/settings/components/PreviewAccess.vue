<template>
    <v-card v-if="isSuperAdmin" rounded="xl" class="pa-4 pa-md-6">
        <h2 class="text-h6 mb-2">Schooltool Vorschau</h2>
        <p class="text-body-2 text-medium-emphasis mb-4">
            Admins, Lehrkräfte, Schüler und Studierende können einzeln freigegeben werden. Eltern erhalten Zugang über ein freigegebenes Kinderkonto. Die bisherigen Berechtigungen bleiben bestehen.
        </p>

        <v-alert type="info" variant="tonal" density="compact" class="mb-5">
            Die Vorschau arbeitet mit einer getrennten Testkopie. Änderungen gelten nur für die Vorschau.
            Freigaben und der zentrale Schalter werden ausschließlich in der Hauptanwendung verwaltet.
            <a v-if="isFeaturePreview && previewLiveUrl" :href="previewLiveUrl" class="d-block mt-1">Zur Hauptanwendung</a>
        </v-alert>

        <v-alert v-if="!isFeaturePreview && store.error" type="error" variant="tonal" class="mb-4" role="alert">
            {{ store.error }}
        </v-alert>
        <v-progress-linear v-if="!isFeaturePreview && store.is_loading" indeterminate aria-label="Vorschau-Einstellungen werden geladen" />

        <template v-if="!isFeaturePreview && store.data">
            <div class="d-flex flex-wrap align-center ga-3 mb-4">
                <v-switch
                    v-model="enabledDraft"
                    label="Vorschau aktiv (alle Schulen)"
                    color="primary"
                    inset
                    hide-details
                    :disabled="isBusy" />
                <v-btn
                    color="primary"
                    :disabled="isBusy || enabledDraft === store.data.enabled"
                    :loading="store.is_saving"
                    @click="saveEnabled">
                    Speichern
                </v-btn>
                <v-chip :color="store.data.enabled ? 'success' : 'secondary'" size="small" variant="tonal">
                    {{ store.data.enabled ? 'Vorschau eingeschaltet' : 'Vorschau für alle gesperrt' }}
                </v-chip>
            </div>

            <v-text-field
                v-model="search"
                label="Konten der aktuellen Schule suchen"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                class="mb-4" />

            <div v-for="user in filteredUsers" :key="user.id" class="preview-account py-3">
                <div class="preview-account__details">
                    <div class="font-weight-bold">{{ userName(user) }}</div>
                    <div class="text-body-2">{{ user.email }}</div>
                    <div v-if="user.school_name" class="text-caption text-medium-emphasis">{{ user.school_name }}</div>
                    <div v-if="!user.eligible" class="text-caption text-warning">
                        {{ user.ineligible_reason || 'Dieses Konto kann derzeit nicht für die Vorschau freigegeben werden.' }}
                    </div>
                </div>
                <v-chip :color="user.allowed ? 'success' : 'secondary'" size="small" variant="tonal">
                    {{ user.allowed ? 'Freigegeben' : 'Nicht freigegeben' }}
                </v-chip>
                <v-btn
                    :color="user.allowed ? 'error' : 'primary'"
                    variant="tonal"
                    :disabled="isBusy || (!user.eligible && !user.allowed)"
                    :aria-label="`${user.allowed ? 'Zugang entziehen' : 'Zugang freigeben'}: ${userName(user)}`"
                    @click="store.saveUser(user.id, !user.allowed)">
                    {{ user.allowed ? 'Zugang entziehen' : 'Zugang freigeben' }}
                </v-btn>
            </div>
            <p v-if="filteredUsers.length === 0" class="text-body-2 py-4">Keine passenden Konten gefunden.</p>
        </template>

        <v-btn v-if="!isFeaturePreview && !store.data && !store.is_loading" variant="tonal" @click="load">
            Erneut laden
        </v-btn>
    </v-card>
</template>

<script>
import { useAdminStore } from '@/stores/admin/AdminStore'
import { usePreviewAccessStore } from '@/stores/admin/PreviewAccessStore'

export default {
    data() {
        return {
            store: usePreviewAccessStore(),
            enabledDraft: false,
            search: '',
        }
    },

    computed: {
        isSuperAdmin() {
            return useAdminStore().config?.roles?.includes('super_admin') === true
        },
        isFeaturePreview() {
            return useAdminStore().config?.preview?.is_preview === true
        },
        previewLiveUrl() {
            return useAdminStore().config?.preview?.live_url || ''
        },
        isBusy() {
            return this.store.is_loading || this.store.is_saving
        },
        filteredUsers() {
            const search = this.search.trim().toLocaleLowerCase('de')

            return (this.store.data?.users || []).filter((user) => {
                const text = [user.first_name, user.last_name, user.email, user.school_name].join(' ')

                return text.toLocaleLowerCase('de').includes(search)
            })
        },
    },

    async mounted() {
        if (this.isSuperAdmin && !this.isFeaturePreview) await this.load()
    },

    watch: {
        'store.data.enabled': {
            immediate: true,
            handler(enabled) {
                this.enabledDraft = enabled === true
            },
        },
    },

    methods: {
        userName(user) {
            return [user.last_name, user.first_name].filter(Boolean).join(' ') || user.email
        },
        async load() {
            if (await this.store.load()) this.enabledDraft = this.store.data.enabled
        },
        async saveEnabled() {
            await this.store.saveEnabled(this.enabledDraft)
            this.enabledDraft = this.store.data.enabled
        },
    },
}
</script>

<style scoped>
.preview-account {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}

.preview-account__details {
    flex: 1 1 240px;
    min-width: 0;
    overflow-wrap: anywhere;
}
</style>
