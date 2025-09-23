@extends('emails.layouts.base')

@section('title', __('emails.auth.title'))

@section('header', __('emails.auth.header'))
@section('subheader', __('emails.auth.subheader'))

@section('content')
    <p>{{ __('emails.auth.greeting') }}</p>

    <p>{{ __('emails.auth.code_instructions') }}</p>

    <div class="text-center">
        <div style="font-size: 32px; font-weight: 600; color: var(--accent-color); letter-spacing: 0.1em; margin: 24px 0;">
            {{ $code }}
        </div>
    </div>

    <p class="text-center">{{ __('emails.auth.magic_link_instructions') }}</p>

    <div class="flex justify-center mt-4 mb-4">
        <a href="{{ $magicLink }}" class="button">
            {{ __('emails.auth.sign_in_button') }}
        </a>
    </div>

    <p class="text-center" style="color: var(--text-secondary);">
        {{ __('emails.auth.expires_at', ['time' => $expiresAt->format('g:i A T')]) }}
    </p>
@endsection

@section('footer')
    <p>{{ __('emails.auth.security_notice') }}</p>
@endsection