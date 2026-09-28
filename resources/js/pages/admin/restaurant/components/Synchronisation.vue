<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" title="Synchronisation" icon="mdi-sync">
            <p class="mb-3">Cloudways LIVE → lokal · Restaurant der ausgewählten Schule</p>
            <p class="mb-3">
                Die Vorschau liest Speisen, Menüs, Menüpläne, Kategorien, Symbole, Speisezeiten, freie Tage,
                Buchungen, Abrechnungen, SEPA-Mandate und Restaurant-Einstellungen samt Bildern und Zuordnungen.
                Erst die anschließende Bestätigung übernimmt diesen geprüften Stand lokal.
            </p>
            <v-alert type="warning" variant="tonal" class="mb-3">
                Die Übernahme ersetzt die lokalen Restaurantdaten dieser Schule und entfernt lokale Restauranteinträge,
                die im geprüften Live-Stand fehlen. Benötigte Benutzer und Schüler werden zugeordnet, ergänzt oder in ihren
                restaurantbezogenen Profil- und Kontaktfeldern angepasst. Konten und Unterrichtsdaten werden nicht gelöscht.
                Bestehende Passwörter und Rollen außerhalb des Restaurants bleiben erhalten. Neue Konten erhalten ein
                unbekanntes Zufallspasswort und benötigen für eine lokale Anmeldung eine Passwortzurücksetzung.
                Auf Cloudways wird nichts geschrieben.
            </v-alert>
            <v-btn color="primary" variant="tonal" :loading="isPreviewing" :disabled="isApplying" @click="loadPreview">
                Vorschau laden
            </v-btn>
            <v-alert v-if="errorMessage" type="error" variant="tonal" class="mt-3">{{ errorMessage }}</v-alert>
            <v-alert v-if="successMessage" type="success" variant="tonal" class="mt-3">{{ successMessage }}</v-alert>
            <template v-if="preview">
                <p class="mt-4 mb-2">
                    {{ preview.school }} · Stand {{ formatTime(preview.captured_at) }} · Vorschau gilt 15 Minuten
                </p>
                <v-table density="compact">
                    <thead><tr><th>Datenart</th><th>Neu</th><th>Geändert</th><th>Entfernt</th></tr></thead>
                    <tbody>
                        <tr v-for="row in preview.summary" :key="row.table">
                            <td>{{ tableLabel(row.table) }}</td><td>{{ row.added }}</td><td>{{ row.changed }}</td><td>{{ row.removed }}</td>
                        </tr>
                    </tbody>
                </v-table>
                <p class="mt-2">{{ preview.files }} Bilddateien werden mit geprüfter Dateiprüfsumme lokal bereitgestellt.</p>
                <p v-if="preview.reused_student_accounts" class="mt-2">
                    {{ preview.reused_student_accounts }} eindeutig zugeordnete Schüler-Platzhalterkonten werden wiederverwendet.
                    Ihre Konto-IDs, lokalen Anmeldeadressen und Passwörter bleiben erhalten.
                </p>
                <v-checkbox v-model="confirmed" :disabled="isApplying" label="Ich möchte die oben beschriebenen lokalen Restaurantdaten durch diesen geprüften Stand ersetzen." />
                <v-btn color="primary" :loading="isApplying" :disabled="!confirmed || isPreviewing" @click="applyPreview">
                    Geprüften Stand übernehmen
                </v-btn>
            </template>
        </ItsGridBox>
    </v-col>
</template>

<script setup>
import { ref } from 'vue'
import { preview as previewRoute, apply as applyRoute } from '@/actions/App/Http/Controllers/Admin/Restaurant/RestaurantSynchronisationController'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

const preview = ref(null)
const confirmed = ref(false)
const isPreviewing = ref(false)
const isApplying = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

function formatTime(value) {
    return new Date(value).toLocaleString('de-AT')
}

function tableLabel(table) {
    const labels = {
        restaurant_categories: 'Kategorien', restaurant_ingredient_icons: 'Zutaten-Symbole', restaurant_foods: 'Speisen',
        restaurant_menus: 'Menüs', restaurant_eating_times: 'Speisezeiten', restaurant_free_days: 'Freie Tage',
        restaurant_menu_plans: 'Menüpläne', restaurant_food_restaurant_menu: 'Menü-Speisen',
        restaurant_food_restaurant_ingredient_icon: 'Speisen-Symbole', restaurant_menu_plan_entries: 'Menüplan-Einträge',
        restaurant_menu_plan_entry_eating_times: 'Menüplan-Speisezeiten', restaurant_menu_plan_bookings: 'Buchungen',
        restaurant_billings: 'Abrechnungen', restaurant_sepa_mandates: 'SEPA-Mandate', users: 'Benutzer', import116: 'Schüler (Import 116)',
        'school_tools (Restaurant)': 'Restaurant-Einstellungen', 'Restaurant-Rollen': 'Restaurant-Mitgliedschaften',
    }
    return labels[table] || table
}

async function loadPreview() {
    isPreviewing.value = true
    preview.value = null
    confirmed.value = false
    errorMessage.value = ''
    successMessage.value = ''
    try {
        const response = await axios.post(previewRoute.url())
        preview.value = response.data.data
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Der Live-Stand konnte nicht geprüft werden.'
    } finally {
        isPreviewing.value = false
    }
}

async function applyPreview() {
    if (!confirmed.value || !preview.value || isApplying.value) return
    isApplying.value = true
    errorMessage.value = ''
    try {
        const response = await axios.post(applyRoute.url(), { token: preview.value.token, confirmed: true })
        successMessage.value = response.data.message
        preview.value = null
        confirmed.value = false
        await useRestaurantStore().loadSettings()
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Die Übernahme ist fehlgeschlagen; bitte erneut prüfen.'
    } finally {
        isApplying.value = false
    }
}
</script>
