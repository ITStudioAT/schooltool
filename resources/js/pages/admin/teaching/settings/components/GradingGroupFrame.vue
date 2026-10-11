<template>
    <div class="calculation-level-frame" :class="{ 'calculation-group-frame': block.group }"
        :style="{ '--grading-level-color': levelColor, '--grading-parent-color': parentColor }"
        :aria-label="block.group ? `Gruppe ${block.group.name}` : 'Ungruppierte Benotungsteile'">
        <slot name="header" :block="block" />
        <div v-if="block.children?.length" class="grading-child-groups">
            <GradingGroupFrame v-for="child in block.children" :key="child.id" :block="child" :depth="depth + 1">
                <template #header="frame"><slot name="header" :block="frame.block" /></template>
                <template #default="frame"><slot :block="frame.block" /></template>
            </GradingGroupFrame>
        </div>
        <slot :block="block" />
    </div>
</template>

<script>
const levelColors = ['46, 112, 220', '0, 145, 80', '235, 145, 0', '142, 36, 170', '173, 20, 87', '69, 90, 100']

export default {
    name: 'GradingGroupFrame',
    props: {
        block: { type: Object, required: true },
        depth: { type: Number, default: 0 },
    },
    computed: {
        levelColor() {
            return levelColors[this.depth % levelColors.length]
        },
        parentColor() {
            return levelColors[Math.max(0, this.depth - 1) % levelColors.length]
        },
    },
}
</script>

<style scoped>
.calculation-level-frame {
    min-width: 0;
    border: 2px solid rgba(var(--grading-level-color), 0.65);
    border-radius: 20px;
    padding: 12px;
    background: color-mix(in srgb, rgb(var(--grading-level-color)) 32%, white);
}
.grading-child-groups {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
    margin-bottom: 12px;
}
@media (max-width: 600px) {
    .calculation-level-frame {
        padding: 8px;
    }
}
</style>
