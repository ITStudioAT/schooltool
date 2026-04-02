<x-mail::message :logo="$logo">
# {{ $data['subject'] }}

Ihre Restaurantanmeldung wurde bestätigt.

BENUTZER: **{{ $data['full_name'] }}**

E-MAIL: {{ $data['email'] }}

Die Freischaltung für das Restaurant ist abgeschlossen. Sie können ab sofort Menüs bestellen.

<x-mail::button :url="$data['restaurant_url']">
Zur Restaurantseite
</x-mail::button>

Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name'] }}
</x-mail::message>
