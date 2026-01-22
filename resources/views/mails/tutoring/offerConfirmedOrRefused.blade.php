<x-mail::message :logo="$logo">
# {{ $data['subject'] }}



{{ $data['data']['student'] }}<br>
{{ $data['data']['student_email'] }}<br>

<strong>{{  $data['data']['subject'] }}</strong>


<x-mail::panel>
<u>Nachilfe-Angebot Details</u>:<br>

<strong>Titel:</strong> 
{{ $data['data']['offer']['title'] }}

<strong>Beschreibung:</strong> 
{{ $data['data']['offer']['description'] }}

<strong>Klassen:</strong> 
{{ implode(', ', array_keys(array_filter($data['data']['offer']['classes']->toArray()))) }}

<strong>Gültig bis:</strong> 
{{ $data['data']['offer']['active_until'] ? \Carbon\Carbon::parse($data['data']['offer']['active_until'])->format('d.m.Y') : 'unendlich' }}

<strong>Preis pro Stunde:</strong> 
{{ $data['data']['offer']['price_per_hour'] }}

<strong>Gruppennachhilfe:</strong>
@if($data['data']['offer']['is_group'])
JA (max. {{ $data['data']['offer']['max_group_members'] }} Teilnehmer)
@else
NEIN
@endif
</x-mail::panel>

<br><br>

Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>