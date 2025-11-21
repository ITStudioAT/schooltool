<template>
    <div class="schooltool-background">
        <div class="text-container">
            <span v-for="(letter, index) in letters" :key="index" class="letter" :style="{ animationDelay: `${index * 0.1}s` }">
                {{ letter }}
            </span>
        </div>
    </div>
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

                    <!-- NACHHILFETOOL -->
                    <v-card color="secondary" width="300" height="170" class="d-flex flex-column">
                        <v-card-title class="text-h5">SuSis helfen SuSis</v-card-title>

                        <v-card-subtitle style="white-space: normal">Das Nachhilfetool für Schüler:innen. Anbieten und Anfordern von Nachhilfe.</v-card-subtitle>

                        <v-card-actions class="mt-auto">
                            <div>befindet sich derzeit in Entwicklung</div>
                            <!--
                            <v-btn class="ms-2" size="small" text="LOS" variant="outlined" @click="loadSchoolsForTool('Tutoring')"></v-btn>
                            -->
                        </v-card-actions>
                    </v-card>

                    <!-- MITTAGESSEN -->
                    <v-card color="third" width="300" height="170" class="d-flex flex-column">
                        <v-card-title class="text-h5">Mittagsmenüs</v-card-title>

                        <v-card-subtitle style="white-space: normal">Hier können Mittagessen im Buffet bestellt werden. Derzeit nur CDGym.</v-card-subtitle>

                        <v-card-actions class="mt-auto">
                            <v-btn class="ms-2" size="small" text="LOS" variant="outlined" href="https://cdgym.info/lunch" target></v-btn>
                        </v-card-actions>
                    </v-card>
                </div>
            </v-card-text>
        </v-card>

        <!-- Schulauswahl -->

        <v-card tile flat color="transparent" class="border-md w-100" max-width="600" v-if="step == 'selectSchool' && !selected_school">
            <v-card-text>
                <div class="d-flex flex-column flex-wrap justify-center ga-4">
                    <v-card color="third" max-width="600" class="d-flex flex-column w-100" v-if="licence">
                        <v-card-title class="text-h5">{{ licence.name }}</v-card-title>

                        <v-card-subtitle style="white-space: normal">{{ licence.long_name }}</v-card-subtitle>

                        <v-card-text>
                            <div class="text-h6">Bitte wähle die Schule aus</div>
                            <v-autocomplete v-model="selected_school_id" :items="schools" item-title="long_name" item-value="id" label="Auswahl Schule" />
                        </v-card-text>

                        <v-card-actions class="mt-auto">
                            <v-btn class="ms-2" size="small" text="Zurück" color="warning" variant="flat" @click="abort('')" />
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
        <v-card>{{ licence }}</v-card>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export default {
    components: {},

    async beforeMount() {
        this.homepageStore = useHomepageStore()
        this.school_name = this.$route.query.school
        this.app_name = this.$route.query.app
        if (!this.school) {
            await this.homepageStore.loadConfig(this.school_name, this.app_name)
        }
        this.selected_school = null
        this.selected_school_id = null
    },

    mounted() {},

    unmounted() {},

    data() {
        return {
            homepageStore: null,
            letters: 'SCHOOLTOOL'.split(''),
            step: '',
        }
    },

    computed: {
        ...mapWritableState(useHomepageStore, ['config', 'is_loading', 'schools', 'licence', 'selected_school', 'selected_school_id']),
    },

    watch: {
        selected_school_id() {
            this.selected_school = this.schools.find((item) => item.id == this.selected_school_id)
        },
    },

    methods: {
        moveTo(licence, school) {
            var path = '/homepage/'
            switch (licence.name) {
                case 'Anmeldetool':
                    path += 'register/'
                    break
                case 'Tutoring':
                    path += 'tutoring/'
                    break
            }
            path += '?school=' + school.short_name

            this.$router.push(path)
        },
        abort(step) {
            this.selected_school = null
            this.selected_school_id = null
            this.step = step
        },

        async loadSchoolsForTool(tool) {
            await this.homepageStore.loadSchoolsForTool(tool)
            this.step = 'selectSchool'
        },
    },
}
</script>

<style scoped>
.schooltool-background {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    pointer-events: none;
    z-index: 0;
}

.text-container {
    display: flex;
    gap: 0;
    align-items: flex-end; /* Bottom-Ausrichtung */
}

.letter {
    font-size: clamp(3.6rem, 9vw, 18rem); /* 90% Größe als Default */
    font-weight: 900;
    color: rgba(0, 0, 0, 0.05);
    text-transform: uppercase;
    font-family: 'Arial Black', sans-serif;
    animation: wave 5.5s ease-in-out infinite;
    user-select: none;
}

.letter:nth-child(1) {
    font-size: clamp(4rem, 10vw, 20rem); /* S - volle Größe */
    color: rgba(243, 146, 55, 0.15);
}

.letter:nth-child(7) {
    font-size: clamp(4rem, 9.5vw, 20rem); /* T - volle Größe */
    color: rgba(100, 171, 57, 0.15);
}

@keyframes wave {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-20px);
    }
}
</style>
