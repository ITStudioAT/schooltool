<template>
    <v-col cols="12">
        <header class="super-admin-section-header ai-glass-panel">
            <div class="super-admin-section-brand">
                <div class="super-admin-section-badge">
                    <v-icon size="18" color="white">mdi-shield-crown</v-icon>
                </div>

                <div>
                    <div class="super-admin-section-eyebrow">Admin Dashboard</div>
                    <h2 class="super-admin-section-title">Super-Admin</h2>
                    <p class="super-admin-section-subtitle">
                        Verwaltung von Schulen, Schuljahren, Lizenzen und Benutzern in einer Oberfläche.
                    </p>
                </div>
            </div>
        </header>
    </v-col>
</template>

<style scoped>
.super-admin-section-header {
    border-radius: 20px;
    padding: 14px 16px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    background:
        radial-gradient(circle at 0% 0%, rgba(120, 173, 255, 0.14), transparent 55%),
        linear-gradient(180deg, rgba(15, 22, 34, 0.58), rgba(16, 24, 36, 0.62));
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.10),
        0 16px 38px rgba(6, 10, 18, 0.28);
    color: #eef4ff;
}

.super-admin-section-brand {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.super-admin-section-badge {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #305a92, #1f3553);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
    flex: 0 0 auto;
}

.super-admin-section-eyebrow {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(230, 239, 255, 0.72);
}

.super-admin-section-title {
    margin: 2px 0 0;
    font-size: 1.18rem;
    line-height: 1.2;
    color: #f6f9ff;
}

.super-admin-section-subtitle {
    margin: 4px 0 0;
    color: rgba(235, 242, 255, 0.82);
    font-size: 0.86rem;
    line-height: 1.35;
}

@media (max-width: 640px) {
    .super-admin-section-header {
        border-radius: 16px;
        padding: 12px;
    }

    .super-admin-section-brand {
        gap: 10px;
    }

    .super-admin-section-badge {
        width: 34px;
        height: 34px;
        border-radius: 10px;
    }

    .super-admin-section-subtitle {
        font-size: 0.8rem;
    }
}
</style>
