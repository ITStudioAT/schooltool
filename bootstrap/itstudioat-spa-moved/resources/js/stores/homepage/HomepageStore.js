import { defineStore } from 'pinia'

export const useHomepageStore = defineStore("HomepageStore", {

    state: () => {
        return {
            router: null,
            config: null,
            is_loading: 0,
            error: {
                is_error: false,
                status: null,
                message: null,
                timeout: 3000,
            }

        }
    },

    actions: {
        initialize(router) {
            this.router = router;
        },

        async config(router) {
            this.is_loading++;
            try {
                const response = await axios.get("/api/homepage/config", {});
                this.config = response.data;
            } catch (error) {
                this.redirect(error.response.status, error.response.data.message, 'error');
            } finally {
                this.is_loading--;
            }
        },


        redirect(status, message, type) {
            const redirectUrl = '/application/error?status=' + status + '&message=' + encodeURIComponent(message) + '&type=' + type;
            window.location.href = redirectUrl; // This is a real redirect
        }



    }
})

