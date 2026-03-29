<template>
    <div class="products-page" ref="pageRoot">
        <!-- Atmospheric Background -->
        <div class="atmo">
            <div class="atmo-grain"></div>
            <div class="atmo-orb atmo-orb--1"></div>
            <div class="atmo-orb atmo-orb--2"></div>
            <div class="atmo-orb atmo-orb--3"></div>
        </div>

        <!-- Sticky Nav -->
        <header class="pnav">
            <router-link to="/" class="pnav-brand">
                <span class="pnav-logo">
                    <span class="picon picon--brand" aria-hidden="true">⌂</span>
                </span>
                <span class="pnav-wordmark">
                    <span class="wm-school">School</span><span class="wm-tool">Tool</span>
                </span>
            </router-link>
            <nav class="pnav-links">
                <button
                    v-for="(p, i) in products"
                    :key="p.key"
                    class="pnav-dot"
                    :class="{ 'is-active': activeSection === i + 1 }"
                    :style="{ '--dot-color': p.accent }"
                    @click="scrollToSection(i + 1)"
                    :title="p.name">
                    <span class="pnav-dot-ring"></span>
                </button>
            </nav>
            <router-link to="/" class="pnav-back">
                <span class="picon picon--nav" aria-hidden="true">←</span>
                <span>Startseite</span>
            </router-link>
        </header>

        <!-- Hero -->
        <section class="phero" ref="heroSection" data-section="0">
            <div class="phero-content">
                <div class="phero-eyebrow" :class="{ 'is-visible': heroVisible }">
                    <span class="eyebrow-line"></span>
                    <span>Produkte</span>
                    <span class="eyebrow-line"></span>
                </div>
                <h1 class="phero-title" :class="{ 'is-visible': heroVisible }">
                    <span class="phero-title-line">Fünf Module.</span>
                    <span class="phero-title-line phero-title-line--accent">Eine Plattform.</span>
                </h1>
                <p class="phero-lead" :class="{ 'is-visible': heroVisible }">
                    SchoolTool vereint Organisation, Unterricht und Zusammenarbeit<br class="hide-mobile" />
                    in einer durchdachten, modularen Oberfläche.
                </p>
                <div class="phero-stats" :class="{ 'is-visible': heroVisible }">
                    <div class="stat-item" v-for="stat in stats" :key="stat.label">
                        <span class="stat-value">{{ stat.value }}</span>
                        <span class="stat-label">{{ stat.label }}</span>
                    </div>
                </div>
            </div>
            <div class="phero-scroll-cue" :class="{ 'is-visible': heroVisible }">
                <div class="scroll-cue-track">
                    <div class="scroll-cue-thumb"></div>
                </div>
                <span>Scrollen</span>
            </div>
        </section>

        <!-- Product Sections -->
        <section
            v-for="(product, index) in products"
            :key="product.key"
            class="psection"
            :class="[`psection--${product.key}`, { 'psection--reverse': index % 2 === 1 }]"
            :style="{ '--accent': product.accent, '--accent-soft': product.accentSoft, '--accent-glow': product.accentGlow }"
            :ref="el => sectionRefs[index + 1] = el"
            :data-section="index + 1">

            <div class="psection-bg">
                <div class="psection-gradient"></div>
                <div class="psection-line psection-line--top"></div>
                <div class="psection-line psection-line--bottom"></div>
            </div>

            <div class="psection-inner">
                <div class="psection-copy" :class="{ 'is-visible': visibleSections.has(index + 1) }">
                    <div class="psection-number">{{ String(index + 1).padStart(2, '0') }}</div>
                    <div class="psection-label">
                        <span class="picon picon--chip" :style="{ '--icon-color': product.accent }" aria-hidden="true">{{ product.symbol }}</span>
                        <span>{{ product.name }}</span>
                    </div>
                    <h2 class="psection-title">{{ product.title }}</h2>
                    <p class="psection-text">{{ product.text }}</p>
                    <div class="psection-features">
                        <div class="feature-pill" v-for="f in product.features" :key="f.label">
                            <span class="picon picon--chip picon--feature" :style="{ '--icon-color': product.accent }" aria-hidden="true">{{ f.symbol }}</span>
                            <span>{{ f.label }}</span>
                        </div>
                    </div>
                    <div class="psection-tags">
                        <span v-for="tag in product.tags" :key="tag">{{ tag }}</span>
                    </div>
                </div>

                <div class="psection-visual" :class="{ 'is-visible': visibleSections.has(index + 1) }">
                    <div class="visual-frame">
                        <div class="visual-glow"></div>
                        <div class="visual-inner">
                            <img :src="product.image" :alt="product.name" class="visual-img" />
                        </div>
                        <div class="visual-reflection"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Closing CTA -->
        <section class="pcta" ref="ctaSection" data-section="cta">
            <div class="pcta-content" :class="{ 'is-visible': ctaVisible }">
                <h2 class="pcta-title">Bereit für den<br />nächsten Schritt?</h2>
                <p class="pcta-text">Entdecken Sie, wie SchoolTool Ihren Schulalltag transformieren kann.</p>
                <router-link to="/" class="pcta-button">
                    <span>Zur Startseite</span>
                    <span class="picon picon--cta" aria-hidden="true">→</span>
                </router-link>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    data() {
        return {
            heroVisible: false,
            ctaVisible: false,
            activeSection: 0,
            visibleSections: new Set(),
            sectionRefs: {},
            observer: null,
            stats: [
                { value: '5', label: 'Module' },
                { value: '∞', label: 'Möglichkeiten' },
                { value: '1', label: 'Plattform' },
            ],
            products: [
                {
                    key: 'register',
                    name: 'Anmeldetool',
                    symbol: '📅',
                    title: 'Termine und Veranstaltungen organisiert',
                    text: 'Planen Sie Schulanmeldungen und Veranstaltungen mit klaren Zeitslots, Kapazitäten und nachvollziehbaren Buchungen. Elternabende, Sprechtage und Aufnahmetage werden zum Kinderspiel.',
                    accent: '#f59120',
                    accentSoft: 'rgba(245, 145, 32, 0.12)',
                    accentGlow: 'rgba(245, 145, 32, 0.25)',
                    image: '/images/illustrations/date-picker-animated-v2.svg',
                    features: [
                        { symbol: '⏱', label: 'Zeitslot-Verwaltung' },
                        { symbol: '👥', label: 'Kapazitätskontrolle' },
                        { symbol: '📄', label: 'CSV / PDF Export' },
                    ],
                    tags: ['Zeitslots', 'Buchungen', 'Export', 'Kalender'],
                },
                {
                    key: 'teaching',
                    name: 'Unterricht',
                    symbol: '🚀',
                    title: 'Das digitale Klassenzimmer',
                    text: 'Lehrer:innen verwalten ihren Unterricht und Schüler:innen sehen ihre Kurse, Aufgaben und Termine in einer klaren Oberfläche. Schnell, mobil und auf das Wesentliche reduziert.',
                    accent: '#5b9cf5',
                    accentSoft: 'rgba(91, 156, 245, 0.12)',
                    accentGlow: 'rgba(91, 156, 245, 0.25)',
                    image: '/images/illustrations/online-learning-animated.svg',
                    features: [
                        { symbol: '📚', label: 'Kursverwaltung' },
                        { symbol: '📝', label: 'Aufgaben & Abgaben' },
                        { symbol: '📱', label: 'Mobile-first Design' },
                    ],
                    tags: ['Unterricht', 'Aufgaben', 'Termine', 'Kurse'],
                },
                {
                    key: 'tutoring',
                    name: 'Schüler helfen Schülern',
                    symbol: '🤝',
                    title: 'Nachhilfe transparent organisiert',
                    text: 'Nachhilfe-Angebote übersichtlich verwaltet. Zuständigkeiten und Status bleiben jederzeit sichtbar. Schüler:innen unterstützen sich gegenseitig — koordiniert und nachvollziehbar.',
                    accent: '#f0873a',
                    accentSoft: 'rgba(240, 135, 58, 0.12)',
                    accentGlow: 'rgba(240, 135, 58, 0.25)',
                    image: '/images/illustrations/notebook-animated.svg',
                    features: [
                        { symbol: '🤝', label: 'Matching-System' },
                        { symbol: '📈', label: 'Status-Tracking' },
                        { symbol: '🔔', label: 'Benachrichtigungen' },
                    ],
                    tags: ['Nachhilfe', 'Anfragen', 'Status', 'Peer-Learning'],
                },
                {
                    key: 'materials',
                    name: 'Materialien',
                    symbol: '📁',
                    title: 'Wissen zentral verwalten',
                    text: 'Arbeitsblätter, Präsentationen und Vorlagen strukturiert organisieren und teilen. Ein zentraler Ort für alle Unterrichtsmaterialien — durchsuchbar, versioniert und immer griffbereit.',
                    accent: '#3ebb82',
                    accentSoft: 'rgba(62, 187, 130, 0.12)',
                    accentGlow: 'rgba(62, 187, 130, 0.25)',
                    image: '/images/illustrations/bookshelves-animated.svg',
                    features: [
                        { symbol: '🔎', label: 'Volltextsuche' },
                        { symbol: '🔗', label: 'Teilen & Freigabe' },
                        { symbol: '🗂', label: 'Gruppenordner' },
                    ],
                    tags: ['Dateien', 'Ordner', 'Organisation', 'Suche'],
                },
                {
                    key: 'restaurant',
                    name: 'Restaurant',
                    symbol: '🍽',
                    title: 'Schulverpflegung digital organisiert',
                    text: 'Speisepläne, Bestellungen und wichtige Hinweise für die Ausgabe in einer klaren Oberfläche bündeln. Vom Menüplan bis zur Abholung — alles an einem Ort.',
                    accent: '#8b8cf5',
                    accentSoft: 'rgba(139, 140, 245, 0.12)',
                    accentGlow: 'rgba(139, 140, 245, 0.25)',
                    image: '/images/illustrations/eating-pasta-animated.svg',
                    features: [
                        { symbol: '📆', label: 'Speiseplan-Editor' },
                        { symbol: '🛒', label: 'Online-Bestellung' },
                        { symbol: '⚠', label: 'Allergen-Info' },
                    ],
                    tags: ['Speisepläne', 'Bestellung', 'Abholung', 'Allergene'],
                },
            ],
        }
    },

    mounted() {
        this.setupIntersectionObserver()
        setTimeout(() => { this.heroVisible = true }, 150)
    },

    beforeUnmount() {
        if (this.observer) {
            this.observer.disconnect()
        }
    },

    methods: {
        setupIntersectionObserver() {
            this.observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        const sectionIndex = parseInt(entry.target.dataset.section)
                        if (entry.isIntersecting) {
                            if (!isNaN(sectionIndex) && sectionIndex > 0) {
                                this.visibleSections.add(sectionIndex)
                                this.visibleSections = new Set(this.visibleSections)
                            }
                            if (entry.target === this.$refs.ctaSection) {
                                this.ctaVisible = true
                            }
                            if (!isNaN(sectionIndex)) {
                                this.activeSection = sectionIndex
                            }
                        }
                    })
                },
                { threshold: 0.25, rootMargin: '-5% 0px -5% 0px' }
            )

            this.$nextTick(() => {
                if (this.$refs.heroSection) this.observer.observe(this.$refs.heroSection)
                Object.values(this.sectionRefs).forEach((el) => {
                    if (el) this.observer.observe(el)
                })
                if (this.$refs.ctaSection) this.observer.observe(this.$refs.ctaSection)
            })
        },

        scrollToSection(index) {
            const el = this.sectionRefs[index]
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' })
            }
        },
    },
}
</script>

