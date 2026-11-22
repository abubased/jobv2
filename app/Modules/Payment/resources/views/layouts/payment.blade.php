<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title', 'Payment')</title>
    <link rel="stylesheet" type="text/css" href="{{ route('apay-payment.asset', ['path' => 'css/payment.css']) }}">
</head>

<body>
    @php
        $translations = [
            'en' => (array) trans('payment::payment', [], 'en'),
            'bn' => (array) trans('payment::payment', [], 'bn'),
        ];
    @endphp

    <div
        class="apay-payment"
        data-apay-payment
        data-apay-lang="en"
        data-apay-return-url="{{ $session->get('return_url') }}"
        @isset($paymentStatus) data-apay-payment-status="{{ $paymentStatus }}" @endisset
    >
        <div class="apay-payment__container">
            @yield('content')
        </div>
    </div>

    {{--
        A raw JSON data island, not Js::from() — that helper emits a
        JSON.parse('...') *expression* meant to be executed as JS, which
        breaks when read back via textContent + JSON.parse() client-side.
        Escaping "/" prevents a "</script>" inside a translation string
        from closing this tag early.
    --}}
    <script type="application/json" data-apay-translations>{!! str_replace('/', '\/', json_encode($translations, JSON_UNESCAPED_UNICODE)) !!}</script>
    <script type="module" src="{{ route('apay-payment.asset', ['path' => 'js/entry.js']) }}"></script>
    @yield('scripts')
</body>

</html>
