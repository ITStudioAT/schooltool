<x-mail::message>
# Ihre Unterrichts-Datensicherung

Ihre persönliche Datensicherung vom {{ $createdAtLabel }} wurde erstellt. Die Sicherungsdatei finden Sie im Anhang.

Bewahren Sie die Datei sicher auf. Sie können sie in Unterricht → Einstellungen → Datensicherung wieder hochladen und nach Bestätigung wiederherstellen.

Die Datei ist verschlüsselt und Ihrem Benutzerkonto zugeordnet. Zum Wiederherstellen benötigen Sie dasselbe Benutzerkonto und den passenden Anwendungsschlüssel dieser schooltool-Installation. Ohne diesen Schlüssel kann die Datei nicht entschlüsselt werden.

Freundliche Grüße<br>
{{ config('app.name') }}
</x-mail::message>