<style>
.products-page,
.products-page *,
.products-page *::before,
.products-page *::after {
    font-family: 'Outfit', sans-serif;
}
</style>

<style scoped>

/* ============================================
   FOUNDATIONS
   ============================================ */
.products-page {
    --c-bg: #0c1117;
    --c-surface: #141b24;
    --c-border: rgba(255, 255, 255, 0.06);
    --c-text: rgba(255, 255, 255, 0.82);
    --c-text-muted: rgba(255, 255, 255, 0.42);
    --c-text-bright: rgba(255, 255, 255, 0.96);
    --c-brand: #f59120;
    --font-display: 'Syne', sans-serif;
    --font-body: 'Outfit', sans-serif;
    --shell: min(1280px, 90vw);

    min-height: 100vh;
    background: var(--c-bg);
    color: var(--c-text);
    font-family: 'Outfit', sans-serif !important;
    overflow-x: hidden;
    position: relative;
}

.picon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    font-style: normal;
}

.picon--brand {
    font-size: 0.9rem;
    transform: translateY(-1px);
}

.picon--nav,
.picon--cta {
    font-size: 1rem;
    font-weight: 700;
}

.picon--chip {
    width: 1.1rem;
    min-width: 1.1rem;
    color: var(--icon-color, currentColor);
    font-size: 0.95rem;
}

