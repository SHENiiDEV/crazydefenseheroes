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
    <nav>
        @foreach (['terms' => 'Terms & Conditions', 'privacy' => 'Privacy Policy', 'refunds' => 'Delivery & Refunds', 'security' => 'Security'] as $type => $label)
            <a href="{{ route('api.v1.legal.page', ['document' => $type], false) }}">{{ $label }}</a>@unless ($loop->last) · @endunless
        @endforeach
    </nav>
@endsection
