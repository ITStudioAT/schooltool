<template>
    <section class="ns-invitation" :aria-label="title">
        <div class="ns-qr-preview">
            <button type="button" class="ns-qr-button" aria-label="QR-Code vergrößern" @click="expanded = true">
                <img :src="invitation.qr_data_url" :alt="title" width="240" height="240" />
            </button>
            <p class="ns-small ns-muted">Zum Vergrößern antippen</p>
        </div>
        <div class="ns-stack ns-invitation-copy">
            <h3>{{ title }}</h3>
            <p v-if="localProbe" class="ns-small">Dieser Link öffnet die andere Testperson im selben Lehrkraftkonto. Beide Fenster bleiben diesem isolierten Probelauf zugeordnet; keine Schüleranmeldung erforderlich.</p>
            <p v-else class="ns-small">Mit der Kamera scannen oder den Link öffnen. Melde dich bei Bedarf mit deinem persönlichen Schülerkonto an; die Einladung bleibt erhalten.</p>
            <a class="ns-small" :href="invitation.url" target="_blank" rel="noopener noreferrer">{{ invitation.url }}</a>
            <p v-if="invitation.local_only && localProbe" class="ns-small ns-gold" role="note">Für weitere Fenster auf diesem Rechner vorgesehen. Auf einem Handy bedeutet localhost dessen eigenes Gerät.</p>
            <p v-else-if="invitation.local_only" class="ns-small ns-gold" role="note">Dieser Link zeigt auf einen lokalen Rechner (localhost) und ist von Schülerhandys aus nicht erreichbar. Öffne Schooltool über eine für die Geräte erreichbare Adresse, bevor du den QR-Code teilst.</p>
            <p v-if="privateTeam" class="ns-small ns-muted">Privater Teamzugang: Teile diesen QR-Code nur mit deiner Gruppe. Kurszugehörigkeit und Teamgröße werden weiterhin geprüft.</p>
        </div>
        <v-dialog v-model="expanded" max-width="660">
            <v-card class="ns-dialog">
                <div class="ns-qr-large">
                    <h2>{{ title }}</h2>
                    <img :src="invitation.qr_data_url" :alt="title" width="512" height="512" />
                    <p class="ns-small">{{ invitation.local_only ? 'Lokaler Link: auf Schülerhandys nicht erreichbar.' : 'Mit der Kamera scannen und im eigenen Schülerkonto öffnen.' }}</p>
                    <button type="button" class="ns-button ns-button-secondary" @click="expanded = false">Schließen</button>
                </div>
            </v-card>
        </v-dialog>
    </section>
</template>

<script setup>
import { ref } from 'vue'
defineProps({ invitation: { type: Object, required: true }, title: { type: String, default: 'Der Klassenrunde beitreten' }, privateTeam: Boolean, localProbe: Boolean })
const expanded = ref(false)
</script>

<style scoped>
.ns-invitation { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 24px; align-items: center; margin: 18px 0; }
.ns-qr-button { display: block; padding: 0; background: #fff; border: 0; line-height: 0; border-radius: 4px; }
.ns-qr-button img { display: block; width: 240px; max-width: 100%; height: auto; }
.ns-qr-preview { max-width: 100%; }
.ns-qr-preview p { margin-top: 8px; }
.ns-invitation-copy { min-width: 0; }
.ns-invitation a { overflow-wrap: anywhere; }
.ns-qr-large { padding: 24px; display: grid; gap: 18px; justify-items: center; text-align: center; }
.ns-qr-large img { display: block; width: auto; max-width: 100%; height: auto; max-height: calc(100dvh - 260px); background: #fff; }
@media (max-width: 700px) { .ns-invitation { grid-template-columns: 1fr; gap: 16px; } .ns-qr-preview { justify-self: center; } }
</style>
