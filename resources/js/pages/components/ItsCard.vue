<template>
    <v-card :color="color" width="300" min-height="170" class="d-flex flex-column">
        <v-card-title class="text-h6">{{ title }}</v-card-title>
        <v-card-subtitle style="white-space: normal" v-if="subtitle">{{ subtitle }}</v-card-subtitle>
        <v-card-text class="text-body-2" v-if="text">{{ text }}</v-card-text>

        <v-card-text class="text-body-2" style="white-space: pre-line" v-if="description">
            {{ description.substr(0, 60) }}
            <span v-if="description.length > 60">…</span>
        </v-card-text>

        <v-card-actions class="mt-auto d-flex flex-row align-center" :class="justify ? justify : 'justify-end'">
            <div class="text-body-2 font-weight-light" v-if="subtext">{{ subtext }}</div>
            <v-btn size="small" :text="button" variant="outlined" @click="$emit('clickCard')" />
        </v-card-actions>
    </v-card>
</template>
<script>
export default {
    props: ['title', 'subtitle', 'color', 'text', 'description', 'subtext', 'justify', 'button'],
    emits: ['clickCard'],

    data() {
        return {}
    },

    methods: {},
}
</script>
