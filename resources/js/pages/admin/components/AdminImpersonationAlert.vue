<template>
    <v-alert type="warning" variant="tonal" class="ma-2" v-if="isImpersonating">
        <div class="d-flex flex-row flex-wrap align-center justify-space-between ga-2">
            <div>
                Benutzer-Übernahme aktiv:
                <strong>{{ currentImpersonatedUserLabel }}</strong>
                <div class="text-caption mt-1">
                    Ursprünglicher Benutzer: <strong>{{ impersonatorLabel }}</strong>. Mit "Zurück" wechseln Sie zu diesem
                    Benutzer.
                </div>
            </div>
            <v-btn color="warning" flat tile @click="$emit('stop')">Zurück</v-btn>
        </div>
    </v-alert>
</template>

<script>
export default {
    props: {
        isImpersonating: {
            type: Boolean,
            required: true,
        },
        currentImpersonatedUserLabel: {
            type: String,
            required: true,
        },
        impersonatorLabel: {
            type: String,
            required: true,
        },
    },

    emits: ['stop'],
}
</script>
