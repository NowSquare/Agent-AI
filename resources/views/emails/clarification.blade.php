@extends('emails.layouts.base')

@section('title', __('emails.clarification.title'))

@section('header', __('emails.clarification.header'))
@section('subheader', __('emails.clarification.subheader'))

@section('content')
    <div class="mb-4">
        <p>{{ __('emails.clarification.interpretation') }}</p>
        
        <div style="background: var(--bg-secondary); padding: 16px; border-radius: 8px; margin: 16px 0;">
            <p style="margin: 0; color: var(--text-primary);">{{ $summary }}</p>
        </div>
    </div>

    <div class="flex flex-col items-center gap-4">
        <a href="{{ $confirmUrl }}" class="button">
            {{ __('emails.clarification.confirm_button') }}
        </a>
        
        <a href="{{ $cancelUrl }}" class="button button-secondary">
            {{ __('emails.clarification.cancel_button') }}
        </a>
    </div>

    <p class="text-center mt-4" style="color: var(--text-secondary);">
        {{ __('emails.clarification.reply_notice') }}
    </p>
@endsection

@section('footer')
    <p>{{ __('emails.clarification.help_message') }}</p>
@endsection