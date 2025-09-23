@extends('layouts.auth')

@section('title', __('auth.challenge.title'))

@section('header', config('app.name'))

@section('subheader')
    {{ __('auth.challenge.subtitle') }}
@endsection

@section('content')
    <form id="challengeForm" class="space-y-6" action="{{ route('auth.challenge') }}" method="POST">
        @csrf

        <!-- Email Input -->
        <div>
            <label for="email" class="sr-only">{{ __('auth.challenge.email_label') }}</label>
            <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="mail" class="h-5 w-5 text-gray-400"></i>
                </div>
                <input type="email" name="email" id="email" required
                    class="block w-full pl-10 sm:text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-md focus:ring-primary-500 focus:border-primary-500 @error('email') border-red-300 text-red-900 placeholder-red-300 focus:ring-red-500 focus:border-red-500 @enderror"
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
            <div class="flex items-center">
                <input type="checkbox" name="remember" id="remember"
                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 dark:border-gray-700 rounded">
                <label for="remember" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                    {{ __('auth.challenge.remember_me') }}
                </label>
            </div>
        </div>

        <!-- Submit Button -->
        <div>
            <button type="submit" id="submitButton"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                <span class="inline-flex items-center">
                    <i data-lucide="arrow-right" class="mr-2 h-5 w-5"></i>
                    {{ __('auth.challenge.continue') }}
                </span>
            </button>
        </div>
    </form>

    <!-- Loading State -->
    <div id="loadingState" class="hidden mt-4 text-center">
        <div class="inline-flex items-center px-4 py-2 font-semibold leading-6 text-sm text-gray-800 dark:text-gray-200 transition ease-in-out duration-150 cursor-not-allowed">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-800 dark:text-gray-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ __('auth.challenge.sending_code') }}
        </div>
    </div>
@endsection

@section('footer')
    <p class="text-sm text-gray-500 dark:text-gray-400">
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