@extends('payment::layouts.payment')

@section('title', 'Payment: Account Number')

@section('content')
    <x-payment::lang-switcher />

    <x-payment::merchant-info :merchant-name="$session->get('merchant_name')" />

    <x-payment::payment-summary
        :invoice-no="$session->get('reference_no')"
        :amount="$session->get('amount')"
        :charge="$session->get('charge')"
    />

    <div class="apay-payment__form-container apay-payment__form-container--account">
        <form
            method="POST"
            action="{{ route('apay-payment.steps.account-number.store') }}"
            data-apay-form
            data-apay-step="account-number"
        >
            @csrf

            <div class="apay-payment__form-group">
                <label class="apay-payment__translateable" data-apay-trans="account_number_label">
                    Your Account Number
                </label>

                <x-payment::box-input name="account_number" :groups="[3, 4, 4]" :compact="true" />

                <div class="apay-payment__form-check">
                    <label class="apay-payment__translateable">
                        <span data-apay-trans="terms_condition_label">
                            By clicking or tapping "Proceed" you are agreeing to our
                        </span>

                        <strong>
                            <a
                                href="#"
                                target="_blank"
                                class="apay-payment__translateable"
                                data-apay-trans="terms_condition_link"
                            >
                                Terms and Conditions
                            </a>
                        </strong>
                    </label>
                </div>
            </div>

            <x-payment::message-box />

            <x-payment::action-buttons />
        </form>
    </div>
@endsection
