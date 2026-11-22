@extends('payment::layouts.payment')

@php $paymentStatus = 'failed'; @endphp

@section('title', 'Payment: Failed')

@section('content')
    <x-payment::merchant-info :merchant-name="$session->get('merchant_name')" />

    <x-payment::result-panel status="failed" title-key="failed_title" message-key="failed_message" />
@endsection
