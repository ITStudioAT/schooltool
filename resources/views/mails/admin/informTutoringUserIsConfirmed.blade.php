<x-mail::message :logo="$logo">
# {{ $data['subject'] }}


<h4>Herzliche Gratulation!</h4>

Du wurdest soeben für das Nachhiletool freigeschaltet!
Du kannst dich ab sofort einloggen.

BENUTZER: **{{ $data['full_name']}}**    

E-MAIL: {{ $data['email']  }}


<x-mail::button :url="$data['login_url']">
Einloggen
</x-mail::button>


Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>