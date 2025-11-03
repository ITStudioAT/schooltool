<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-row class="d-flex flex-row ga-2 mb-2 mt-0 w-100" no-gutters>
            <its-menu-button subtitle="Benutzer" icon="mdi-arrow-left" color="secondary" to="/admin/users" />
        </v-row>

        <!-- TABELLE -->
        <v-row class="w-100" no-gutters>
            <v-col cols="12" md="6" xl="4">
                <!--*********************************************
                * ITS-TABLE
                * title: title of the table ==> change this, what you want to be displayed, if one record is shown
                * search_options: All search option (=> see data-section <= may be changed) (dont change here)
                * model: the used model (dont change here)
                * multiple: If more records can be selected (=> see data-section <= may be changed) (dont change here)
                * data: The record for action (dont change here)
                * data_multiple: A list of records for action (dont change here)
                * save_action, destroy_action, destroy_multiple_action, select_all : "events" to run the action in the component (dont change here)
                * show_search_field: indicates if the search_field is displayed
                * If you dont want any search option, set search_options : [],
                * reload : model will be reloaded if reload is increased
                * EVENTS you may listen
                * @updated
                * @stored
                * @destroyed
                * @multiple_destroyed
                **********************************************-->
                <its-table :icon="icon" color="primary"
                    :title="is_show_or_edit && data ? data.id ? data.last_name + ' ' + data.first_name : title_new : title"
                    :search_options="search_options" :model="this.model" :multiple="this.multiple" :data="data"
                    :data_multiple="data_multiple" :save_action="save_action" :destroy_action="destroy_action"
                    :destroy_multiple_action="destroy_multiple_action" :select_all="select_all"
                    :show_search_field="show_search_field" :reload="reload_table">

                    <!-- Menu on the top -->
                    <!-- You can comment out any of these actions  or the whole template-->
                    <!-- You can add more actions, the selected items are in the var selected_items -->
                    <template v-slot:menu="{ selected_items }">
                        <v-card-text class="d-flex flex-row flex-wrap  align-center ga-2">
                            <v-checkbox hide-details v-model="select_all"></v-checkbox>


                            <v-btn prepend-icon="mdi-eye" flat tile class="text-caption"
                                :color="show_user_roles ? 'accent' : 'secondary'"
                                @click="show_user_roles = !show_user_roles" v-if="hasRole(['admin'])">Rollen</v-btn>

                            <v-btn prepend-icon="mdi-plus" flat rounded="0" color="success" @click="add">Neu</v-btn>
                            <v-btn prepend-icon="mdi-gesture-tap" flat rounded="0" color="success"
                                @click="confirm(selected_items)" v-if="selected_items.length > 0">Bestätigen</v-btn>
                            <v-btn prepend-icon="mdi-email-seal-outline" flat rounded="0" color="success"
                                @click="sendVerificationEmail(selected_items)" v-if="selected_items.length > 0">V-E-Mail
                                senden</v-btn>
                            <v-btn prepend-icon="mdi-relation-one-to-many" flat rounded="0" color="success"
                                @click="showRoles" v-if="selected_items.length > 0 && hasRole(['admin'])">Rollen
                                zuordnen</v-btn>
                            <v-btn :prepend-icon="selected_items.length == 1 ? 'mdi-delete' : 'mdi-delete-sweep'" flat
                                rounded="0" color="error" v-if="selected_items.length > 0"
                                @click="destroyMultiple(selected_items)">Löschen</v-btn>
                        </v-card-text>
                    </template>

                    <!-- Show item (=one line) -->
                    <template v-slot:content="{ item }">
                        <v-col cols="12" lg="4">{{ item.last_name + ' ' + (item.first_name || '')
                            }}</v-col>
                        <v-col cols="12" lg="5">{{ item.email }}</v-col>
                        <v-col cols="12" lg="3" class="d-flex align-center justify-space-between">
                            <v-icon :icon="item.is_active ? 'mdi-lock-open-check' : 'mdi-lock'"
                                :color="item.is_active ? 'success' : 'error'" />
                            <v-icon :icon="item.is_confirmed ? 'mdi-check' : 'mdi-gesture-tap'"
                                :color="item.is_confirmed ? 'success' : 'error'" />
                            <v-icon :icon="item.is_verified ? 'mdi-email-seal' : 'mdi-email-fast'"
                                :color="item.is_verified ? 'success' : 'error'" />
                            <v-icon :icon="item.is_2fa ? 'mdi-two-factor-authentication' : 'mdi-form-textbox-password'"
                                :color="item.is_2fa ? 'success' : 'warning'" />
                        </v-col>
                        <v-col cols="12" class="d-flex flex-row flex-wrap ga-2" v-if="show_user_roles">
                            <v-icon color="primary" icon="mdi-badge-account-horizontal-outline" size="small" />
                            <div class="text-caption" v-for="(role, i) in item.roles" v-if="item.roles.length > 0">{{
                                role }}</div>
                            <div v-if="item.roles.length == 0">--</div>
                        </v-col>
                    </template>

                    <!-- Menu for each item-->
                    <!-- You can comment out any of these actions or the whole template-->
                    <!-- You can add more actions, the selected item is in the var item -->
                    <template v-slot:actions="{ item }">
                        <v-btn icon="mdi-dots-horizontal" color="success" @click="show(item)"></v-btn>
                        <v-btn flat icon="mdi-delete" color="error" @click="destroy(item)"></v-btn>
                        <v-btn icon="mdi-close" color="warning"></v-btn>
                    </template>


                    <!-- Show, Edit or New Record-->
                    <template v-slot:show="{ abortShow }" v-if="is_show_or_edit">
                        <!--ItemShow.vue -->
                        <item-show :item="data" @abortShow="is_show_or_edit = false" @save="save" />
                    </template>

                </its-table>
            </v-col>
        </v-row>
        <!-- ROLLEN ZUORDNEN-->
        <its-overlay-box title="Rollen zuordnen" color="primary" v-if="is_show_roles">
            <template #content>

                <v-list>
                    <v-list-item v-for="(role, i) in roles" v-if="userWithRoleStore">
                        <v-row>
                            <v-col cols=" 10">
                                {{ role.name }}
                            </v-col>
                            <!-- Menü -->
                            <v-col cols="2" class="d-flex justify-end">
                                <v-btn flat rounded="0" @click="roleClick(role)"><v-icon :color="roleColor(role)"
                                        :icon="roleIcon(role)" /></v-btn>
                            </v-col>
                        </v-row>
                    </v-list-item>
                </v-list>

                <v-row no-gutters class=" pt-2">
                    <v-col cols="6">
                        <v-btn append-icon="mdi-content-save" block color="success" slim flat rounded="0"
                            @click="saveUserRoles(modelStore.selected_items, roles)">Speichern</v-btn>

                    </v-col>
                    <v-col cols="6">
                        <v-btn append-icon="mdi-close" block color="error" slim flat rounded="0"
                            @click="abortShowRoles">Abbruch</v-btn>
                    </v-col>
                </v-row>

            </template>

        </its-overlay-box>

        {{ adminStore.config.user }}

    </v-container>
