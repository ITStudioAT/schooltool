import { inject, watch } from 'vue'
import { routeLocationKey, routerKey } from 'vue-router'

// The URL contains only the accepted number and the current explanation step.
export function useNumberExplanationPosition({ getValue, setValue, step, maximumStep, parseValue }) {
    const route = inject(routeLocationKey, null)
    const router = inject(routerKey, null)
    const initialValue = getValue()

    if (route && router) {
        watch(() => [route.path, route.query.value, route.query.step], ([, rawValue, rawStep]) => {
            const parsed = typeof rawValue === 'string' ? parseValue(rawValue) : null
            const validValue = parsed !== null
            setValue(validValue ? parsed : initialValue)
            const selectedStep = typeof rawStep === 'string' && /^\d{1,3}$/.test(rawStep) ? Number(rawStep) : 0
            step.value = (rawValue === undefined || validValue) && selectedStep <= maximumStep() ? selectedStep : 0
        }, { immediate: true })
    }

    return function savePosition() {
        if (!route || !router) return
        const query = { ...route.query, value: String(getValue()), step: String(step.value) }
        if (route.query.value === query.value && route.query.step === query.step) return
        router.push({ query, hash: route.hash })
    }
}
