<template>
    <v-card class="option-card option-card--drop pa-5" rounded="xl" elevation="0">
        <v-icon size="44" class="mb-3 option-icon">mdi-tray-arrow-down</v-icon>
        <div class="text-h6 font-weight-bold mb-2">Datei ablegen</div>
        <div class="text-body-2 option-subline mb-5">Upload wird später implementiert.</div>
        <v-btn block size="large" class="option-btn" disabled>Demnächst verfügbar</v-btn>
    </v-card>
</template>

<script>
export default {
    name: 'MaterialsDropOption',
}
</script>

<style scoped>
.option-card {
    min-height: 320px;
    display: flex;
    flex-direction: column;
    border: 1px solid rgba(253, 128, 46, 0.25);
    background: rgba(255, 255, 255, 0.7);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.option-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(253, 128, 46, 0.2);
    border-color: rgba(253, 128, 46, 0.45);
}

.option-card--drop {
    background: linear-gradient(180deg, rgba(253, 128, 46, 0.11), rgba(255, 255, 255, 0.95));
}

.option-icon {
    color: var(--pumpkin);
}

.option-subline {
    color: #314d5d;
}

.option-btn {
    margin-top: auto;
    font-weight: 700;
}
</style>
