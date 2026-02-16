import { execFileSync } from 'node:child_process'
import path from 'node:path'

const TOKEN_SCRIPT_PATH = path.resolve(process.cwd(), 'tests/e2e/helpers/read-token.php')

function wait(ms: number): Promise<void> {
    return new Promise((resolve) => {
        setTimeout(resolve, ms)
    })
}

export async function fetchUserToken(
    email: string,
    field: 'token_2fa' | 'token_2fa_2' = 'token_2fa',
    timeoutMs = 15000,
): Promise<string> {
    const deadline = Date.now() + timeoutMs

    while (Date.now() <= deadline) {
        try {
            const token = execFileSync('php', [TOKEN_SCRIPT_PATH, email, field], {
                encoding: 'utf8',
                stdio: ['ignore', 'pipe', 'ignore'],
                env: {
                    ...process.env,
                    APP_ENV: 'e2e',
                },
            }).trim()

            if (token.length > 0) {
                return token
            }
        } catch {
            // Retry until timeout. Token is generated asynchronously by login flow.
        }

        await wait(250)
    }

    throw new Error(`Timed out while waiting for ${field} for user ${email}`)
}
