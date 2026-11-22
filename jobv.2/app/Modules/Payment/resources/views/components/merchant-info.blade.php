@props(['merchantName', 'merchantIcon' => null])

<div class="apay-payment__merchant-info">
    <div class="apay-payment__top-icon">
        <img class="apay-payment__img-fluid"
             src="{{ $merchantIcon ?? route('apay-payment.asset', ['path' => 'assets/cart.png']) }}"
             alt="">
    </div>
    <div class="apay-payment__merchant-name">{{ $merchantName }}</div>
</div>