.picon--feature {
    font-size: 0.9rem;
}

.phero-title-line,
.psection-title,
.pcta-title {
    font-family: 'Outfit', sans-serif !important;
    font-weight: 800;
    line-height: 1.02;
    letter-spacing: -0.03em;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
}

/* ============================================
   ATMOSPHERE
   ============================================ */
.atmo {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
}

.atmo-grain {
    position: absolute;
    inset: -50%;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.025'/%3E%3C/svg%3E");
    background-repeat: repeat;
    background-size: 256px;
    opacity: 1;
}

.atmo-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
}

.atmo-orb--1 {
    width: 600px;
    height: 600px;
    top: -10%;
    left: -10%;
    background: radial-gradient(circle, rgba(245, 145, 32, 0.08), transparent 70%);
    animation: orbFloat 30s ease-in-out infinite;
}

.atmo-orb--2 {
    width: 500px;
    height: 500px;
    top: 40%;
    right: -15%;
    background: radial-gradient(circle, rgba(91, 156, 245, 0.06), transparent 70%);
    animation: orbFloat 25s ease-in-out infinite reverse;
}

.atmo-orb--3 {
    width: 400px;
    height: 400px;
    bottom: -5%;
    left: 30%;
    background: radial-gradient(circle, rgba(62, 187, 130, 0.05), transparent 70%);
    animation: orbFloat 35s ease-in-out infinite 5s;
}

