<x-mail::message>
# Erinnerung

**Unterricht:** {{ $entry->teachingCourse->title }}

**Schüler/in:** {{ $entry->user?->last_name }} {{ $entry->user?->first_name }}

**Fällig:** {{ $entry->due_date->format('d.m.Y') }}{{ $entry->due_time ? ' um '.$entry->due_time.' Uhr' : '' }}

{{ $entry->description }}

Diese persönliche Erinnerung wurde im Unterricht gespeichert.
</x-mail::message>
