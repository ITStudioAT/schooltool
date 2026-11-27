<template>
    <header class="school-entry-header">
        <div class="school-entry-shell school-entry-header-inner">
            <div class="school-entry-brand">
                <img src="/storage/images/schooltool/schooltool-mark.svg" width="32" height="32" alt="" />
                <span>schooltool<span class="school-entry-brand-dot">.</span></span>
            </div>
            <nav v-if="showDocumentation" aria-label="Navigation">
                <a href="/documentation" target="_blank" rel="noopener noreferrer" class="school-entry-docs">
                    <v-icon icon="mdi-book-open-outline" size="19" aria-hidden="true" />
                    Dokumentation
                </a>
            </nav>
            <slot />
        </div>
    </header>
</template>

<script setup>
defineProps({
    showDocumentation: Boolean,
})
</script>

<style src="../../../../css/school-entry.css"></style>
