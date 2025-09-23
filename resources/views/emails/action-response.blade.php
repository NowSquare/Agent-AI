@extends('emails.layouts.base')

@section('title', __('emails.action_response.title'))

@section('header', __('emails.action_response.' . ($success ? 'success.header' : 'failure.header')))
@section('subheader', __('emails.action_response.' . ($success ? 'success.subheader' : 'failure.subheader')))

@section('content')
    <div class="text-center mb-4">
        @if($success)
            <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
        @else
            <div style="font-size: 48px; margin-bottom: 16px;">❌</div>
        @endif

        <div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px; margin: 16px 0;">
            <p style="margin: 0; color: var(--text-primary);">{{ $message }}</p>
        </div>
    </div>

    @if($detailsUrl)
        <div class="flex justify-center">
            <a href="{{ $detailsUrl }}" class="button">
                {{ __('emails.action_response.view_details_button') }}
            </a>
        </div>
    @endif
@endsection