@keyframes orbFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(40px, -30px) scale(1.08); }
    66% { transform: translate(-30px, 20px) scale(0.94); }
}

/* ============================================
   STICKY NAV
   ============================================ */
.pnav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 28px;
    background: rgba(12, 17, 23, 0.7);
    backdrop-filter: blur(20px) saturate(1.2);
    border-bottom: 1px solid var(--c-border);
}

.pnav-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: var(--c-text-bright);
}

.pnav-logo {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--c-brand), #e07a0e);
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.pnav-wordmark {
    font-family: 'Syne', sans-serif !important;
    font-size: 1.1rem;
    font-weight: 800;
    letter-spacing: -0.3px;
}

.wm-school { color: var(--c-text-bright); }
.wm-tool { color: var(--c-brand); }

.pnav-links {
    display: flex;
    align-items: center;
    gap: 10px;
}

.pnav-dot {
    width: 12px;
    height: 12px;
    border: none;
    background: none;
    padding: 0;
    cursor: pointer;
    position: relative;
    display: grid;
    place-items: center;
}

.pnav-dot-ring {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.pnav-dot.is-active .pnav-dot-ring {
    width: 10px;
    height: 10px;
    background: var(--dot-color);
    box-shadow: 0 0 12px var(--dot-color);
}

.pnav-dot:hover .pnav-dot-ring {
    background: rgba(255, 255, 255, 0.35);
    transform: scale(1.2);
}

.pnav-back {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--c-text-muted);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: color 0.2s;
}

