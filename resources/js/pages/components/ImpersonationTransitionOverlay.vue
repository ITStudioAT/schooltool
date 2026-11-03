<template>
    <v-dialog
        v-if="impersonationTransition"
        :model-value="Boolean(impersonationTransition)"
        persistent
        max-width="420"
        :z-index="10000"
        class="impersonation-transition-overlay">
        <v-card rounded="xl" class="pa-6 text-center" role="status" aria-live="assertive" aria-busy="true">
            <LoadingAnimation />
            <div class="text-h6 mt-2">
                {{ impersonationTransition === 'stop' ? 'Sie wechseln zurück …' : impersonationTransition === 'switch' ? 'Konto wird gewechselt …' : 'Benutzer-Übernahme wird gestartet …' }}
            </div>
            <div class="text-body-2 text-medium-emphasis mt-2">Der Account wird geöffnet. Bitte kurz warten.</div>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { impersonationTransition } from '@/helpers/impersonationTransition';
import LoadingAnimation from '@/pages/components/LoadingAnimation.vue';
</script>

<style>
.impersonation-transition-overlay .v-overlay__scrim {
    background: rgba(15, 23, 42, 0.48);
    backdrop-filter: blur(5px);
    opacity: 1;
}
</style>
