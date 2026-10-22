@php($title = $document['title'])
@extends('legal.layout')
@section('content')
    <h1>{{ $document['title'] }}</h1>
    <p class="meta">Updated {{ $document['version'] }}</p>
    @foreach ($document['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>
        @foreach ($section['paragraphs'] as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    @endforeach
    <nav><a href="/legal/terms">Terms &amp; Conditions</a> · <a href="/legal/privacy">Privacy Policy</a> · <a href="/legal/refunds">Delivery &amp; Refunds</a> · <a href="/legal/security">Security</a></nav>
@endsection
