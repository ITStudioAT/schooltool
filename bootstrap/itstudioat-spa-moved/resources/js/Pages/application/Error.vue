<template>
    <v-empty-state headline="Whoops!" :title="message ? message : 'Es ist ein Fehler aufgetreten'"
        :text="status ? 'Die Fehlernummer lautet:' + status : ''" image="/storage/images/logo.png"></v-empty-state>
</template>
<script>
export default {
    async beforeMount() {
        this.status = this.$route.query.status;
        this.message = this.$route.query.message;
    },

    data() {
        return {
            status: null,
            message: null,
        };
    },


}
</script>