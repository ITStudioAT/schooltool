<template>
    <section class="teaching-games">
        <template v-if="game === 'number-systems'">
            <RouterLink to="/admin/teaching-tools/games" class="teaching-games__back">
                <v-icon icon="mdi-arrow-left" size="18" aria-hidden="true" />
                Zur Spieleübersicht
            </RouterLink>
            <NumberSystemsGame />
        </template>
        <template v-else>
            <header class="teaching-games__heading">
                <h1>Spiele</h1>
                <p>Wähle ein Spiel aus.</p>
            </header>
            <ul class="teaching-games__grid" aria-label="Verfügbare Spiele">
                <li>
                    <RouterLink to="/admin/teaching-tools/games/number-systems" class="game-tile"
                        aria-label="Zahlensysteme öffnen">
                        <div class="game-tile__board" aria-hidden="true">
                            <span>2</span><span>01</span><span>10</span><span>16</span>
                        </div>
                        <div class="game-tile__caption">
                            <h2>Zahlen<wbr>systeme</h2>
                        </div>
                    </RouterLink>
                </li>
            </ul>
        </template>
    </section>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import NumberSystemsGame from './NumberSystemsGame.vue'

defineProps({ game: { type: String, default: null } })
</script>

<style scoped>
.teaching-games {
    --game-night: #171c28;
    --game-board: #242c3c;
    --game-gold: #efd18a;
    --game-brass: #ad925a;
    --game-line: #615334;
    color: #243447;
    padding: 16px 6px 30px;
}

.teaching-games h1 { font-size: 1.6rem; font-weight: 700; line-height: 1.25; }
.teaching-games__heading p { margin-top: 7px; color: #68768a; font-size: .94rem; }
.teaching-games__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 100px), 100px));
    gap: 16px;
    margin-top: 24px;
    padding: 0;
    list-style: none;
}

.teaching-games__grid li { min-width: 0; }
.game-tile {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    aspect-ratio: 1;
    padding: 8px;
    border: 1px solid var(--game-line);
    border-radius: 10px;
    background: var(--game-night);
    color: var(--game-gold);
    text-decoration: none;
    box-shadow: inset 0 0 0 2px var(--game-night), inset 0 0 0 3px #3c3740, 0 2px 7px rgb(23 28 40 / .12);
    transition: border-color .16s ease, box-shadow .16s ease;
}

.game-tile:hover {
    border-color: var(--game-gold);
    box-shadow: inset 0 0 0 2px var(--game-night), inset 0 0 0 3px var(--game-line), 0 3px 9px rgb(23 28 40 / .2);
}

.game-tile:focus-visible, .teaching-games__back:focus-visible {
    outline: 3px solid #ad7c1e;
    outline-offset: 5px;
}

.game-tile__board {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 4px;
    width: 44px;
    margin: 1px auto 6px;
}

.game-tile__board span {
    display: grid;
    place-items: center;
    aspect-ratio: 1;
    background: var(--game-board);
    border: 1px solid var(--game-line);
    border-bottom: 2px solid #373744;
    border-radius: 4px;
    font: 600 .78rem/1 Consolas, ui-monospace, monospace;
}

.game-tile__caption h2 { font-size: .75rem; font-weight: 700; line-height: 1.15; letter-spacing: -.02em; text-align: center; }
.teaching-games__back { display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 44px; max-width: 100%; padding: 10px 16px; margin-bottom: 18px; border: 1px solid #c7d0de; border-radius: 10px; background: #f4f6fa; color: #334760; font-size: .9rem; font-weight: 600; line-height: 1.4; text-decoration: none; box-shadow: 0 1px 2px rgb(23 28 40 / .06); }
.teaching-games__back:hover { background: #e8edf5; border-color: #9aaac0; }
@media (max-width: 480px) { .teaching-games__back { width: 100%; justify-content: flex-start; } }
.game-placeholder { max-width: 660px; padding: clamp(28px, 6vw, 64px); border: 1px solid var(--game-line); border-radius: 18px; background: var(--game-night); color: var(--game-gold); }
.game-placeholder__symbol { margin-bottom: 28px; font: 600 clamp(2.5rem, 8vw, 4rem)/1 Consolas, ui-monospace, monospace; letter-spacing: .08em; }
.game-placeholder__symbol span { color: var(--game-brass); }
.game-placeholder p { margin-top: 12px; color: #e1d8c4; line-height: 1.6; }
@media (prefers-reduced-motion: reduce) { .game-tile { transition: none; } }
</style>
