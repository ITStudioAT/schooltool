<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/')">Zur Startseite</v-btn>
                    <div class="chip-brand">Unterricht</div>
                </div>

                <h1 class="hero-title">Unterricht</h1>
                <p class="hero-subtitle">Überblick bewahren. Inhalte kennen. Noten erfahren.</p>

                <div class="hero-badges">
                    <span class="hero-badge">Pumpkin #FD802E</span>
                    <span class="hero-badge dark">Charcoal #233D4C</span>
                </div>
            </div>
        </section>

        <section class="login-cover">
            <div class="login-card">
                <div class="login-head">
                    <v-icon size="26">mdi-account-school</v-icon>
                    <h2>Login-Bereich</h2>
                </div>
                <p class="login-copy">Hier startet spaeter der Login fuer den Unterrichtsbereich. Aktuell ist nur das Cover aktiv.</p>

                <div class="login-fields">
                    <v-text-field label="E-Mail" variant="outlined" density="comfortable" prepend-inner-icon="mdi-email-outline" disabled />
                    <v-text-field label="Passwort" variant="outlined" density="comfortable" prepend-inner-icon="mdi-lock-outline" type="password" disabled />
                </div>

                <div class="login-actions">
                    <v-btn color="warning" variant="flat" rounded="pill" prepend-icon="mdi-rocket-launch" disabled>Anmelden (bald)</v-btn>
                    <span class="login-hint">Kein Login implementiert, nur Cover-Ansicht.</span>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
export default {}
</script>

<style scoped>
.lernportal-page {
    --pumpkin: #fd802e;
    --charcoal: #233d4c;
    --cream: #f8efe7;
    min-height: 100vh;
    padding: 28px 20px 56px;
    position: relative;
    overflow: hidden;
    background: linear-gradient(150deg, #182a35 0%, var(--charcoal) 46%, #182a35 100%);
    font-family: 'Poppins', 'Nunito', sans-serif;
}

.bg-shape {
    position: absolute;
    border-radius: 999px;
    filter: blur(90px);
    opacity: 0.35;
    pointer-events: none;
}

.bg-shape-1 {
    width: 340px;
    height: 340px;
    top: -80px;
    right: -120px;
    background: var(--pumpkin);
}

.bg-shape-2 {
    width: 340px;
    height: 340px;
    bottom: -120px;
    left: -130px;
    background: #ff9f5e;
}

.hero,
.login-cover {
    max-width: 960px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.hero-card {
    background: linear-gradient(155deg, var(--pumpkin) 0%, #ff8f42 100%);
    border-radius: 24px;
    padding: 26px;
    box-shadow: 0 20px 50px rgba(8, 16, 20, 0.35);
    color: var(--charcoal);
}

.hero-topline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.back-btn {
    color: var(--charcoal);
    font-weight: 700;
    letter-spacing: 0.2px;
    border-radius: 999px;
}

.chip-brand {
    padding: 7px 13px;
    border-radius: 999px;
    background: rgba(35, 61, 76, 0.15);
    font-size: 0.8rem;
    font-weight: 700;
    border: 1px solid rgba(35, 61, 76, 0.28);
}

.hero-title {
    margin: 20px 0 10px;
    font-size: clamp(1.8rem, 4vw, 3rem);
    line-height: 1.05;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.hero-subtitle {
    margin: 0;
    font-size: 1.06rem;
    max-width: 640px;
    font-weight: 600;
}

.hero-badges {
    margin-top: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.hero-badge {
    padding: 6px 14px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.82rem;
    border: 1px solid rgba(35, 61, 76, 0.25);
    background: rgba(248, 239, 231, 0.65);
}

.hero-badge.dark {
    color: var(--pumpkin);
    background: var(--charcoal);
    border-color: rgba(253, 128, 46, 0.55);
}

.login-cover {
    margin-top: 26px;
}

.login-card {
    background: rgba(248, 239, 231, 0.96);
    border: 1px solid rgba(253, 128, 46, 0.35);
    border-radius: 24px;
    padding: 26px;
    box-shadow: 0 14px 38px rgba(0, 0, 0, 0.2);
}

.login-head {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--charcoal);
}

.login-head h2 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 800;
}

.login-copy {
    margin: 10px 0 18px;
    color: #314d5d;
    font-weight: 500;
}

.login-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.login-actions {
    margin-top: 14px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.login-hint {
    color: #38596b;
    font-weight: 600;
    font-size: 0.9rem;
}

@media (max-width: 740px) {
    .lernportal-page {
        padding: 18px 14px 38px;
    }

    .hero-card,
    .login-card {
        padding: 20px;
        border-radius: 18px;
    }

    .login-fields {
        grid-template-columns: 1fr;
    }
}
</style>
