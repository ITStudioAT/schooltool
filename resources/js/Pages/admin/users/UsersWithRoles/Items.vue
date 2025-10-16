<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-row class="d-flex flex-row ga-2 mb-2 mt-0 w-100" no-gutters>
            <its-menu-button subtitle="Benutzer" icon="mdi-arrow-left" color="secondary" to="/admin/users" />
            <its-menu-button subtitle="Rollen" icon="mdi-arrow-left" color="secondary" to="/admin/users/roles" />
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
                *
                * EVENTS you may listen
                * @updated
                * @stored
                * @destroyed
                * @multiple_destroyed
                **********************************************-->
                <its-table :icon="icon" color="primary"
                    :title="is_show_or_edit && data ? data.id ? 'Rollen von ' + data.last_name + ' ' + data.first_name : title_new : title"
                    :search_options="search_options" :model="this.model" :multiple="this.multiple" :data="data"
                    :data_multiple="data_multiple" :save_action="save_action" :destroy_action="destroy_action"
                    :destroy_multiple_action="destroy_multiple_action" :select_all="select_all"
                    :show_search_field="show_search_field" :reload="userWithRoleStore.reload">

                    <!-- Menu on the top -->
                    <!-- You can comment out any of these actions  or the whole template-->
                    <!-- You can add more actions, the selected items are in the var selected_items 
                    <template v-slot:menu="{ selected_items }">
                        <v-card-text class="d-flex flex-row align-center ga-2">
                            <v-checkbox hide-details v-model="select_all"></v-checkbox>
                            <v-btn prepend-icon="mdi-plus" flat rounded="0" color="success" @click="add">Neu</v-btn>
                            <v-btn :prepend-icon="selected_items.length == 1 ? 'mdi-delete' : 'mdi-delete-sweep'" flat
                                rounded="0" color="error" v-if="selected_items.length > 0"
                                @click="destroyMultiple(selected_items)">Löschen</v-btn>
                        </v-card-text>
                    </template>
-->

                    <!-- Show item (=one line) -->
                    <template v-slot:content="{ item }">
                        <v-col cols="12" lg="6">{{ item.last_name + ' ' + (item.first_name || '')
                        }}</v-col>
                        <v-col cols="12" lg="6">{{ item.roles }}</v-col>
                    </template>

                    <!-- Menu for each item-->
                    <!-- You can comment out any of these actions or the whole template-->
                    <!-- You can add more actions, the selected item is in the var item -->
                    <template v-slot:actions="{ item }">
                        <v-btn icon="mdi-dots-horizontal" color="success" @click="show(item)"></v-btn>
                        <v-btn icon="mdi-close" color="warning"></v-btn>
                    </template>

                    <!-- Show, Edit or New Record-->
                    <template v-slot:show="{ abortShow }" v-if="is_show_or_edit">
                        <!--ItemShow.vue -->
                        <item-show :item="data" :roles="userWithRoleStore.roles" @abortShow="is_show_or_edit = false"
                            @save="save" />
                    </template>

                </its-table>
            </v-col>
        </v-row>


    </v-container>
</template>
<script>
import { useUserWithRoleStore } from "@/stores/admin/UserWithRoleStore";
import ItsMenuButton from "@/pages/components/ItsMenuButton.vue";
import ItsTable from "@/pages/components/ItsTable.vue";
import ItemShow from "./ItemShow.vue";

export default {
    components: { ItsMenuButton, ItsTable, ItemShow },
    async beforeMount() {
        this.userWithRoleStore = useUserWithRoleStore();
        await this.userWithRoleStore.loadRoles();
        var search_option = {
            'type': 'toggle',
            'field': 'role',
            'options': [],
        }
        var i = 0;
        this.userWithRoleStore.roles.forEach(role => {
            if (i == 0) {
                search_option.options.push({ 'name': role.name, 'value': role.name, 'default': true });
            } else {
                search_option.options.push({ 'name': role.name, 'value': role.name });
            }

            i++;
        });
        this.search_options.push(search_option);
    },



    data() {
        return {
            userWithRoleStore: null,
            model: 'users_with_roles', // The used model
            multiple: true, // multi-selection of records (for deletion)
            title: 'Benutzer mit Rollen', // Title, if all records are shown
            title_new: 'Neuer Benutzer', // Title for a new record
            icon: 'mdi-relation-one-to-many', // Icon shown directly before the title
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

            search_options: [],
            save_action: 0,
            destroy_action: 0,
            destroy_multiple_action: 0,
            add_action: 0,
            select_all: false,
            is_show_or_edit: false,
            data: {},
            data_multiple: [],
            event: null,


        };
    },

    methods: {



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
