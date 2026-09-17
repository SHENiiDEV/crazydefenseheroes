@php($title = 'Privacy Policy')
@extends('legal.layout')

@section('content')
    <h1>Privacy Policy</h1>
    <p class="meta">Version {{ config('registration.privacy_version') }} · Effective 17 September 2026</p>
    <h2>1. Information we collect</h2>
    <p>We collect the account, contact and address details you provide, along with game progress, purchase records and technical information needed to operate and protect the service.</p>
    <h2>2. Why we use it</h2>
    <p>We use this information to create your account, verify your email, deliver digital purchases, issue invoices, prevent fraud and answer support requests.</p>
    <h2>3. Email and retention</h2>
    <p>Transactional email is sent through the configured Private Email SMTP service. We retain information only as long as needed for the service, accounting, security and legal obligations.</p>
    <h2>4. Your choices</h2>
    <p>For access, correction or deletion requests, contact <a href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>.</p>
@endsection
