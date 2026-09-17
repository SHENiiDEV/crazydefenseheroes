@php($title = 'Terms & Conditions')
@extends('legal.layout')

@section('content')
    <h1>Terms &amp; Conditions</h1>
    <p class="meta">Version {{ config('registration.terms_version') }} · Effective 17 September 2026</p>
    <h2>1. The service</h2>
    <p>Crazy Defense Hereoes is an online tower defense game with endless waves, digital items, boosts and diamond top-ups. You must use the service lawfully and keep your account secure.</p>
    <h2>2. Accounts and purchases</h2>
    <p>You are responsible for the information supplied during registration. Digital items are licensed for use in the game, have no cash value outside the service, and are delivered only after a confirmed payment.</p>
    <h2>3. Fair play</h2>
    <p>Automation, abuse of payment flows, fraud, harassment and attempts to disrupt the service are prohibited. We may suspend accounts that materially breach these terms.</p>
    <h2>4. Contact</h2>
    <p>Questions about the service or a purchase can be sent to <a href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>.</p>
@endsection
