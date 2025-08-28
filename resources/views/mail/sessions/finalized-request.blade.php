@component('mail::message')
    # Pregled finalizovane sesije (izlaska na vodu)

    {{ $session->title }} — {{ $session->started_at->format('d.m.Y.') }}

    Broj ulova: {{ $session->catches()->count() }}

    Status sesije: {{ $session->final_result }}

    @component('mail::button', ['url' => $reviewUrl])
        Otvori sesiju za pregled
    @endcomponent

    Hvala,<br>
    {{ config('app.name') }}
@endcomponent
