<template>
    <v-card tile flat border="md" width="300" min-height="200">
        <v-card-title>{{ offer.subject.short_name }}</v-card-title>
        <v-card-subtitle>{{ offer.subject.long_name }}</v-card-subtitle>
        <v-card-text>
            <div class="text-body-1">{{ offer.title }}</div>
            <div class="text-body-2">{{ offer.description }}</div>
        </v-card-text>
        <v-card-text v-if="!offer.accepted_at">
            <v-alert type="warning">
                <div>Bestätigung ausstehend</div>
                <div class="text-caption">{{ offer.email_mentor }}</div>
            </v-alert>
        </v-card-text>
        <v-card-text v-if="offer.accepted_at">
            <v-alert type="success">Freigegeben!</v-alert>
            <v-card tile flat class="mt-2" v-if="offer.is_active">
                <v-card-title class="d-flex flex-row align-center ga-2 bg-success">
                    <v-icon icon="mdi-web" />
                    <div>ONLINE</div>
                </v-card-title>
                <div class="d-flex justify-end">
                    <v-btn tile flat size="small" color="primary">Ausschalten</v-btn>
                </div>
            </v-card>
            <v-card tile flat class="mt-2" v-if="!offer.is_active">
                <v-card-title class="d-flex flex-row align-center ga-2 bg-error">
                    <v-icon icon="mdi-web" />
                    <div>OFFLINE</div>
                </v-card-title>
                <div class="d-flex justify-end">
                    <v-btn tile flat size="small" color="primary">Einschalten</v-btn>
                </div>
            </v-card>
        </v-card-text>
    </v-card>
</template>
<script>
export default {
    props: ['offer'],
}
</script>
