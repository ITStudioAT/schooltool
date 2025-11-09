import puppeteer from 'puppeteer'

const browser = await puppeteer.launch()
const page = await browser.newPage()
await page.goto('https://example.com', { waitUntil: 'networkidle0' })
await page.screenshot({ path: 'puppeteer-ok.png' })
await browser.close()

console.log('✅ Puppeteer worked. Screenshot saved as puppeteer-ok.png')
