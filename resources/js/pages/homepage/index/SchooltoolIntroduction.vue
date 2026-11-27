<template>
    <section ref="introduction" class="schooltool-intro" aria-labelledby="schooltool-intro-title" tabindex="-1">
        <header class="schooltool-intro-header">
            <div class="schooltool-intro-shell">
                <router-link to="/" class="schooltool-intro-back">
                    <v-icon icon="mdi-arrow-left" size="19" aria-hidden="true" />
                    Zur Startseite
                </router-link>
            </div>
        </header>

        <div class="schooltool-intro-shell schooltool-intro-hero">
            <div class="schooltool-intro-copy">
                <h1 id="schooltool-intro-title">Schooltool</h1>
                <p>Mehr Zeit fürs Wesentliche</p>
            </div>

            <div ref="assembly" class="schooltool-assembly" aria-hidden="true">
                <span class="schooltool-rail schooltool-rail--top"></span>
                <span class="schooltool-rail schooltool-rail--right"></span>
                <span class="schooltool-rail schooltool-rail--bottom"></span>
                <span class="schooltool-rail schooltool-rail--left"></span>
                <div class="schooltool-carriage">
                    <img src="/storage/images/schooltool/schooltool-mark.svg" alt="" width="64" height="64" />
                    <span class="schooltool-carriage-light"></span>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const introduction = ref(null)
const assembly = ref(null)
const motionQuery = '(prefers-reduced-motion: no-preference)'
let animationMedia

function animateAssembly(gsap) {
    const rails = [
        { side: 'top', y: -34, start: 0 },
        { side: 'bottom', y: 34, start: 0 },
        { side: 'left', x: -34, start: 0.09 },
        { side: 'right', x: 34, start: 0.09 },
    ]
    const timeline = gsap.timeline({ defaults: { ease: 'power3.out' } })

    for (const { side, x = 0, y = 0, start } of rails) {
        const rail = `.schooltool-rail--${side}`
        timeline
            .fromTo(rail, { opacity: 0, x, y }, { opacity: 1, x: -x / 8, y: -y / 8, duration: 0.374 }, start)
            .to(rail, { x: 0, y: 0, duration: 0.146, clearProps: 'opacity,transform' }, '>')
    }

    const carriage = '.schooltool-carriage'
    const carriageLight = '.schooltool-carriage-light'
    timeline
        .fromTo(
            carriage,
            { opacity: 0, y: 36, scale: 0.88 },
            { opacity: 1, y: -3, scale: 1.02, duration: 0.395, ease: 'power4.out' },
            0.29,
        )
        .to(carriage, { y: 0, scale: 1, duration: 0.125, ease: 'power4.out', clearProps: 'opacity,transform' }, '>')
        .to(carriageLight, { opacity: 1, duration: 0.15, ease: 'power1.out' }, '>')
        .to(carriageLight, { opacity: 0, duration: 0.65, ease: 'power1.inOut', clearProps: 'opacity' }, '>1.2')
}

onMounted(async () => {
    introduction.value?.focus({ preventScroll: true })

    const localDevelopment = import.meta.env.DEV && window.location.hostname === 'localhost'
    const assemblyMotionQuery = localDevelopment ? 'all' : motionQuery
    const stage = assembly.value
    if (!stage || !window.matchMedia(assemblyMotionQuery).matches) return

    let gsap
    try {
        ({ gsap } = await import('gsap'))
    } catch {
        // The CSS layout is already the finished state if the optional import fails.
        return
    }

    if (assembly.value !== stage) return

    animationMedia = gsap.matchMedia(stage)
    animationMedia.add(assemblyMotionQuery, () => animateAssembly(gsap))
})

onBeforeUnmount(() => animationMedia?.revert())
</script>

<style scoped>
.schooltool-intro {
    --intro-text: #f5f5ed;
    --intro-muted: #c9d1cb;
    --intro-orange: #ff5a00;
    --intro-line: #2d3d35;
    display: grid;
    grid-template-rows: auto 1fr;
    min-height: calc(100svh - var(--v-layout-bottom, 52px));
    overflow: hidden;
    background: #111b17;
    color: var(--intro-text);
}

.schooltool-intro:focus {
    outline: none;
}

.schooltool-intro-shell {
    width: min(1200px, calc(100% - clamp(32px, 8.4vw, 128px)));
    margin-inline: auto;
}

.schooltool-intro-header {
    display: grid;
    min-height: 69px;
    align-items: center;
    border-bottom: 1px solid var(--intro-line);
}

