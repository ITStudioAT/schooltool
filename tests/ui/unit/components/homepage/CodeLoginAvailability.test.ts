import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import RegisterPage from '@/pages/homepage/register/Register.vue'
import StudentPage from '@/pages/homepage/student/Student.vue'
import ParentAccessPanel from '@/pages/homepage/student/components/ParentAccessPanel.vue'
import StudentNavigationDrawer from '@/pages/homepage/student/components/StudentNavigationDrawer.vue'
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

    it('student page opens child selection after a parent code is verified', async () => {
        const context: Record<string, any> = {
            isCodeLoginAvailable: true,
            canSubmitCode: true,
            data: { login_code: '123456' },
            login_step: 'code_sent',
            selected_parent_student_id: 99,
            studentStore: {
                data: { status: 'select_student' },
                loginStepCode: vi.fn().mockResolvedValue(true),
            },
        }

        await (StudentPage as any).methods.submitCode.call(context)

        expect(context.login_step).toBe('select_student')
        expect(context.selected_parent_student_id).toBeNull()
    })

    it('student page submits the selected child and opens the overview', async () => {
        const push = vi.fn()
        const loginStepParentStudent = vi.fn().mockResolvedValue(true)
        const context: Record<string, any> = {
            selected_parent_student_id: 42,
            studentStore: {
                data: { status: 'login_ok' },
                loginStepParentStudent,
            },
            $router: { push },
        }

        await (StudentPage as any).methods.submitParentStudent.call(context)

        expect(loginStepParentStudent).toHaveBeenCalledWith(42)
        expect(push).toHaveBeenCalledWith('/student/overview')
    })

    it('student drawer hides password changes for parent viewers', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/homepage/student/components/StudentNavigationDrawer.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("viewer_type !== 'parent' && currentRoute !== 'password'")
    })

    it('student drawer lets a parent return to child selection', () => {
        const push = vi.fn()
        const context: Record<string, any> = {
            drawerModel: true,
            $router: { push },
        }

        ;(StudentNavigationDrawer as any).methods.handleParentStudentChange.call(context)

        expect(context.drawerModel).toBe(false)
        expect(push).toHaveBeenCalledWith({ path: '/student', query: { select_child: '1' } })
    })

    it('parent access panel offers child selection directly', () => {
        const push = vi.fn()

        ;(ParentAccessPanel as any).methods.changeChild.call({ $router: { push } })

        expect(push).toHaveBeenCalledWith({ path: '/student', query: { select_child: '1' } })
    })

    it('student pages display the parent access panel outside the navigation drawer', () => {
        const pagePaths = [
            'resources/js/pages/homepage/student/overview/Overview.vue',
            'resources/js/pages/homepage/student/overview/myCourse/MyCourse.vue',
            'resources/js/pages/homepage/student/profile/Profile.vue',
        ]

        pagePaths.forEach((pagePath) => {
            const source = readFileSync(resolve(process.cwd(), pagePath), 'utf8')

            expect(source).toContain('<ParentAccessPanel />')
        })
    })

    it('student page reloads and preselects eligible children when a parent switches child', async () => {
        const selectSchool = vi.fn(function (this: Record<string, any>) {
            this.school = { id: 5 }
        })
        const context: Record<string, any> = {
            studentStore: {
                data: {
                    school_id: 5,
                    selected_student_import_id: 77,
                    students: [{ id: 77, name: 'Anna Adler' }, { id: 88, name: 'Berta Bauer' }],
                },
                loadParentStudents: vi.fn().mockResolvedValue(true),
                loadConfig: vi.fn().mockResolvedValue(true),
            },
            school: null,
            login_step: 'email',
            selected_parent_student_id: null,
            selectSchool,
        }

        const opened = await (StudentPage as any).methods.openParentStudentSelection.call(context)

        expect(opened).toBe(true)
        expect(selectSchool).toHaveBeenCalledWith(5)
        expect(context.login_step).toBe('select_student')
        expect(context.selected_parent_student_id).toBe(77)
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
