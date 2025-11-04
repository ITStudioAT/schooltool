<x-mail::message :logo="$logo">
# {{ $data['subject'] }}


Wir informieren sie darüber, dass folgende Anmeldung gebucht wurde:

**{{ $data['register_name']}}**    


**{{ $data['student_last_name'] . ' ' . $data['student_first_name'] }}**

{{  'Datum: ' .  $data['date'] }}<br>
{{  'Uhrzeit: ' .  $data['from'] . ' - ' . $data['to'] }}<br>
{{ $data['note'] ?? '' }}


Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>