<template>
    <v-card :style="{ ...gradientStyle, color: textColor }" width="300" min-height="170" class="d-flex flex-column">
        <v-card-title class="d-flex flex-row align-start justify-space-between">
            <div class="flex-shrink-1">
                <div class="text-body-1 font-weight-medium" v-if="school_short_name">{{ school_short_name }}</div>
                <div class="text-caption" style="white-space: normal" v-if="school_long_name">{{ school_long_name }}</div>
            </div>
            <div class="d-flex flex-row ga-1 flex-grow-1 justify-end">
                <v-icon :icon="mark_icon ? mark_icon : 'mdi-meteor'" :style="{ color: iconColor }" v-if="is_mark" />
                <v-icon icon="mdi-mail" size="small" :style="{ color: sentIconColor }" v-if="my_request?.sent_at" />
                <v-icon icon="mdi-eye" size="small" :style="{ color: seenIconColor }" v-if="my_request?.seen_at" />
                <v-icon icon="mdi-email-arrow-left" size="small" :style="{ color: mailIconColor }" v-if="my_request?.mail_at" />
            </div>
        </v-card-title>
        <!--
        <v-card-subtitle style="white-space: normal" v-if="subtitle">{{ subtitle }}</v-card-subtitle>
        -->
        <v-card-text>
            <v-chip size="small" v-if="subject">{{ subject }}</v-chip>
            <div class="text-body-2 mt-2" v-if="title">{{ title }}</div>
            <div class="text-caption mt-2" style="white-space: pre-line" v-if="description">
                {{ description.substr(0, 160) }}
                <span v-if="description.length > 160">…</span>
            </div>
        </v-card-text>

        <v-card-actions class="mt-auto d-flex flex-row align-center" :class="justify ? justify : 'justify-end'">
            <div class="text-body-2 font-weight-light" v-if="subtext">{{ subtext }}</div>
            <v-btn size="small" :text="button" variant="outlined" @click="$emit('clickCard')" />
        </v-card-actions>
    </v-card>
</template>
<script>
import { useTheme } from 'vuetify'
export default {
    setup() {
        const theme = useTheme()
        return { theme }
    },

    props: [
        'school_short_name',
        'school_long_name',
        'subject',
        'color',
        'title',
        'description',
        'subtext',
        'justify',
        'button',
        'is_mark',
        'mark_icon',
        'mark_color',
        'my_request',
    ],
    emits: ['clickCard'],

    data() {
        return {}
    },
    computed: {
        gradientStyle() {
            if (!this.color) return {}

            // Hole die Farbe aus dem Vuetify Theme
            const colorValue = this.theme.current.value.colors[this.color] || this.color

            return {
                background: `linear-gradient(-45deg, ${colorValue} 0%, ${this.adjustBrightness(colorValue, 70)} 100%)`,
            }
        },
        colorValue() {
            if (!this.color) return '#ffffff'
            return this.theme.current.value.colors[this.color] || this.color
        },
        iconColor() {
            // Dunkler für Kontrast auf hellem Gradient
            return this.mixColors(this.colorValue, '#F0000', 0.75)
        },

        sentIconColor() {
            return this.adjustBrightness(this.colorValue, -60)
        },

        seenIconColor() {
            return this.mixColors(this.colorValue, '#777700', 0.5)
        },

        mailIconColor() {
            return this.mixColors(this.colorValue, '#0077AA', 0.5)
        },

        textColor() {
            // Nimm die hellere Gradient-Farbe für die Berechnung
            const lighterColor = this.adjustBrightness(this.colorValue, 70)
            const r = parseInt(lighterColor.slice(1, 3), 16)
            const g = parseInt(lighterColor.slice(3, 5), 16)
            const b = parseInt(lighterColor.slice(5, 7), 16)

            // Berechne relative Luminanz (YIQ Formel)
            const luminance = (r * 299 + g * 587 + b * 114) / 1000

            // Höherer Schwellenwert für bessere Lesbarkeit
            return luminance > 160 ? '#000000' : '#FFFFFF'
        },
    },

    methods: {
        adjustBrightness(hex, percent) {
            // Hex zu RGB
            let r = parseInt(hex.slice(1, 3), 16)
            let g = parseInt(hex.slice(3, 5), 16)
            let b = parseInt(hex.slice(5, 7), 16)

            // Helligkeit anpassen
            r = Math.max(0, Math.min(255, r + percent))
            g = Math.max(0, Math.min(255, g + percent))
            b = Math.max(0, Math.min(255, b + percent))

            // Zurück zu Hex
            return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`
        },
        mixColors(color1, color2, weight = 0.5) {
            const r1 = parseInt(color1.slice(1, 3), 16)
            const g1 = parseInt(color1.slice(3, 5), 16)
            const b1 = parseInt(color1.slice(5, 7), 16)

            const r2 = parseInt(color2.slice(1, 3), 16)
            const g2 = parseInt(color2.slice(3, 5), 16)
            const b2 = parseInt(color2.slice(5, 7), 16)

            const r = Math.round(r1 * (1 - weight) + r2 * weight)
            const g = Math.round(g1 * (1 - weight) + g2 * weight)
            const b = Math.round(b1 * (1 - weight) + b2 * weight)

            return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`
        },
    },
}
</script>
