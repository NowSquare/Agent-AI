@extends('emails.layouts.base')

@section('title', __('emails.action_response.title'))

@section('header', null)
@section('subheader', null)

@section('content')
    <div style="margin: 0 0 16px 0; text-align: left;">
        {!! \Illuminate\Support\Str::of($responseContent)->markdown(['html_input' => 'allow']) !!}
    </div>

    @if(!empty($detailsUrl))
        <div>
            <a href="{{ $detailsUrl }}" class="button">
                {{ __('emails.action_response.view_details_button') }}
            </a>
        </div>
    @endif
@endsection