<table style="border-collapse: collapse; border-top: 1px solid #d5deec; color: #6b7890; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; line-height: 1.05; padding-top: 2px; width: 100%;">
    <tr>
        <td style="padding-top: 2px; width: 38%;">
            {{ $course_title !== '' ? $course_title : 'Kursübersicht' }}
        </td>
        <td style="padding-top: 2px; text-align: center; width: 24%;">
            {{ $student_count }} Schüler:innen · {{ $date_count }} Termine
        </td>
        <td style="padding-top: 2px; text-align: right; width: 38%;">
            Erstellt {{ $generated_at }} · Seite @pageNumber
        </td>
    </tr>
</table>
