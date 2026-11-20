<template>
    <span class="d-inline-flex flex-column align-center" role="img"
        :aria-label="`Höchstwert der Leistungsfeststellung ${assessmentPoints}, Zielwert auf der Schooltool-Bewertungsskala der Arbeit ${workPoints}`">
        <span aria-hidden="true">{{ assessmentPoints }}</span>
        <span aria-hidden="true">↓</span>
        <span aria-hidden="true">{{ workPoints }}</span>
    </span>
</template>

<script setup>
defineProps({ assessmentPoints: { type: String, required: true }, workPoints: { type: String, required: true } })
</script>
