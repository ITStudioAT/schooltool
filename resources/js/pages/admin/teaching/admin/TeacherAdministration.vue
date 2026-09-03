<template>
    <v-col cols="12" class="pb-1">
        <section class="teaching-administration-toolbar">
            <v-btn-toggle model-value="teachers" mandatory color="primary">
                <v-btn value="teachers" prepend-icon="mdi-account-tie" class="text-none">
                    Lehrer
                </v-btn>
            </v-btn-toggle>
        </section>
    </v-col>

    <Teachers :hide-back-button="true" />
</template>

<script setup>
import Teachers from '@/pages/admin/superAdmin/components/Teachers.vue'
</script>

<style scoped>
.teaching-administration-toolbar {
    width: 100%;
    display: flex;
    justify-content: flex-start;
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
}
</style>
