<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-row class="d-flex flex-row ga-2 mb-2 mt-0 w-100" no-gutters>
            <its-menu-button :title="item.title" :subtitle="item.subtitle" :icon="item.icon" :to="item.to"
                :color="item.color" @click="runAction(item.action)" v-for="(item, i) in navigationStore.menu" />
        </v-row>
        <v-row class="w-100" no-gutters>
            <v-col cols="12" sm="4" md="3" xl="2">
                <ItsInfoBox color="primary" :title="item.title" :icon="item.icon" :infos="item.infos" :url="item.url"
                    v-for="(item, i) in navigationStore.selection">
                </ItsInfoBox>
            </v-col>
        </v-row>
        <v-row>
            {{ config }}
        </v-row>

    </v-container>
</template>
<script>
import { useValidationRulesSetup } from "@/helpers/rules";
import { mapWritableState } from "pinia";
import { useAdminStore } from "@/stores/admin/AdminStore";
import { useUserStore } from "@/stores/admin/UserStore";
import { useNavigationStore } from "@/stores/admin/NavigationStore";
import ItsMenuButton from "@/pages/components/ItsMenuButton.vue";
import ItsInfoBox from "@/pages/components/ItsInfoBox.vue";


export default {

    setup() { return useValidationRulesSetup(); },

    components: { ItsMenuButton, ItsInfoBox },

    async beforeMount() {
        this.adminStore = useAdminStore(); this.adminStore.initialize(this.$router);
        this.userStore = useUserStore(); this.userStore.initialize(this.$router);
        this.navigationStore = useNavigationStore();
        await this.navigationStore.loadMenu('user_menu');
    },

    unmounted() {
    },

    data() {
        return {
            adminStore: null,
            userStore: null,
            navigationStore: null,

        };
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'show_navigation_drawer', 'load_config', 'user_roles']),
        ...mapWritableState(useUserStore, ['user', 'api_answer']),
    },

    methods: {

        runAction(methodName) {
            if (typeof this[methodName] === 'function') {
                this[methodName]();
            }
        }


    }

}
</script>
