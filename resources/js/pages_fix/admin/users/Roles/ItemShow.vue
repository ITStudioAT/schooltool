<template>
    <v-card flat rounded="0">
        <v-card-text>
            <v-form ref="form" v-model="is_valid" :disabled="!is_edit">
                <v-text-field autofocus v-model="data.name" label="Name der Rolle"
                    :rules="[required(), maxLength(255)]" />
            </v-form>

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

export default {
    setup() { return useValidationRulesSetup(); },

    props: ['item', 'saved'],

    async beforeMount() {
        this.data = JSON.parse(JSON.stringify(this.item));
        if (!this.data.id) this.is_edit = true;
    },

    data() {
        return {
            data: {},
            is_edit: false,
            is_valid: false,
        };
    },


    methods: {
        async save(data) {
            this.is_valid = false;
            await this.$refs.form.validate(); if (!this.is_valid) return;
            this.is_edit = false;
            this.$emit('save', data);
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