.pnav-back:hover {
    color: var(--c-text-bright);
}

/* ============================================
   HERO SECTION
   ============================================ */
.phero {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 100px 28px 60px;
    text-align: center;
}

.phero-content {
    max-width: 860px;
}

.phero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--c-brand);
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.1s;
}

.phero-eyebrow.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.eyebrow-line {
    width: 32px;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-brand), transparent);
}

.phero-title {
    font-size: clamp(2.8rem, 7vw, 5.5rem);
    margin: 0 0 28px;
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.9s cubic-bezier(0.16, 1, 0.3, 1) 0.25s;
}

.phero-title.is-visible {
    opacity: 1;
    transform: none;
}

.phero-title-line {
    display: block;
    color: var(--c-text-bright);
}

.phero-title-line--accent {
    background: linear-gradient(135deg, var(--c-brand), #ffb74d);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.phero-lead {
    font-size: clamp(1rem, 1.6vw, 1.2rem);
    line-height: 1.6;
    color: var(--c-text-muted);
    max-width: 640px;
    margin: 0 auto 48px;
    font-weight: 300;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.45s;
}

.phero-lead.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.phero-stats {
    display: flex;
    justify-content: center;
    gap: 56px;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.6s;
}

.phero-stats.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.stat-value {
    font-family: 'Syne', sans-serif !important;
    font-size: 2.4rem;
    font-weight: 800;
    color: var(--c-text-bright);
    letter-spacing: -0.02em;
}

.stat-label {
    font-size: 0.78rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--c-text-muted);
}

.phero-scroll-cue {
    position: absolute;
    bottom: 36px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    color: var(--c-text-muted);
    font-size: 0.72rem;
    font-weight: 500;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    opacity: 0;
    transition: opacity 0.8s ease 1.2s;
}

.phero-scroll-cue.is-visible {
    opacity: 1;
}

.scroll-cue-track {
    width: 1px;
    height: 48px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 1px;
    overflow: hidden;
}

.scroll-cue-thumb {
    width: 100%;
    height: 16px;
    background: var(--c-brand);
    border-radius: 1px;
    animation: scrollCue 2.4s ease-in-out infinite;
}

@keyframes scrollCue {
    0% { transform: translateY(-16px); }
    50% { transform: translateY(48px); }
    100% { transform: translateY(-16px); }
}

/* ============================================
   PRODUCT SECTIONS
   ============================================ */
.psection {
    position: relative;
    z-index: 1;
    padding: 120px 28px;
    overflow: hidden;
}

.psection-bg {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.psection-gradient {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 70% 50% at 50% 50%, var(--accent-soft, rgba(255,255,255,0.02)), transparent);
}

.psection-line {
    position: absolute;
    left: 5%;
    right: 5%;
    height: 1px;
}

.psection-line--top {
    top: 0;
    background: linear-gradient(90deg, transparent, var(--c-border), transparent);
}

.psection-line--bottom {
    bottom: 0;
    background: linear-gradient(90deg, transparent, var(--c-border), transparent);
}

.psection-inner {
    max-width: var(--shell);
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 64px;
    align-items: center;
}

.psection--reverse .psection-inner {
    direction: rtl;
}

.psection--reverse .psection-inner > * {
    direction: ltr;
}

/* Copy Side */
.psection-copy {
    opacity: 0;
    transform: translateY(40px);
    transition: all 1s cubic-bezier(0.16, 1, 0.3, 1);
}

.psection-copy.is-visible {
    opacity: 1;
    transform: none;
}

.psection-number {
    font-family: 'Syne', sans-serif !important;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--accent);
    letter-spacing: 0.08em;
    margin-bottom: 16px;
    opacity: 0.6;
}

.psection-label {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 6px 14px 6px 8px;
    background: var(--accent-soft);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: 999px;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--accent);
    margin-bottom: 20px;
}

.psection-title {
    font-size: clamp(1.6rem, 3.2vw, 2.6rem);
    color: var(--c-text-bright);
    margin: 0 0 18px;
}

