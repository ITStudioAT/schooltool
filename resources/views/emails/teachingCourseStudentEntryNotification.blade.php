<x-mail::message>
# Verständigung

Guten Tag {{ $notification->recipient_label }},

Sie erhalten eine Verständigung zu {{ trim("{$student->first_name} {$student->last_name}") }}.

**Kurs:** {{ $course->title }}  
**Eintrag:** {{ $definition->name }} ({{ $entry->type }})  
**Datum:** {{ $entry->date?->format('d.m.Y') ?? '–' }}

@if($entry->description)
**Beschreibung:**  
{{ $entry->description }}
@endif

Bitte bestätigen Sie über den folgenden Link, dass Sie diese Information erhalten haben.

<x-mail::button :url="$confirmationUrl">
Empfang bestätigen
</x-mail::button>

Freundliche Grüße<br>
{{ config('app.name') }}

<img src="{{ $trackingUrl }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0" />
</x-mail::message>
