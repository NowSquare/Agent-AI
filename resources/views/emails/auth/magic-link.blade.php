@extends('emails.layouts.base')

@section('title')
    {{ __('auth.magic_link.title') }}
@endsection

@section('preview')
    {{ __('auth.magic_link.preview') }}
@endsection

@section('content')
    <p class="mb-8">
        {{ __('auth.magic_link.greeting') }}
    </p>

    <div class="text-center mb-8">
        <a href="{{ $url }}" class="inline-block px-6 py-3 bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 text-white font-medium rounded-lg text-base">
            {{ __('auth.magic_link.button') }}
        </a>
    </div>

    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        {{ __('auth.magic_link.expiry_notice', ['minutes' => 60]) }}
    </p>

    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('auth.magic_link.security_notice') }}
    </p>

    <div class="mt-8 text-xs text-gray-500 dark:text-gray-400">
        {{ __('auth.magic_link.alternative_text') }}<br>
        <a href="{{ $url }}" class="text-primary-600 dark:text-primary-400 break-all">
            {{ $url }}
        </a>
    </div>
@endsection
