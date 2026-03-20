<template>
    <v-overlay :model-value="true">
        <div style="width: 100vw !important; height: 100vh !important;"
            class="d-flex flex-row align-center justify-center">
            <v-row no-gutters justify="center">
                <v-col cols="12" md="6" xl="4">
                    <v-card flat rounded="0" :color="color ? color : 'primary'">
                        <v-card-title v-if="title">
                            {{ title }}
                        </v-card-title>

                        <v-card-text :class="color ? 'bg-' + color + '-lighten-4' : 'bg-primary-lighten-4'">
                            <slot name="content"></slot>
                        </v-card-text>

                    </v-card>

                </v-col>
            </v-row>
        </div>
    </v-overlay>

</template>


<script>
export default {
    props: ['title', 'subtitle', 'color', 'icon'],

    data() {
        return {
            is_show: true,
        };
    },

    methods: {

    }

}
</script>
