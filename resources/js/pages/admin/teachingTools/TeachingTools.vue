<template>
    <v-container fluid class="teaching-page ma-0 w-100 pa-2">
        <AdminPageHeader class="mb-3" location="Unterricht Tools" />

        <v-sheet class="teaching-nav mb-2" aria-label="Unterricht Tools">
            <v-btn-toggle model-value="multiple-choice" mandatory divided color="primary" class="teaching-nav__buttons">
                <v-btn
                    value="multiple-choice"
                    to="/admin/teaching-tools/multiple-choice"
                    prepend-icon="mdi-format-list-checks"
                    :aria-pressed="true"
                    class="teaching-nav__button">
                    <span class="teaching-nav__button-copy">
                        <span class="teaching-nav__button-title">Multiple Choice</span>
                    </span>
                </v-btn>
            </v-btn-toggle>
        </v-sheet>
    </v-container>
</template>

<script setup>
import AdminPageHeader from '@/pages/admin/components/AdminPageHeader.vue'
</script>

<style scoped>
.teaching-nav {
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.teaching-nav__buttons {
    width: 100%;
    flex-wrap: wrap;
    row-gap: 6px;
    height: auto !important;
    min-width: 0;
}

.teaching-nav__button {
    min-height: 56px !important;
    height: auto !important;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
}

.teaching-nav__button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.teaching-nav__button-title {
    font-weight: 650;
}
</style>
