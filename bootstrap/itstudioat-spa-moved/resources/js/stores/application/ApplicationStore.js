import { defineStore } from 'pinia'

export const useApplicationStore = defineStore("ApplicationStore", {

    state: () => {
        return {
            router: null,
            is_loading: 0,
            error: {
                is_error: false,
                status: null,
                message: null,
                timeout: 3000,
                type: 'error'
            }

        }
    },

    actions: {
        initialize(router) {
            this.router = router;
        },




    }
})

