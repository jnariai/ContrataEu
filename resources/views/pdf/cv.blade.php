@extends('pdf.layout')

{{-- $body is the CV's Markdown rendered by Str::markdown() with raw HTML stripped and unsafe links dropped. --}}
@section('content')
{!! $body !!}
@endsection
