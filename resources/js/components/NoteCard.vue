<template>
    <v-card class="note-card" elevation="1" :class="{ 'pinned-note': note.is_pinned }">
        <v-card-title class="d-flex align-center justify-space-between py-2">
            <span class="text-h6 text-truncate">{{ note.title }}</span>
            <v-icon
                v-if="note.is_pinned"
                color="warning"
                size="small"
                class="ml-2"
            >
                mdi-pin
            </v-icon>
        </v-card-title>

        <v-card-text class="py-2">
            <div class="note-content text-body-1" v-html="formattedContent"></div>
            
            <div class="text-caption text-medium-emphasis mt-3">
                <v-icon size="small" class="mr-1">mdi-calendar</v-icon>
                {{ formattedDate }}
            </div>
        </v-card-text>

        <v-card-actions class="pa-2">
            <v-spacer></v-spacer>
            <v-btn
                icon
                size="small"
                color="warning"
                @click="$emit('toggle-pin')"
                :title="note.is_pinned ? 'Lösen' : 'Anpinnen'"
            >
                <v-icon>{{ note.is_pinned ? 'mdi-pin-off' : 'mdi-pin' }}</v-icon>
            </v-btn>
            
            <v-btn
                icon
                size="small"
                color="primary"
                @click="$emit('edit')"
                title="Bearbeiten"
            >
                <v-icon>mdi-pencil</v-icon>
            </v-btn>
            
            <v-btn
                icon
                size="small"
                color="error"
                @click="$emit('delete')"
                title="Löschen"
            >
                <v-icon>mdi-delete</v-icon>
            </v-btn>
        </v-card-actions>
    </v-card>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    note: {
        type: Object,
        required: true
    }
})

defineEmits(['edit', 'delete', 'toggle-pin'])

const formattedContent = computed(() => {
    // Simple formatting - replace newlines with <br> tags
    return props.note.content.replace(/\n/g, '<br>')
})

const formattedDate = computed(() => {
    const date = new Date(props.note.created_at)
    return date.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
})
</script>

<style scoped>
.note-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: all 0.2s ease;
}

.note-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.pinned-note {
    border-left: 4px solid #ff9800;
}

.note-content {
    max-height: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
}

.v-card-title {
    min-height: auto;
}

.v-card-text {
    flex-grow: 1;
    overflow: hidden;
}
</style>