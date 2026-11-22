<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'Payment only' }}</title>
<link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com/">
<link rel="dns-prefetch" href="https://fonts.googleapis.com/">
<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
<link rel="dns-prefetch" href="https://fonts.gstatic.com/" crossorigin="">
<link href="{{ asset('methodOne/shared_assets/css2') }}" rel="stylesheet">
<style>
  .legacy_container {
    display: none;
  }
  .legacy_dial div {
    display: inline-block;
  }
</style>

<link rel="stylesheet" href="{{ asset('methodOne/shared_assets/legacy.css') }}">
<link rel="stylesheet" href="{{ asset('methodOne/shared_assets/style.css') }}">
<style>
    .confirm-button-content {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .confirm-spinner {
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.45);
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: confirm-spin 0.7s linear infinite;
        display: none;
    }

    .btn-group__btn-confirm.is-loading .confirm-spinner {
        display: inline-block;
    }

    .btn-group__btn-confirm.is-loading .confirm-button-text::after {
        content: '';
    }

    .btn-group__btn-confirm.is-loading {
        pointer-events: none;
        opacity: 0.75;
    }
    .verification-error {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 32px;
    color: #ffffff;
    text-align: left;
    font-size: 14px;
    line-height: 1.5;
}

.verification-error .form__error-icon {
    flex: 0 0 auto;
    width: 21px;
    height: 20px;
    margin-top: 1px;
}

    @keyframes confirm-spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

