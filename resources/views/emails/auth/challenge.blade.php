@extends('emails.layouts.base')

@section('title')
    {{ __('auth.challenge.title') }}
@endsection

@section('preview')
    {{ __('auth.challenge.preview') }}
@endsection

@section('content')
    <p class="mb-8">
        {{ __('auth.challenge.greeting') }}
    </p>

    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-6 mb-8 text-center">
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ __('auth.challenge.code_instruction') }}
        </p>
        <div class="font-mono text-2xl tracking-wider bg-white dark:bg-gray-700 py-4 rounded">
            {{ $challenge->code }}
        </div>
    </div>

    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        {{ __('auth.challenge.expiry_notice', ['minutes' => 15]) }}
    </p>

    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('auth.challenge.security_notice') }}
    </p>
@endsection
