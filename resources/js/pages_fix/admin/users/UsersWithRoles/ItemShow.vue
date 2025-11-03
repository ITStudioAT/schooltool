<template>
    <v-card flat rounded="0">
        <v-card-text>
            <v-list :disabled="!is_edit">
                <v-list-item v-for="(role, i) in roles" :class="!is_edit ? 'disabled-opacity' : ''">
                    <v-row>
                        <v-col cols=" 10">
                            {{ role.name }}
                        </v-col>
                        <!-- Menü -->
                        <v-col cols="2" class="d-flex justify-end">
                            <v-btn flat rounded="0" v-if="hasRole(role.name)" @click="removeRole(role.name)"><v-icon
                                    color="success" icon="mdi-check-bold" /></v-btn>
                            <v-btn flat rounded="0" v-if="!hasRole(role.name)" @click="addRole(role.name)"><v-icon
                                    color="error" icon="mdi-close-thick" /></v-btn>
                        </v-col>
                    </v-row>
                </v-list-item>
            </v-list>
            <v-row no-gutters>
                <v-col cols=12 sm=6>
                    <v-btn append-icon="mdi-pencil" block color="success" slim flat rounded="0" @click="edit"
                        v-if="!is_edit">Ändern</v-btn>
                    <v-btn append-icon="mdi-content-save" block color="success" slim flat rounded="0"
                        @click="save(data)" v-if="is_edit">Speichern</v-btn>
                </v-col>
                <v-col cols=12 sm=6>
                    <v-btn append-icon="mdi-arrow-left" block color="primary" slim flat rounded="0"
                        @click="$emit('abortShow')" v-if="!is_edit">Übersicht</v-btn>
                    <v-btn append-icon="mdi-close" block color="error" slim flat rounded="0" @click="abortEdit"
                        v-if="is_edit">Abbruch</v-btn>
                </v-col>
            </v-row>
        </v-card-text>

    </v-card>
</template>


<script>
import { useValidationRulesSetup } from "@/helpers/rules";
import { useUserWithRoleStore } from "@/stores/admin/UserWithRoleStore";

export default {
    setup() { return useValidationRulesSetup(); },

    props: ['item', 'saved', 'roles'],

    async beforeMount() {
        this.userWithRoleStore = useUserWithRoleStore();
        this.data = JSON.parse(JSON.stringify(this.item));
        if (!this.data.id) this.is_edit = true;
    },

    data() {
        return {
            userWithRoleStore: null,
            data: {},
            is_edit: false,
            is_valid: false,
        };
    },


    methods: {

        hasRole(role) {
            return this.data.roles.includes(role);
        },
        removeRole(role_to_remove) {
            this.data.roles = this.data.roles.filter(role => role !== role_to_remove);
        },

        addRole(role) {
            this.data.roles.push(role);
        },


        async save(data) {
            this.userWithRoleStore.saveUserRoles(data);
            this.userWithRoleStore.reload++;
            console.log("userWithRoleStore.reload: " + this.userWithRoleStore.reload);

            this.is_edit = false;
            this.$emit('abortShow');

        },

        edit() {
            this.data = JSON.parse(JSON.stringify(this.item));
            this.is_edit = true;
        },

        abortEdit() {
            this.data = JSON.parse(JSON.stringify(this.item));
            this.is_edit = false;
        },


    }


}
</script>
