<template>
    <v-card
        class="option-card option-card--drop pa-5"
        rounded="xl"
        elevation="0"
        :class="{ 'drop-zone--active': isDragActive }"
        @dragover.prevent="onDragOver"
        @dragleave.prevent="onDragLeave"
        @drop.prevent="onDrop">
        <v-icon size="44" class="mb-3 option-icon">mdi-tray-arrow-down</v-icon>
        <div class="text-h6 font-weight-bold mb-2">Datei ablegen</div>
        <div class="text-body-2 option-subline">Ziehe eine Datei hier hinein, z. B. PDF oder Bild.</div>
    </v-card>
</template>

<script>
export default {
    name: 'MaterialsDropOption',
    emits: ['files-dropped'],

    data() {
        return {
            isDragActive: false,
        }
    },

    methods: {
        onDragOver() {
            this.isDragActive = true
        },
        onDragLeave() {
            this.isDragActive = false
        },
        onDrop(event) {
            this.isDragActive = false
            const files = Array.from(event?.dataTransfer?.files || [])
            this.$emit('files-dropped', files)
        },
    },
}
</script>

<style scoped>
.option-card {
    min-height: 320px;
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
    border-style: dashed;
    border-width: 2px;
    border-color: rgba(253, 128, 46, 0.55);
    background: linear-gradient(180deg, rgba(253, 128, 46, 0.11), rgba(255, 255, 255, 0.95));
}

.drop-zone--active {
    border-color: var(--pumpkin);
    background: linear-gradient(180deg, rgba(253, 128, 46, 0.2), rgba(255, 255, 255, 0.98));
}

.option-icon {
    color: var(--pumpkin);
}

.option-subline {
    color: #314d5d;
}
</style>
