import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { mount } from '@vue/test-utils'
import { useMaterialsV2Polling } from '@/domains/materialsV2/useMaterialsV2Polling'

describe('useMaterialsV2Polling', () => {
    afterEach(() => {
        vi.useRealTimers()
    })

    it('starts once, stops when processing finishes, and cleans up on unmount', () => {
        vi.useFakeTimers()
        const refresh = vi.fn()
        let polling
        const wrapper = mount(defineComponent({
            setup() {
                polling = useMaterialsV2Polling(refresh, 4000)

                return () => null
            },
        }))

        polling.configurePolling(true)
        polling.configurePolling(true)
        vi.advanceTimersByTime(4000)
        expect(refresh).toHaveBeenCalledTimes(1)

        polling.configurePolling(false)
        vi.advanceTimersByTime(8000)
        expect(refresh).toHaveBeenCalledTimes(1)

        polling.configurePolling(true)
        wrapper.unmount()
        vi.advanceTimersByTime(4000)
        expect(refresh).toHaveBeenCalledTimes(1)
    })
})
