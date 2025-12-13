<template>
    <div class="schooltool-background"></div>
    <div class="h-100 w-100 d-flex flex-column align-center justify-center" style="max-width: 1024px; margin: auto">
        <!-- Tool-Auswahl -->
        <v-card tile flat color="transparent" class="border-md" v-if="step == ''">
            <v-card-text>
                <!-- ANMELDETOOL -->
                <div class="d-flex flex-wrap justify-center ga-4">
                    <v-card color="third" width="300" height="170" class="d-flex flex-column">
                        <v-card-title class="text-h5">Anmeldetool</v-card-title>

                        <v-card-subtitle style="white-space: normal">Hier können Sie sich zu ausgeschriebenen Events anmelden.</v-card-subtitle>

                        <v-card-actions class="mt-auto">
                            <v-btn class="ms-2" size="small" text="LOS" variant="outlined" @click="loadSchoolsForTool('Anmeldetool')"></v-btn>
                        </v-card-actions>
                    </v-card>
                </div>
            </v-card-text>
        </v-card>

        <v-card tile flat color="transparent" class="border-md w-100" max-width="600" v-if="step == 'selectSchool' && selected_school">
            <v-card-text>
                <div class="d-flex flex-column flex-wrap justify-center ga-4">
                    <v-card color="third" max-width="600" class="d-flex flex-column w-100" v-if="licence">
                        <v-card-title class="text-h5">{{ licence.name }}</v-card-title>

                        <v-card-subtitle style="white-space: normal">{{ licence.long_name }}</v-card-subtitle>

                        <v-card-text>
                            <v-card tile flat width="300" color="transparent" class="text-left">
                                <img :src="'/storage/images/' + selected_school.logo" max-height="50" max-width="150" />
                            </v-card>
                            <div class="text-h6">{{ selected_school.long_name }}</div>
                        </v-card-text>

                        <v-card-actions class="mt-auto">
                            <v-btn class="ms-2" size="small" text="Zurück" color="warning" variant="flat" @click="abort('selectSchool')" />
                            <v-btn class="ms-2" size="small" text="Weiter" variant="outlined" @click="moveTo(licence, selected_school)" />
                        </v-card-actions>
                    </v-card>
                </div>
            </v-card-text>
        </v-card>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export default {
    components: {},

    async beforeMount() {
        this.homepageStore = useHomepageStore()
    },

    mounted() {},

    unmounted() {},

    data() {
        return {
            homepageStore: null,
            step: '',
        }
    },

    computed: {
        ...mapWritableState(useHomepageStore, ['config', 'is_loading', 'schools', 'licence', 'selected_school', 'selected_school_id']),
    },

    watch: {},

    methods: {},
}
</script>

<style scoped>
.schooltool-background {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url('/storage/images/students.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0.2;
    pointer-events: none;
    z-index: -1;
}
</style>
