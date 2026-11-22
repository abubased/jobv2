@extends('payment::layouts.payment')

@section('title', 'Payment: Verification Code')

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
            action="{{ route('apay-payment.steps.otp.store') }}"
            data-apay-form
            data-apay-step="otp"
            data-apay-resend-url="{{ route('apay-payment.steps.otp.resend') }}"
        >
            @csrf

            <div class="apay-payment__form-group">
                <label class="apay-payment__translateable" data-apay-trans="otp_label">
                    Enter Verification Code [OTP]
                </label>

                <input
                    class="apay-payment__form-control apay-payment__text-center"
                    inputmode="numeric"
                    pattern="\d{6}"
                    placeholder="XXXXXX"
                    type="text"
                    maxlength="6"
                    name="otp"
                    autofocus
                >
            </div>

            <x-payment::message-box />

            <x-payment::action-buttons :show-resend="true" />
        </form>
    </div>
@endsection
