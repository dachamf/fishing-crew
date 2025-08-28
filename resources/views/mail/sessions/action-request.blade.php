@component('mail::message')
    # Molba za potvrdu sesije (ulova)

    {{ $session->title }} — {{ $session->started_at->format('d.m.Y.') }}

    Nominovani: {{ $actor->name }} je {{ $decision }} tvoj ulov

    @component('mail::button', ['url' => $reviewUrl])
        Otvori sesiju za pregled
    @endcomponent

    Hvala,<br>
    {{ config('app.name') }}
@endcomponent
