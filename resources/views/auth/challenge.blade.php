@extends('layouts.auth')

@section('title', __('auth.challenge.title'))

@section('header')
    <div class="flex items-center justify-center gap-3">
        <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-900">
            <i data-lucide="mail" class="h-6 w-6 text-sky-700 dark:text-sky-300"></i>
        </div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
            {{ __('auth.challenge.title') }}
        </h1>
    </div>
@endsection

@section('subheader')
    <p class="mt-3 text-slate-600 dark:text-slate-400">
        {{ __('auth.challenge.subtitle') }}
    </p>
@endsection

@section('content')
    <form id="challengeForm" class="space-y-6" action="{{ route('auth.challenge') }}" method="POST">
        @csrf

        <!-- Email Input -->
        <div>
            <label for="email" class="sr-only">{{ __('auth.challenge.email_label') }}</label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <i data-lucide="at-sign" class="h-5 w-5 text-slate-400 dark:text-slate-500"></i>
                </div>
                <input type="email" name="email" id="email" required
                    class="block w-full rounded-xl border-0 py-3 pl-10 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-sky-600 dark:bg-slate-800 dark:text-white dark:ring-slate-700 dark:placeholder:text-slate-500 dark:focus:ring-sky-500 sm:text-sm @error('email') ring-red-300 dark:ring-red-500 text-red-900 dark:text-red-400 placeholder:text-red-300 dark:placeholder:text-red-500 focus:ring-red-500 dark:focus:ring-red-500 @enderror"
                    placeholder="{{ __('auth.challenge.email_placeholder') }}"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    autofocus>
            </div>
            @error('email')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <label class="relative flex items-start">
                <div class="flex h-5 items-center">
                    <input type="checkbox" name="remember" id="remember"
                        class="h-4 w-4 appearance-none rounded border border-slate-300 bg-white transition checked:border-sky-600 checked:bg-sky-600 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-700 dark:checked:border-sky-500 dark:checked:bg-sky-500 dark:hover:border-slate-500 dark:focus:ring-offset-slate-900">
                    <svg class="pointer-events-none absolute h-4 w-4 text-white opacity-0 transition [input:checked+&]:opacity-100" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12.207 4.793a1 1 0 0 1 0 1.414l-5 5a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L6.5 9.086l4.293-4.293a1 1 0 0 1 1.414 0z" fill="currentColor"/>
                    </svg>
                </div>
                <span class="ml-2 select-none text-sm text-slate-600 dark:text-slate-400">
                    {{ __('auth.challenge.remember_me') }}
                </span>
            </label>
        </div>

        <!-- Submit Button -->
        <div>
            <button type="submit" id="submitButton"
                class="relative w-full rounded-xl bg-slate-900 py-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 dark:bg-sky-600 dark:hover:bg-sky-500 dark:focus-visible:outline-sky-600">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <i data-lucide="arrow-right" class="h-5 w-5"></i>
                </span>
                <span>{{ __('auth.challenge.continue') }}</span>
            </button>
        </div>
    </form>

    <!-- Loading State -->
    <div id="loadingState" class="hidden">
        <div class="mt-4 flex items-center justify-center gap-2 text-sm text-slate-600 dark:text-slate-400">
            <svg class="h-5 w-5 animate-spin text-sky-600 dark:text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ __('auth.challenge.sending_code') }}
        </div>
    </div>
@endsection

@section('footer')
    <p class="text-sm text-slate-500 dark:text-slate-400">
        {{ __('auth.challenge.help_text') }}
    </p>
@endsection

@push('scripts')
<script>
    document.getElementById('challengeForm').addEventListener('submit', function() {
        document.getElementById('submitButton').classList.add('opacity-50', 'cursor-not-allowed');
        document.getElementById('loadingState').classList.remove('hidden');
    });
</script>
@endpush