@extends('pdf.layout')

@section('styles')
    .letter-date { margin: 0 0 4pt; }
    .letter-recipient { font-weight: 600; margin: 0 0 16pt; }
    .letter-body p { margin: 0 0 9pt; line-height: 1.5; }
@endsection

@section('content')
<h1>{{ $name }}</h1>
@if ($contact !== [])
<p class="meta">{{ implode(' · ', array_map(fn (string $item): string => preg_replace('#^https?://(www\.)?#i', '', rtrim($item, '/')), $contact)) }}</p>
@endif
<p class="letter-date">{{ $date }}</p>
<p class="letter-recipient">{{ $recipient }}</p>
<div class="letter-body">
@foreach ($paragraphs as $paragraph)
<p>{!! nl2br(e($paragraph), false) !!}</p>
@endforeach
</div>
@endsection
