@extends('payment::layouts.payment')

@section('title', 'Payment: PIN')

@section('content')
    <x-payment::lang-switcher />

    <x-payment::merchant-info :merchant-name="$session->get('merchant_name')" />

    <x-payment::payment-summary
        :invoice-no="$session->get('reference_no')"
        :amount="$session->get('amount')"
        :charge="$session->get('charge')"
    />

    <div class="apay-payment__form-container">
        <form
            method="POST"
            action="{{ route('apay-payment.steps.pin.store') }}"
            data-apay-form
            data-apay-step="pin"
        >
            @csrf

            <div class="apay-payment__form-group">
                <label class="apay-payment__translateable" data-apay-trans="pin_label">
                    Enter PIN
                </label>

                <x-payment::box-input name="pin" :groups="[4]" type="password" />
            </div>

            <x-payment::message-box />

            <x-payment::action-buttons />
        </form>
    </div>
@endsection
