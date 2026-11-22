<div class="apay-payment__messages" data-apay-messages>
    @if ($errors->any())
        <span class="apay-payment__text-error">{{ $errors->first() }}</span>
    @elseif (session('status'))
        <span class="apay-payment__text-info">{{ session('status') }}</span>
    @endif
</div>
