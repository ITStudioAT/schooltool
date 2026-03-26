<template>
    <v-col cols="12">
        <v-card rounded="xl" elevation="0" class="restaurant-users-dummy">
            <v-card-text class="pa-6 pa-md-8">
                <div class="restaurant-users-dummy__eyebrow">
                    Benutzer
                </div>
                <h2 class="restaurant-users-dummy__title">
                    Restaurant-Benutzer
                </h2>
                <p class="restaurant-users-dummy__text">
                    Diese Seite ist aktuell ein Platzhalter für die spätere Benutzerverwaltung im Restaurant-Modul.
                </p>
            </v-card-text>
        </v-card>
    </v-col>
</template>

<style scoped>
.restaurant-users-dummy {
    border: 1px solid rgba(148, 163, 184, 0.18);
    background:
        radial-gradient(circle at top right, rgba(59, 130, 246, 0.18), transparent 40%),
        linear-gradient(145deg, rgba(15, 23, 42, 0.96), rgba(30, 41, 59, 0.9));
    color: #e2e8f0;
}

.restaurant-users-dummy__eyebrow {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #93c5fd;
}

.restaurant-users-dummy__title {
    margin-top: 10px;
    font-size: clamp(1.5rem, 2vw, 2rem);
    font-weight: 700;
}

.restaurant-users-dummy__text {
    margin-top: 12px;
    max-width: 42rem;
    line-height: 1.65;
    color: rgba(226, 232, 240, 0.82);
}
</style>
