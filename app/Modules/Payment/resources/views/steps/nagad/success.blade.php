@extends('payment::layouts.payment')

@php $paymentStatus = 'success'; @endphp

@section('title', 'Payment: Success')

@section('content')
    <x-payment::merchant-info :merchant-name="$session->get('merchant_name')" />

    <x-payment::result-panel status="success" title-key="success_title" message-key="success_message" />
@endsection
