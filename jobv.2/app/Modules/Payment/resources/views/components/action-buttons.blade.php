@props([
    'primaryLabelKey' => 'proceed_btn',
    'primaryLabel' => 'Proceed',
    'showResend' => false,
    'showClose' => true,
])

<div class="apay-payment__action-buttons">
    <button
        type="submit"
        class="apay-payment__btn apay-payment__translateable"
        data-apay-trans="{{ $primaryLabelKey }}"
        data-apay-submit
    >
        {{ $primaryLabel }}
    </button>

    @if ($showResend)
        <button type="button" class="apay-payment__btn apay-payment__translateable" data-apay-resend data-apay-trans="resend_code_btn">
            Resend Code
        </button>
    @endif

    @if ($showClose)
        <button type="button" class="apay-payment__btn apay-payment__translateable" data-apay-return="cancelled" data-apay-trans="close_btn">
            Close
        </button>
    @endif
</div>
<div class="ng-logo">
    <img class="img-fluid" src="{{ route('apay-payment.asset', ['path' => 'assets/ng_logo.png']) }}" alt="">
</div>
