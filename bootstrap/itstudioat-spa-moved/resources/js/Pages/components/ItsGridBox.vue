<template>
    <v-sheet class="pr-2 mb-2">
        <v-card flat rounded="0" :color="color ? color : 'secondary'" class="h-100 py-0">
            <v-card-title>
                <div class="d-flex flex-row ga-2">
                    <v-icon :icon="icon" v-if="icon" />
                    <div>{{ title }}</div>
                </div>
                <div class="text-caption" v-if="subtitle">{{ subtitle }}</div>
            </v-card-title>
            <v-card-text :class="'bg-' + color + '-lighten-4'" class="pt-2 h-100">
                <slot>
                    {{ text }}
                </slot>
            </v-card-text>
        </v-card>
    </v-sheet>
</template>


<script>
export default {
    props: ['title', 'subtitle', 'text', 'color', 'icon'],

    data() {
        return {

        };
    },

    methods: {

    }

}
</script>
