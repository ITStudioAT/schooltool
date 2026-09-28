<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" title="Buchungen" icon="mdi-format-list-bulleted">
            <v-progress-linear v-if="isLoading" indeterminate color="primary" />
            <v-alert v-else-if="errorMessage" type="error" variant="tonal">{{ errorMessage }}</v-alert>
            <v-alert v-else-if="!bookings.length" type="info" variant="tonal">
                Es sind noch keine Buchungen vorhanden.
            </v-alert>
            <v-table v-else density="compact" class="restaurant-bookings-table">
                <thead>
                    <tr><th>Nr.</th><th>Person</th><th>Datum</th><th>Menü</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(booking, index) in bookings" :key="booking.id">
                        <td data-label="Nr.">{{ index + 1 }}</td>
                        <td data-label="Person">{{ booking.person }}</td>
                        <td data-label="Datum" class="text-no-wrap">{{ formatDate(booking.date) }}</td>
                        <td data-label="Menü">{{ booking.menu }}</td>
                    </tr>
                </tbody>
            </v-table>
        </ItsGridBox>
    </v-col>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { bookings as bookingsRoute } from '@/actions/App/Http/Controllers/Admin/Restaurant/RestaurantMenuPlanController'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

const bookings = ref([])
const isLoading = ref(true)
const errorMessage = ref('')

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

onMounted(async () => {
    try {
        const response = await axios.get(bookingsRoute.url())
        bookings.value = response.data.data
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Die Buchungen konnten nicht geladen werden.'
    } finally {
        isLoading.value = false
    }
})
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
