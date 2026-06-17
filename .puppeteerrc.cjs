const { join } = require('path')

module.exports = {
    cacheDirectory: join(__dirname, '.cache', 'puppeteer'),
    skipDownload: true,
    chrome: {
        skipDownload: true,
    },
    'chrome-headless-shell': {
        skipDownload: true,
    },
}
