<table style="border-collapse: collapse; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; line-height: 1.05; width: 100%;">
    <tr>
        <td style="background: #182a52; border-radius: 5px 0 0 5px; color: #ffffff; padding: 4px 7px; width: 58%;">
            <div style="color: #bcd1ff; font-size: 9pt; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;">
                Kursübersicht
            </div>
            <div style="font-size: 12pt; font-weight: 700; line-height: 1.05; margin-top: 1px;">
                {{ $course_title !== '' ? $course_title : 'Kurs' }}
            </div>
            <div style="color: #dce7ff; font-size: 9pt; margin-top: 1px;">
                {{ $school_name !== '' ? $school_name : 'Schule' }}
                · {{ $schoolyear_name !== '' ? $schoolyear_name : 'Schuljahr –' }}
            </div>
        </td>
        <td style="background: #eef3fb; border-radius: 0 5px 5px 0; color: #273b60; padding: 3px 7px; width: 42%;">
            <table style="border-collapse: collapse; font-size: 9pt; line-height: 1.05; width: 100%;">
                <tr>
                    <td style="color: #6c7a92; padding: 0 6px 1px 0; width: 35%;">Zeitraum</td>
                    <td style="font-weight: 700; padding: 0 0 1px;">{{ $period_label }}</td>
                </tr>
                <tr>
                    <td style="color: #6c7a92; padding: 0 6px 1px 0;">Termine</td>
                    <td style="font-weight: 700; padding: 0 0 1px;">{{ $date_range }}</td>
                </tr>
                <tr>
                    <td style="color: #6c7a92; padding: 0 6px 1px 0;">Lehrkraft</td>
                    <td style="font-weight: 700; padding: 0 0 1px;">
                        {{ $teacher_name !== '' ? $teacher_name : '–' }}
                        @if ($teacher_short !== '')
                            ({{ $teacher_short }})
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="color: #6c7a92; padding-right: 6px;">Klasse(n)</td>
                    <td style="font-weight: 700;">{{ $classes !== '' ? $classes : '–' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
