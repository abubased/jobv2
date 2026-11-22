@props([
    'name',
    'groups' => [4],
    'type' => 'text',
    'divider' => '-',
    'compact' => false,
])

@php $isFirstInput = true; @endphp

<div
    class="apay-payment__box-inputs @if ($compact) apay-payment__box-inputs--compact @endif"
    data-apay-box-input
    data-apay-box-input-name="{{ $name }}"
>
    @foreach ($groups as $groupIndex => $count)
        @if ($groupIndex > 0)
            <span class="apay-payment__input-divider">{{ $divider }}</span>
        @endif

        @for ($i = 0; $i < $count; $i++)
            <input
                class="apay-payment__form-control"
                inputmode="numeric"
                pattern="[0-9]*"
                type="{{ $type }}"
                maxlength="1"
                autocomplete="off"
                @if ($isFirstInput) autofocus @endif
            >
            @php $isFirstInput = false; @endphp
        @endfor
    @endforeach

    <input type="hidden" name="{{ $name }}" data-apay-box-input-value>
</div>
