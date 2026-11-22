@props(['status', 'titleKey', 'messageKey'])

<div class="apay-payment__result">
    <h2 class="apay-payment__result-title apay-payment__translateable" data-apay-trans="{{ $titleKey }}">
        {{ __("payment::payment.$titleKey") }}
    </h2>

    <p class="apay-payment__translateable" data-apay-trans="{{ $messageKey }}">
        {{ __("payment::payment.$messageKey") }}
    </p>

    <p class="apay-payment__result-note apay-payment__translateable" data-apay-trans="returning_message">
        {{ __('payment::payment.returning_message') }}
    </p>

    <div class="apay-payment__action-buttons">
        <button
            type="button"
            class="apay-payment__btn apay-payment__translateable"
            data-apay-return="{{ $status }}"
            data-apay-trans="continue_btn"
        >
            {{ __('payment::payment.continue_btn') }}
        </button>
    </div>
</div>