</template>
<script>
import { useModelStore } from "@/stores/admin/ModelStore";
import { useUserStore } from "@/stores/admin/UserStore";
import { useAdminStore } from "@/stores/admin/AdminStore";
import { useUserWithRoleStore } from "@/stores/admin/UserWithRoleStore";
import ItsOverlayBox from "@/pages/components/ItsOverlayBox.vue";
import ItsMenuButton from "@/pages/components/ItsMenuButton.vue";
import ItsTable from "@/pages/components/ItsTable.vue";
import ItemShow from "./ItemShow.vue";

export default {
    components: { ItsMenuButton, ItsTable, ItemShow, ItsOverlayBox },
    async beforeMount() {
        this.userStore = useUserStore(); this.userStore.initialize(this.$router);
        this.adminStore = useAdminStore();
        this.modelStore = useModelStore();
        this.modelStore.selected_items = [];
        this.userWithRoleStore = useUserWithRoleStore();
        await this.userWithRoleStore.loadRoles();
        this.initRoles(this.userWithRoleStore.roles);
    },

    data() {
        return {
            userStore: null,
            adminStore: null,
            modelStore: null,
            userWithRoleStore: null,
            model: 'users', // The used model
            multiple: true, // multi-selection of records (for deletion)
            title: 'Alle Benutzer', // Title, if all records are shown
            title_new: 'Neuer Benutzer', // Title for a new record
            icon: 'mdi-account-group', // Icon shown directly before the title
            show_search_field: true, // Searach field should be shown
            /********************************************* 
             * The search_options represents the filters of the search request
             * Each search_option is displayed on the items-screen and the user may select it
             * 'type':
             *      toggle: Toggle-Button (one of the buttons must be pushed)
             * 'field': name of the field
             * 'options':
             *      'name': Name of the option
             *      'value': Value of the option
             *      'default':true: if the option is the default pushed option
             * 
             * If you dont want any search option, set search_options : [],
            **********************************************/

            search_options: [
                {
                    'type': 'toggle',
                    'field': 'is_active',
                    'options': [
                        { 'name': 'Alle', 'value': 0, 'default': true },
                        { 'name': 'Aktive', 'value': 1, },
                        { 'name': 'Nicht aktive', 'value': 2 }
                    ]
                },
                {
                    'type': 'toggle',
                    'field': 'is_confirmed',
                    'options': [
                        { 'name': 'Alle', 'value': 0, 'default': true },
                        { 'name': 'Bestätigte', 'value': 1, },
                        { 'name': 'Nicht Bestätigte', 'value': 2 }
                    ]
                },
                {
                    'type': 'toggle',
                    'field': 'is_verified',
                    'options': [
                        { 'name': 'Alle', 'value': 0, 'default': true },
                        { 'name': 'Verifizierte', 'value': 1, },
                        { 'name': 'Nicht Verifizierte', 'value': 2 }
                    ]
                },
                {
                    'type': 'toggle',
                    'field': 'is_2fa',
                    'options': [
                        { 'name': 'Alle', 'value': 0, 'default': true },
                        { 'name': '2-Fakoren', 'value': 1 },
                        { 'name': 'Ohne 2-Faktoren', 'value': 2 }
                    ]
                },

            ],
            save_action: 0,
            destroy_action: 0,
            destroy_multiple_action: 0,
            add_action: 0,
            select_all: false,
            is_show_or_edit: false,
            data: {},
            data_multiple: [],
            event: null,
            is_show_roles: false,
            roles: [],
            show_user_roles: false,
            reload_table: 0,



        };
    },

    methods: {

        hasRole(p_roles) {
            const roles = Array.isArray(p_roles) ? p_roles : [p_roles];
            const userRoles = this.adminStore?.config?.user?.roles || [];
            if (!roles.includes('super_admin')) { roles.push('super_admin'); }

            return roles.some(role => userRoles.includes(role));
        },


        async saveUserRoles(users, roles) {
            const user_ids = users.map(item => item.id);
            const role_ids = roles
                .filter(item => item.role_check === 1 || item.role_check === 2)
                .map(item => ({ id: item.id, role_check: item.role_check }));

            const answer = this.userStore.saveUserRoles(user_ids, role_ids);
            this.reload_table++;
            this.is_show_roles = false;

        },

        roleColor(role) {
            switch (role.role_check) {
                case 0: return 'primary'; break;
                case 1: return 'success'; break;
                case 2: return 'error'; break;
            }
        },

        roleIcon(role) {
            switch (role.role_check) {
                case 0: return 'mdi-dots-square'; break;
                case 1: return 'mdi-check-bold'; break;
                case 2: return 'mdi-close-thick'; break;
            }
        },

        initRoles(roles) {
            this.roles = roles.map(role => ({
                ...role,
                role_check: 0
            }));

        },

        roleClick(role) {
            role.role_check++;
            if (role.role_check === 3) {
                role.role_check = 0;
            }
        },

        showRoles() {
            this.initRoles(this.userWithRoleStore.roles);
            this.is_show_roles = true;
        },
        abortShowRoles() {
            this.is_show_roles = false;
        },

        async confirm(items) {
            const ids = items.map(item => item.id);
            await this.userStore.confirm(ids);
        },

        async sendVerificationEmail(items) {
            const ids = items.map(item => item.id);
            await this.userStore.sendVerificationEmail(ids);

        },

        save(data) {
            this.data = data;
            this.save_action++;
        },

        show(item) {
            this.data = item;
            this.is_show_or_edit = true;
        },

        add() {
            this.data = {};
            this.is_show_or_edit = true;
        },

        destroy(item) {
            this.data = item;
            this.destroy_action++;
        },

        destroyMultiple(items) {
            this.data_multiple = items;
            this.destroy_multiple_action++;
        }

    }

}
</script>