.schooltool-intro-back {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    gap: 8px;
    padding-inline: 12px;
    border: 1px solid #45564c;
    border-radius: 8px;
    color: inherit;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
}

.schooltool-intro-back:hover {
    background: #24372d;
}

.schooltool-intro-back:focus-visible {
    outline: 3px solid var(--intro-orange);
    outline-offset: 4px;
}

.schooltool-intro-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 0.94fr);
    align-items: center;
    gap: clamp(24px, 4.6vw, 76px);
    padding-block: clamp(32px, 7vw, 88px);
}

.schooltool-intro-copy h1 {
    margin: 0;
    font-size: clamp(3rem, 8.8vw, 8.05rem);
    font-weight: 800;
    letter-spacing: -0.052em;
    line-height: 0.96;
}

.schooltool-intro-copy p {
    margin: 22px 0 0;
    color: var(--intro-muted);
    font-size: clamp(1.125rem, 2.8vw, 2.25rem);
    line-height: 1.28;
}

.schooltool-assembly {
    position: relative;
    isolation: isolate;
    width: min(100%, 500px);
    aspect-ratio: 1;
    justify-self: end;
    border: 1px solid #34483e;
    border-radius: clamp(12px, 2vw, 24px);
    background:
        linear-gradient(#33423948 1px, transparent 1px) 50% 50% / 8% 8%,
        linear-gradient(90deg, #33423948 1px, transparent 1px) 50% 50% / 8% 8%,
        radial-gradient(ellipse at 50% 45%, #233931, #15221d 75%);
}

.schooltool-assembly::before,
.schooltool-assembly::after {
    position: absolute;
    border: 1px solid #536658;
    border-radius: 6px;
    content: '';
}

.schooltool-assembly::before {
    inset: 8%;
    border-color: #415349;
}

.schooltool-assembly::after {
    inset: 14%;
    border-style: dashed;
    border-color: #54665a;
}

.schooltool-rail {
    position: absolute;
    z-index: 1;
    border: 1px solid #9aa89e;
    border-radius: 5px;
    box-shadow: 0 4px 10px #0008;
}

.schooltool-rail::after {
    position: absolute;
    inset: 32% 10%;
    border-radius: 2px;
    background: #243d33;
    content: '';
}

.schooltool-rail--top,
.schooltool-rail--bottom {
    inset-inline: 23%;
    height: 5.2%;
    background: linear-gradient(#aab7ac, #596b60);
}

.schooltool-rail--left,
.schooltool-rail--right {
    inset-block: 23%;
    width: 5.2%;
    background: linear-gradient(90deg, #9caea1, #596b60);
}

.schooltool-rail--left::after,
.schooltool-rail--right::after {
    inset: 10% 32%;
}

.schooltool-rail--top {
    inset-block-start: 12%;
}

.schooltool-rail--right {
    inset-inline-end: 12%;
}

.schooltool-rail--bottom {
    inset-block-end: 12%;
}

.schooltool-rail--left {
    inset-inline-start: 12%;
}

.schooltool-carriage {
    position: absolute;
    z-index: 2;
    inset: 22%;
    display: grid;
    place-items: center;
    border: 1px solid #7d8e81;
    border-radius: 12px;
    background: linear-gradient(145deg, #30483c, #1a2d23 70%);
    box-shadow: 0 12px 25px #0d1410a6, inset 0 0 0 5px #0d1c14;
}

.schooltool-carriage img {
    display: block;
    width: 62%;
    height: auto;
    border: 1px solid #789d72;
    border-radius: 6px;
    box-shadow: 0 10px 28px #081b0cce;
}

.schooltool-carriage-light {
    position: absolute;
    inset: -1px;
    border: 1px solid #ffbd80;
    border-radius: inherit;
    box-shadow: 0 0 12px #ff8c3d63, inset 0 0 9px #ff8c3d38;
    opacity: 0;
    pointer-events: none;
}

@media (max-width: 700px) {
    .schooltool-intro-header {
        min-height: 61px;
    }

    .schooltool-intro-hero {
        grid-template-columns: minmax(0, 1fr);
        justify-items: center;
        align-content: center;
        gap: clamp(26px, 8vw, 48px);
        text-align: center;
    }

    .schooltool-assembly {
        grid-row: 1;
        width: min(100%, 370px);
        justify-self: center;
    }

    .schooltool-intro-copy h1 {
        font-size: clamp(3rem, 12.7vw, 5rem);
    }

    .schooltool-intro-copy p {
        margin-top: 12px;
    }
}
</style>
