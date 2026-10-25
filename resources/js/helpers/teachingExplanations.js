import { defineAsyncComponent } from 'vue'
import { numberSystemNotation } from './numberSystemNotation'

const explanationComponents = {
    'binary-to-hexadecimal': defineAsyncComponent(() => import('@/pages/admin/teachingTools/BinaryToHexadecimalExplanation.vue')),
    'hexadecimal-to-binary': defineAsyncComponent(() => import('@/pages/admin/teachingTools/HexadecimalToBinaryExplanation.vue')),
    'decimal-to-hexadecimal': defineAsyncComponent(() => import('@/pages/admin/teachingTools/DecimalToHexadecimalExplanation.vue')),
    'decimal-to-binary': defineAsyncComponent(() => import('@/pages/admin/teachingTools/DecimalToBinaryExplanation.vue')),
}

export const teachingExplanationCategories = [
    { key: 'number-systems', title: 'Zahlensysteme', icon: 'mdi-numeric-2-box-multiple-outline' },
    { key: 'networks', title: 'Netzwerke', icon: 'mdi-lan' },
]

export const teachingExplanations = [
    { key: 'decimal-to-binary', category: 'number-systems', title: 'Dezimal auf Dual', system: 'Dual', base: 2, example: 13, result: '1101', description: 'Immer durch zwei teilen, Reste sammeln und von unten nach oben lesen. Entdecke, wie daraus eine Dualzahl wird.' },
    { key: 'decimal-to-hexadecimal', category: 'number-systems', title: 'Dezimal auf Hexadezimal', system: 'Hexadezimal', base: 16, example: 254, result: 'FE', description: 'Immer durch sechzehn teilen, Reste sammeln und A bis F kennenlernen. Von unten nach oben entsteht die Hexadezimalzahl.' },
    { key: 'binary-to-hexadecimal', category: 'number-systems', title: 'Dual auf Hexadezimal', sourceBase: 2, sourceSystem: 'Dual', system: 'Hexadezimal', base: 16, example: '110101', result: '35', description: 'Bits von rechts in Vierergruppen einteilen, links mit Nullen ergänzen und jede Gruppe in eine Hexziffer umwandeln.' },
    { key: 'hexadecimal-to-binary', category: 'number-systems', title: 'Hexadezimal auf Dual', sourceBase: 16, sourceSystem: 'Hexadezimal', system: 'Dual', base: 2, example: 'AF', result: '10101111', description: 'Jede Hexziffer in genau vier Bits übersetzen und die Vierergruppen von links nach rechts zusammensetzen.' },
].map(item => ({ sourceSystem: 'Dezimal', ...item, ...numberSystemNotation(item.base), sourceNotation: numberSystemNotation(item.sourceBase || 10) }))

export function teachingExplanationComponent(key) {
    return explanationComponents[key] || null
}
