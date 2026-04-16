<template>
    <div class="loading-animation" role="status" aria-label="Wird geladen...">
        <div class="loading-dots">
            <span class="dot dot--orange"></span>
            <span class="dot dot--green"></span>
            <span class="dot dot--slate"></span>
        </div>
        <span class="sr-only">Wird geladen...</span>
    </div>
</template>

<style scoped>
.loading-animation {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.loading-dots {
    display: flex;
    gap: 10px;
    align-items: center;
}

.dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    will-change: transform, opacity;
    animation: bounce 1.4s infinite ease-in-out;
}

.dot--orange {
    background: #f39200;
    animation-delay: 0s;
}

.dot--green {
    background: #3aaa35;
    animation-delay: 0.16s;
}

.dot--slate {
    background: #37474f;
    animation-delay: 0.32s;
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    25% {
        transform: translateY(-12px) scale(1.15);
        opacity: 1;
    }
    50% {
        transform: translateY(0) scale(1);
        opacity: 0.7;
    }
    75% {
        transform: translateY(2px) scale(0.95);
        opacity: 0.85;
    }
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}
</style>
