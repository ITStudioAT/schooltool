<template>
    <v-card class="materials-shell pa-6" rounded="xl" elevation="0">
        <div class="mb-5">
            <h2 class="text-h5 font-weight-bold mb-1">Berechtigungen</h2>
            <div class="text-subtitle-1 subline">Überblick über die verfügbaren Berechtigungsstufen beim Teilen von Materialien.</div>
        </div>

        <div class="materials-permissions-list">
            <section class="materials-permissions-item">
                <h3 class="text-h6 font-weight-bold mb-2">Lesen</h3>
                <p class="mb-1">Der Empfänger kann die geteilten Inhalte ausschließlich lesen und zu seinem Workspace hinzufügen.</p>
                <p class="mb-0">Änderungen sind gänzlich ausgeschlossen.</p>
            </section>

            <section class="materials-permissions-item">
                <h3 class="text-h6 font-weight-bold mb-2">Lesen/Hinzufügen</h3>
                <p class="mb-1">Der Empfänger kann die geteilten Inhalte lesen und Ihnen neue Fächer/Bereiche/Einheiten/Materialien hinzufügen.</p>
                <p class="mb-0">Er kann jedoch nichts umbenennen, verschieben oder löschen.</p>
            </section>

            <section class="materials-permissions-item">
                <h3 class="text-h6 font-weight-bold mb-2">Lesen/Schreiben</h3>
                <p class="mb-1">Der Empfänger kann die geteilten Inhalte lesen und Ihnen neue Fächer/Bereiche/Einheiten/Materialien hinzufügen. Außerdem kann er Fächer/Bereiche/Einheiten/Materialien umbenennen oder verschieben.</p>
                <p class="mb-0">Er kann jedoch nichts löschen.</p>
            </section>

            <section class="materials-permissions-item">
                <h3 class="text-h6 font-weight-bold mb-2">Vollzugriff</h3>
                <p class="mb-0">Der Empfänger hat die gleichen Recht wie sie.</p>
            </section>
        </div>
    </v-card>
</template>

<script>
export default {
    name: 'MaterialsPermissionsView',
}
</script>

<style scoped>
.materials-permissions-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.materials-permissions-item {
    padding: 16px 18px;
    border-radius: 14px;
    border: 1px solid rgba(253, 128, 46, 0.22);
    background: rgba(255, 255, 255, 0.6);
}
</style>
