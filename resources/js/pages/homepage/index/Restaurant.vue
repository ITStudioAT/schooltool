<template>
    <div class="restaurant-page">
        <section class="restaurant-hero">
            <div class="restaurant-shell">
                <router-link to="/" class="restaurant-back-link">
                    <v-icon size="18">mdi-arrow-left</v-icon>
                    <span>Zur Startseite</span>
                </router-link>

                <div class="restaurant-hero-card">
                    <div class="restaurant-hero-copy">
                        <div class="restaurant-eyebrow">SchoolTool Restaurant</div>
                        <h1 class="restaurant-title">Restaurant</h1>
                        <p class="restaurant-lead">
                            Diese Seite ist aktuell eine interne Vorschau. Hier entsteht die neue Benutzeroberfläche für Speisepläne, Bestellungen und alle Infos rund um das Essen.
                        </p>

                        <div class="restaurant-actions">
                            <router-link to="/" class="restaurant-action restaurant-action--secondary">Zur Übersicht</router-link>
                            <a href="/admin/restaurant/menu-plans" class="restaurant-action restaurant-action--primary">Zur Verwaltung</a>
                        </div>
                    </div>

                    <div class="restaurant-status-card">
                        <div class="restaurant-status-card__label">Status</div>
                        <div class="restaurant-status-card__title">Dummy-Seite aktiv</div>
                        <p class="restaurant-status-card__text">
                            Die externe Weiterleitung wurde entfernt. Dieser Bereich bleibt jetzt vollständig in der App.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="restaurant-content">
            <div class="restaurant-shell restaurant-grid">
                <article class="restaurant-panel">
                    <div class="restaurant-panel__icon">
                        <v-icon size="24">mdi-silverware-fork-knife</v-icon>
                    </div>
                    <h2>Speisepläne</h2>
                    <p>Hier werden später aktuelle Wochenpläne, verfügbare Gerichte und Hinweise zu Allergenen sichtbar.</p>
                </article>

                <article class="restaurant-panel">
                    <div class="restaurant-panel__icon">
                        <v-icon size="24">mdi-cart-outline</v-icon>
                    </div>
                    <h2>Bestellungen</h2>
                    <p>Bestellstatus, Fristen und Rückmeldungen sollen direkt verständlich und ohne externe Seite erreichbar sein.</p>
                </article>

                <article class="restaurant-panel">
                    <div class="restaurant-panel__icon">
                        <v-icon size="24">mdi-bell-outline</v-icon>
                    </div>
                    <h2>Hinweise</h2>
                    <p>Öffnungszeiten, Ausgabetexte und organisatorische Infos bekommen hier einen festen Platz.</p>
                </article>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    name: 'HomepageRestaurantPage',
}
</script>

<style scoped>
.restaurant-page {
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(255, 213, 128, 0.55), transparent 34%),
        radial-gradient(circle at top right, rgba(255, 247, 237, 0.7), transparent 28%),
        linear-gradient(180deg, #fff7ed 0%, #ffedd5 26%, #fff 100%);
    color: #1f2937;
}

.restaurant-shell {
    width: min(1120px, calc(100% - 32px));
    margin: 0 auto;
}

.restaurant-hero {
    padding: 32px 0 36px;
}

.restaurant-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #9a3412;
    font-weight: 700;
    text-decoration: none;
    margin-bottom: 18px;
}

.restaurant-hero-card {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.65fr);
    gap: 22px;
    padding: 28px;
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(251, 146, 60, 0.24);
    box-shadow: 0 24px 70px rgba(194, 65, 12, 0.12);
    backdrop-filter: blur(10px);
}

.restaurant-eyebrow {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(251, 146, 60, 0.12);
    color: #c2410c;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.restaurant-title {
    margin: 18px 0 12px;
    font-size: clamp(2.4rem, 4vw, 4rem);
    line-height: 0.94;
    font-weight: 900;
    color: #111827;
}

.restaurant-lead {
    max-width: 62ch;
    font-size: 1.02rem;
    line-height: 1.7;
    color: #4b5563;
}

.restaurant-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

.restaurant-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 0 18px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 800;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.restaurant-action:hover {
    transform: translateY(-1px);
}

.restaurant-action--primary {
    background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
    color: #fff;
    box-shadow: 0 16px 30px rgba(234, 88, 12, 0.24);
}

.restaurant-action--secondary {
    background: rgba(255, 255, 255, 0.92);
    color: #9a3412;
    border: 1px solid rgba(251, 146, 60, 0.28);
}

.restaurant-status-card {
    align-self: stretch;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
    padding: 22px;
    border-radius: 22px;
    background: linear-gradient(160deg, #7c2d12 0%, #c2410c 54%, #f97316 100%);
    color: #fff7ed;
}

.restaurant-status-card__label {
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.74;
}

.restaurant-status-card__title {
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1.1;
}

.restaurant-status-card__text {
    line-height: 1.65;
    opacity: 0.94;
}

.restaurant-content {
    padding: 0 0 56px;
}

.restaurant-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.restaurant-panel {
    padding: 24px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(251, 146, 60, 0.18);
    box-shadow: 0 16px 40px rgba(120, 53, 15, 0.08);
}

.restaurant-panel__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    margin-bottom: 14px;
    background: rgba(251, 146, 60, 0.12);
    color: #c2410c;
}

.restaurant-panel h2 {
    margin: 0 0 10px;
    font-size: 1.22rem;
    font-weight: 800;
    color: #111827;
}

.restaurant-panel p {
    margin: 0;
    line-height: 1.68;
    color: #4b5563;
}

@media (max-width: 960px) {
    .restaurant-hero-card,
    .restaurant-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .restaurant-hero {
        padding-top: 20px;
    }

    .restaurant-shell {
        width: min(100% - 20px, 1120px);
    }

    .restaurant-hero-card,
    .restaurant-panel {
        padding: 20px;
    }

    .restaurant-title {
        font-size: 2.4rem;
    }

    .restaurant-actions {
        flex-direction: column;
    }
}
</style>
