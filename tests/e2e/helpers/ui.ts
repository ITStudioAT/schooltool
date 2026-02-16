import { Page } from '@playwright/test'

export async function hideObstructiveUi(page: Page): Promise<void> {
    await page.addStyleTag({
        content: `
            .phpdebugbar,
            .phpdebugbar-resize-handle,
            .phpdebugbar *,
            .cookie-consent-root {
                display: none !important;
                pointer-events: none !important;
                visibility: hidden !important;
            }
        `,
    })

    await page.evaluate(() => {
        const removeObstructions = () => {
            document.querySelectorAll('.phpdebugbar, .cookie-consent-root').forEach((node) => node.remove())
        }

        removeObstructions()
        window.setTimeout(removeObstructions, 50)
        window.setTimeout(removeObstructions, 250)
    })
}
