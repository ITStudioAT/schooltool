import { Page } from '@playwright/test'

export async function hideObstructiveUi(page: Page): Promise<void> {
    await page.addStyleTag({
        content: `
            .phpdebugbar,
            .phpdebugbar-resize-handle,
            .cookie-consent-root {
                display: none !important;
                pointer-events: none !important;
            }
        `,
    })

    await page.evaluate(() => {
        document.querySelectorAll('.phpdebugbar, .cookie-consent-root').forEach((node) => node.remove())
    })
}