.psection-text {
    font-size: 1.02rem;
    line-height: 1.65;
    color: var(--c-text);
    margin: 0 0 28px;
    max-width: 50ch;
    font-weight: 300;
}

.psection-features {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 24px;
}

.feature-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px;
    font-size: 0.88rem;
    font-weight: 500;
    color: var(--c-text);
    transition: border-color 0.3s, background 0.3s;
}

.feature-pill:hover {
    border-color: var(--accent);
    background: var(--accent-soft);
}

.psection-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.psection-tags span {
    padding: 5px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.06);
    color: var(--c-text-muted);
    font-size: 0.78rem;
    font-weight: 500;
    letter-spacing: 0.02em;
}

/* Visual Side */
.psection-visual {
    opacity: 0;
    transform: translateY(50px) scale(0.96);
    transition: all 1.1s cubic-bezier(0.16, 1, 0.3, 1) 0.15s;
}

.psection-visual.is-visible {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.visual-frame {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
}

.visual-glow {
    position: absolute;
    inset: -40%;
    background: radial-gradient(circle at center, var(--accent-glow), transparent 70%);
    filter: blur(60px);
    z-index: 0;
    animation: glowPulse 6s ease-in-out infinite;
}

@keyframes glowPulse {
    0%, 100% { opacity: 0.4; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.05); }
}

.visual-inner {
    position: relative;
    z-index: 1;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.02));
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    backdrop-filter: blur(8px);
    padding: 28px;
    min-height: 320px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.visual-img {
    width: 100%;
    max-width: 400px;
    height: auto;
    object-fit: contain;
    filter: drop-shadow(0 8px 32px rgba(0, 0, 0, 0.3));
}

.visual-reflection {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 40%;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), transparent);
    border-radius: 24px 24px 0 0;
    z-index: 2;
    pointer-events: none;
}

/* ============================================
   CTA SECTION
   ============================================ */
.pcta {
    position: relative;
    z-index: 1;
    padding: 140px 28px;
    text-align: center;
}

.pcta-content {
    max-width: 600px;
    margin: 0 auto;
    opacity: 0;
    transform: translateY(30px);
    transition: all 1s cubic-bezier(0.16, 1, 0.3, 1);
}

.pcta-content.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.pcta-title {
    font-size: clamp(2rem, 4vw, 3.2rem);
    line-height: 1.08;
    letter-spacing: -0.02em;
    color: var(--c-text-bright);
    margin: 0 0 18px;
}

.pcta-text {
    font-size: 1.05rem;
    color: var(--c-text-muted);
    line-height: 1.6;
    margin: 0 0 36px;
    font-weight: 300;
}

.pcta-button {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 32px;
    background: linear-gradient(135deg, var(--c-brand), #e07a0e);
    color: white;
    font-family: 'Outfit', sans-serif !important;
    font-size: 0.95rem;
    font-weight: 600;
    border-radius: 14px;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 24px rgba(245, 145, 32, 0.25);
}

.pcta-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 36px rgba(245, 145, 32, 0.35);
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 900px) {
    .psection-inner {
        grid-template-columns: 1fr;
        gap: 40px;
    }

    .psection--reverse .psection-inner {
        direction: ltr;
    }

    .psection-visual {
        order: -1;
    }

    .psection {
        padding: 80px 20px;
    }

    .pnav-links {
        display: none;
    }

    .phero-stats {
        gap: 32px;
    }

    .stat-value {
        font-size: 1.8rem;
    }

    .hide-mobile {
        display: none;
    }
}

@media (max-width: 600px) {
    .pnav {
        padding: 12px 16px;
    }

    .pnav-back span {
        display: none;
    }

    .phero {
        padding: 80px 16px 60px;
    }

    .phero-stats {
        gap: 24px;
    }

    .psection {
        padding: 60px 16px;
    }

    .visual-inner {
        padding: 20px;
        min-height: 220px;
    }

    .pcta {
        padding: 80px 16px;
    }
}
</style>
