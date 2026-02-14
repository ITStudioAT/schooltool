import { render, screen } from '@testing-library/vue'
import { defineComponent } from 'vue'
import { createTestingPinia } from '@pinia/testing'

const Example = defineComponent({
    template: '<div>Example</div>',
})

test('renders starter component', async () => {
    render(Example, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })],
        },
    })

    expect(screen.getByText(/example/i)).toBeInTheDocument()
})

