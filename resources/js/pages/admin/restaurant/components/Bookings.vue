<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" :title="title" icon="mdi-format-list-bulleted">
            <v-btn
                to="/admin/restaurant"
                size="small"
                color="primary"
                variant="tonal"
                prepend-icon="mdi-arrow-left"
                class="mb-4">
                Zurück zur Übersicht
            </v-btn>
            <p v-if="selectedWeek" class="text-body-2 mb-4">
                {{ formatDate(selectedWeek.start_date) }} – {{ formatDate(selectedWeek.end_date) }}
                · {{ bookings.length }} {{ bookings.length === 1 ? 'Buchung' : 'Buchungen' }}
            </p>
            <v-progress-linear v-if="isLoading" indeterminate color="primary" />
            <v-alert v-else-if="errorMessage" type="error" variant="tonal">{{ errorMessage }}</v-alert>
            <v-alert v-else-if="!bookings.length" type="info" variant="tonal">
                Es sind noch keine Buchungen vorhanden.
            </v-alert>
            <v-table v-else density="compact" class="restaurant-bookings-table">
                <thead>
                    <tr><th>Nr.</th><th>Person</th><th>Datum</th><th>Menü</th><th>Aktion</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(booking, index) in bookings" :key="booking.id">
                        <td data-label="Nr.">{{ index + 1 }}</td>
                        <td data-label="Person">{{ booking.person }}</td>
                        <td data-label="Datum" class="text-no-wrap">{{ formatDate(booking.date) }}</td>
                        <td data-label="Menü">{{ booking.menu }}</td>
                        <td data-label="Aktion">
                            <v-btn size="small" color="error" variant="tonal" :disabled="!booking.can_delete"
                                :title="booking.can_delete ? 'Buchung löschen' : 'Bereits abgerechnet – Löschen nicht möglich'"
                                @click="requestDelete(booking)">Löschen</v-btn>
                            <div v-if="!booking.can_delete" class="text-caption">Bereits abgerechnet</div>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </ItsGridBox>
        <v-dialog v-model="deleteDialog" max-width="520" persistent>
            <v-card rounded="xl">
                <v-card-title>Buchung löschen</v-card-title>
                <v-card-text>
                    <template v-if="deleteTarget">
                        <p>Soll diese Buchung endgültig gelöscht werden?</p>
                        <p class="mt-3 font-weight-bold">{{ deleteTarget.person }}</p>
                        <p>{{ formatDate(deleteTarget.date) }} · {{ deleteTarget.menu }}</p>
                    </template>
                    <v-alert v-if="deleteError" type="error" variant="tonal" class="mt-3">{{ deleteError }}</v-alert>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="isDeleting" @click="cancelDelete">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="isDeleting" :disabled="isDeleting || deleteTarget?.can_delete !== true"
                        @click="confirmDelete">Löschen bestätigen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { bookings as bookingsRoute, destroyEntryBooking } from '@/actions/App/Http/Controllers/Admin/Restaurant/RestaurantMenuPlanController'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

const bookings = ref([])
const isLoading = ref(true)
const errorMessage = ref('')
const route = useRoute()
const selectedWeek = ref(null)
const restaurantStore = useRestaurantStore()
const deleteDialog = ref(false)
const deleteTarget = ref(null)
const deleteError = ref('')
const isDeleting = ref(false)
const title = computed(() => selectedWeek.value
    ? `Buchungen · KW ${selectedWeek.value.calendar_week}/${selectedWeek.value.week_year}`
    : 'Buchungen')

function formatDate(date) {
    const menuDate = new Date(`${date}T00:00:00`)
    const weekday = menuDate.toLocaleDateString('de-AT', { weekday: 'short' }).slice(0, 2)
    const formattedDate = menuDate.toLocaleDateString('de-AT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    })

    return `${weekday} ${formattedDate}`
}

function requestDelete(booking) {
    if (booking.can_delete !== true) return
    deleteTarget.value = booking
    deleteError.value = ''
    deleteDialog.value = true
}

function cancelDelete() {
    if (isDeleting.value) return
    deleteDialog.value = false
    deleteTarget.value = null
}

async function confirmDelete() {
    if (isDeleting.value || deleteTarget.value?.can_delete !== true) return
    isDeleting.value = true
    deleteError.value = ''
    const target = deleteTarget.value
    try {
        await axios.delete(destroyEntryBooking.url({ planId: target.plan_id, entryId: target.entry_id, bookingId: target.id }))
        bookings.value = bookings.value.filter((booking) => booking.id !== target.id)
        deleteDialog.value = false
        deleteTarget.value = null
        await restaurantStore.loadSettings()
    } catch (error) {
        deleteError.value = error.response?.data?.message || 'Die Buchung konnte nicht gelöscht werden.'
        if (error.response?.status === 409) target.can_delete = false
    } finally {
        isDeleting.value = false
    }
}

watch(() => route.query.week_start, async (weekStart) => {
    isLoading.value = true
    errorMessage.value = ''
    bookings.value = []
    selectedWeek.value = null
    cancelDelete()
    try {
        const response = await axios.get(bookingsRoute.url(weekStart ? { query: { week_start: weekStart } } : undefined))
        bookings.value = response.data.data
        selectedWeek.value = response.data.meta?.selected_week || null
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Die Buchungen konnten nicht geladen werden.'
    } finally {
        isLoading.value = false
    }
}, { immediate: true })
</script>

<style scoped>
.restaurant-bookings-table :deep(table) {
    table-layout: fixed;
}

.restaurant-bookings-table :deep(td) {
    overflow-wrap: anywhere;
}

.restaurant-bookings-table :deep(th:first-child) {
    width: 64px;
}

.restaurant-bookings-table :deep(th:nth-child(3)) {
    width: 150px;
}

.restaurant-bookings-table :deep(th:last-child) {
    width: 155px;
}

@media (max-width: 599px) {
    .restaurant-bookings-table :deep(thead) {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip-path: inset(50%);
    }

    .restaurant-bookings-table :deep(tbody tr) {
        display: block;
        padding: 12px 0;
        border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    }

    .restaurant-bookings-table :deep(tbody tr td) {
        display: grid;
        grid-template-columns: 64px minmax(0, 1fr);
        gap: 12px;
        height: auto;
        padding: 4px 12px;
        border: 0;
    }

    .restaurant-bookings-table :deep(td::before) {
        content: attr(data-label);
        font-weight: 600;
    }
}
</style>
