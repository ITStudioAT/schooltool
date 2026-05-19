import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import RegisterPage from '@/pages/homepage/register/Register.vue'
import StudentPage from '@/pages/homepage/student/Student.vue'
import SchoolAndUser from '@/pages/homepage/tutoring/components/TutoringOverview/SchoolAndUser.vue'

describe('code login availability guards', () => {
    it('register page marks code login unavailable when queue is down', () => {
        const isCodeLoginAvailable = (RegisterPage as any).computed.isCodeLoginAvailable.call({
            config: { health: { queue_working: false } },
        })

        expect(isCodeLoginAvailable).toBe(false)
    })

    it('student page blocks continueWithoutPassword when queue is down', async () => {
        const loginStepEmail = vi.fn()
        const context: Record<string, any> = {
            isCodeLoginAvailable: false,
            studentStore: {
                loginStepEmail,
            },
        }

        await (StudentPage as any).methods.continueWithoutPassword.call(context)

        expect(loginStepEmail).not.toHaveBeenCalled()
    })

    it('student page blocks submitCode when queue is down', async () => {
        const loginStepCode = vi.fn()
        const context: Record<string, any> = {
            isCodeLoginAvailable: false,
            canSubmitCode: true,
            studentStore: {
                loginStepCode,
            },
            data: {},
        }

        await (StudentPage as any).methods.submitCode.call(context)

        expect(loginStepCode).not.toHaveBeenCalled()
    })

    it('student page preselects the school from the login URL', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/student/Student.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("const schoolShortName = String(this.$route.query.school || '').trim()")
        expect(source).toContain('const selectedSchoolFromUrl = schoolShortName')
        expect(source).toContain('this.selected_school_id = selectedSchoolFromUrl.id')
        expect(source).toContain('this.school = selectedSchoolFromUrl')
    })

    it('tutoring page blocks unknown password flow when queue is down', async () => {
        const unknownPassword = vi.fn()
        const context: Record<string, any> = {
            isCodeLoginAvailable: false,
            tutoringStore: {
                unknownPassword,
            },
        }

        await (SchoolAndUser as any).methods.unknownPassword.call(context, {})

        expect(unknownPassword).not.toHaveBeenCalled()
    })

    it('tutoring page blocks token login when queue is down', async () => {
        const loginWithToken = vi.fn()
        const context: Record<string, any> = {
            isCodeLoginAvailable: false,
            tutoringStore: {
                loginWithToken,
            },
        }

        await (SchoolAndUser as any).methods.loginWithToken.call(context, {})

        expect(loginWithToken).not.toHaveBeenCalled()
    })

    it('tutoring page marks code login unavailable from queue_working prop even without store config', () => {
        const isCodeLoginAvailable = (SchoolAndUser as any).computed.isCodeLoginAvailable.call({
            queue_working: false,
            config: null,
        })

        expect(isCodeLoginAvailable).toBe(false)
    })
})
