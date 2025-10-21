<template>
    <v-container fluid class="h-100 w-100 d-flex flex-column align-center justify-center bg-background" v-if="config">
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary">
            <v-card-title class="d-flex flex-row align-center">
                <img :src="`/storage/images/${school?.logo}`" alt="Logo" class="logo" v-if="school?.logo" />
                <div class="ml-2"></div>
            </v-card-title>
            <!-- ANZEIGE DER SCHULE-->
            <v-card-subtitle class="d-flex flex-row align-center justify-space-between" v-if="school?.long_name">
                <div>
                    {{ school?.long_name }}
                </div>
                <v-btn
                    flat
                    size="small"
                    icon
                    color="primary"
                    @click="selected_school_id = null"
                    v-if="config.selectableSchools.length > 1">
                    <v-icon icon="mdi-close" />
                </v-btn>
            </v-card-subtitle>

            <!-- AUSWAHL DER SCHULE (wenn nicht ausgewählt)-->
            <v-card-text v-if="!school">
                <div class="text-h6">Bitte die Schule auswählen</div>
                <v-autocomplete
                    v-model="selected_school_id"
                    :items="config.selectableSchools"
                    item-title="long_name"
                    item-value="id"
                    label="Auswahl Schule" />
            </v-card-text>

            <!-- AUSWAHL DER LICENCE (wenn nicht ausgewählt)-->
            <v-card-text v-if="school && !licence && config.schoolLicences.length > 0">
                <div class="text-h6">Bitte die App auswählen</div>
                <v-autocomplete
                    v-model="selected_licence_id"
                    :items="config?.schoolLicences"
                    item-title="long_name"
                    item-value="id"
                    label="Auswahl App" />
            </v-card-text>

            <!-- ANZEIGE DER LICENCE -->
            <v-card-text v-if="licence">
                <div class="d-flex flex-row align-center justify-space-between">
                    <div class="text-body-1 font-weight-medium">
                        {{ licence.long_name }}
                    </div>
                    <v-btn
                        flat
                        size="small"
                        icon
                        color="primary"
                        @click="selected_licence_id = null"
                        v-if="config.schoolLicences.length > 1">
                        <v-icon icon="mdi-close" />
                    </v-btn>
                </div>
                <div>App</div>
            </v-card-text>

            <!-- MENÜ -->
            <v-card-actions class="d-flex flex-column justify-center text-body-1 font-weight-medium">
                <v-btn
                    tile
                    flat
                    variant="outlined"
                    v-if="selected_school_id && selected_licence_id"
                    :href="'/homepage/register?school_id=' + school.id + '&licence_id=' + licence.id">
                    Weiter
                </v-btn>
                <div v-if="config?.selectableSchools?.length == 0">Es kann keine Schule ausgewählt werden!</div>
                <div v-if="selected_school_id && config?.schoolLicences?.length == 0">
                    Es kann gibt keine Apps zum Auswählen!
                </div>
            </v-card-actions>
        </v-card>
    </v-container>
</template>
<script>
import { ref } from 'vue'
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
export default {
    components: {},

    async beforeMount() {
        this.homepageStore = useHomepageStore()
        this.school_name = this.$route.query.school
        this.app_name = this.$route.query.app
        this.homepageStore.loadConfig(this.school_name, this.app_name)
    },

    unmounted() {},

    data() {
        return {
            homepageStore: null,
            is_more_content: false,

            school_name: '',
            app_name: '',
        }
    },

    computed: {
        ...mapWritableState(useHomepageStore, [
            'config',
            'is_loading',
            'error',
            'school',
            'licence',
            'selected_school_id',
            'selected_licence_id',
        ]),
    },

    watch: {
        selected_school_id() {
            if (this.selected_school_id) {
                this.school = this.config?.selectableSchools.find((s) => s.id === this.selected_school_id)
                this.homepageStore.loadConfig(this.school?.short_name, this.app_name)
            } else {
                this.school = null
                this.homepageStore.loadConfig(null, this.app_name)
            }
        },
        selected_licence_id() {
            if (this.selected_licence_id) {
                this.licence = this.config?.schoolLicences.find((s) => s.id === this.selected_licence_id)
                this.homepageStore.loadConfig(this.school?.short_name, this.licence.name)
            } else {
                this.licence = null
            }
        },
    },
    methods: {},
}
</script>
<style scoped>
.logo {
    display: block;
    max-height: 90px; /* or 2em, relative to font size */
    height: auto;
    width: auto;
    object-fit: contain;
}
</style>
