<template>
    <v-container fluid class="ma-0 w-100 pa-2">

        <v-row class="w-100" no-gutters>
            <v-col cols="12" md="6" lg="4" xl="3">
                <its-grid-box color="primary" title="Laravel-Spa" class="h-100 w-100">
                    <div class="text-body-1">
                        <div>
                            Laravel-Spa ist der Startpunkt für eine Laravel Single Page Applikation.
                        </div>
                        <div class="my-2">
                            Es beinhaltet alle notwendigen Setups, um eine Laravel-SPA-Projekt beginnen zu können
                        </div>
                    </div>

                    <v-list density="compact" class="text-body-2">
                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Vue with Vue-Router als Javascript-Framework inklusive dem Routing
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Pinia als Vue-State-Store
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Vuetify als CSS-Framework
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Sanctum als Authentifizierungs-System für SPAs
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Vite als Asset-Bundling
                        </v-list-item>

                    </v-list>


                </its-grid-box>
            </v-col>



            <v-col cols="12" md="6" lg="4" xl="3">
                <its-grid-box color="primary" title="Standardseiten" class="h-100 w-100">
                    <div class="text-body-1 mb-2">
                        Standardmäßig stehen folgende Seiten fix und fertig zur Verfügung:
                    </div>


                    <v-list density="compact" class="text-body-2">
                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Eine Login-Seite
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Eine 'Kennwort vergessen'-Seite
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Eine 'Neu registrieren'-Seite
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Das Login kann optional als 2-Faktoren-Authentifizierung (Kennwort und E-Mail) vom
                            Benutzer gewählt werden
                        </v-list-item>

                    </v-list>


                </its-grid-box>
            </v-col>

            <v-col cols="12" md="6" lg="4" xl="3">
                <its-grid-box color="primary" title="Dashboard" class="h-100 w-100">
                    <div class="text-body-1 mb-2">
                        Es steht auch ein Dashboard mit folgenden Seiten zur Verfügung:
                    </div>


                    <v-list density="compact" class="text-body-2">
                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Home (diese Seite wird gerade angezeigt)
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Dashboard (diese Seite ist nicht weiter implementiert)
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Benutzer (Verwaltung über alle Benutzer) inkl. Rollen-Management
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Profil (Verwalten des eigenen Profils inkl. Kennwort ändern)
                        </v-list-item>

                        <v-list-item density="compact">
                            <template #prepend>
                                <v-icon size="small" icon="mdi-check-bold" color="success" />
                            </template>
                            Abmelden
                        </v-list-item>

                    </v-list>


                </its-grid-box>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from "pinia";
import { useAdminStore } from "@/stores/admin/AdminStore";
import ItsMenuButton from "@/pages/components/ItsMenuButton.vue";
import ItsGridBox from "@/pages/components/ItsGridBox.vue";

export default {

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
    },

    unmounted() {
    },

    data() {
        return {

        };
    },


    computed: {

    },

    methods: {


    }

}
</script>
