<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Schooltool-Anmeldung erforderlich</title></head>
<body style="margin:0;background:#f7f8fa;color:#173458;font:18px/1.6 system-ui,sans-serif">
    <main style="max-width:760px;margin:48px auto;padding:24px">
        <h1>Schooltool-Anmeldung erforderlich</h1>
        <p>Melde dich mit deinem Schooltool-Konto an. Öffne anschließend die Erklärung in diesem Fenster.</p>
        <p><a href="/student" target="_blank" rel="noopener">Schüleranmeldung öffnen</a></p>
        <p><a href="{{ route('login') }}" target="_blank" rel="noopener">Lehrkraftanmeldung öffnen</a></p>
        <p><a href="{{ request()->url() }}">Erklärung nach Anmeldung öffnen</a></p>
    </main>
</body>
</html>
