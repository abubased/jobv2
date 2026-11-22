@props([
    'invoiceNo',
    'amount',
    'charge' => null,
    'additionalFieldName' => null,
    'additionalFieldValue' => null,
])

<div class="apay-payment__summary">
    <p class="apay-payment__summary-row">
        <strong class="apay-payment__translateable" data-apay-trans="invoice_no">Invoice No:</strong>&nbsp;
        <span>{{ $invoiceNo }}</span>
    </p>
    <p class="apay-payment__summary-row">
        <strong class="apay-payment__translateable" data-apay-trans="total">Total Amount:</strong>&nbsp;
        <span>{{ $amount }}</span>
    </p>
    @if (! is_null($charge))
        <p class="apay-payment__summary-row">
            <strong class="apay-payment__translateable" data-apay-trans="charge">Charge:</strong>&nbsp;
            <span>{{ $charge }}</span>
        </p>
    @endif
    @if ($additionalFieldName)
        <p class="apay-payment__summary-row">
            <strong class="apay-payment__translateable">{{ $additionalFieldName }}</strong>&nbsp;
            <span>{{ $additionalFieldValue }}</span>
        </p>
    @endif
</div>
