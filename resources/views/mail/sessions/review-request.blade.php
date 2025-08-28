@component('mail::message')
    # Molba za pregled sesije

    {{ $session->title }} — {{ $session->started_at->format('d.m.Y.') }}

    Broj ulova: {{ $session->catches()->count() }}

    @component('mail::button', ['url' => $reviewUrl])
        Otvori sesiju za pregled
    @endcomponent

    Hvala,<br>
    {{ config('app.name') }}
@endcomponent
