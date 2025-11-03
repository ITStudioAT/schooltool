<template>
    <v-sheet class="pr-2 mb-2">
        <v-card flat rounded="0" :color="color ? color : 'secondary'">
            <v-card-title>
                <div class="d-flex flex-row ga-2">
                    <v-icon :icon="icon" v-if="icon" />
                    <div>{{ title }}</div>
                </div>
            </v-card-title>
            <v-card-text :class="'bg-' + color + '-lighten-4'" class="pt-2">
                <v-list density="compact">
                    <v-list-item :class="i % 2 == 0 ? '' : 'bg-secondary-lighten-2'" v-for="(info, i) in infos">
                        <template v-slot:prepend>
                            {{ info.title }}
                        </template>
                        <template v-slot:append>
                            {{ info.content }}
                        </template>
                    </v-list-item>
                </v-list>
            </v-card-text>

            <v-card-actions class="justify-end">
                <v-btn slim flat rounded="0" :to="url" append-icon="mdi-arrow-right">Mehr</v-btn>
            </v-card-actions>
        </v-card>
    </v-sheet>
</template>


<script>
export default {
    props: ['title', 'color', 'icon', 'infos', 'url'],

    data() {
        return {

        };
    },

    methods: {

    }

}
</script>
