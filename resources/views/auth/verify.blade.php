@extends('layouts.auth')

@section('title', __('auth.verify.title'))

@section('header', config('app.name'))

@section('subheader')
    {{ __('auth.verify.subtitle') }}
@endsection

@section('content')
    <form id="verifyForm" class="space-y-6" action="{{ route('auth.verify') }}" method="POST">
        @csrf

        <!-- Hidden email field -->
        <input type="hidden" name="email" value="{{ $email }}">

        <!-- Code Input -->
        <div>
            <label class="block text-sm font-medium text-center text-gray-700 dark:text-gray-300 mb-4">
                {{ __('auth.verify.code_label') }}
            </label>

            <div data-code-input
                 data-name="code"
                 data-length="6"
                 data-auto-submit="false"
                 class="mb-4">
            </div>

            @error('code')
                <p class="mt-2 text-sm text-center text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <input type="checkbox" name="remember" id="remember"
                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 dark:border-gray-700 rounded">
                <label for="remember" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                    {{ __('auth.verify.remember_me') }}
                </label>
            </div>
        </div>

        <!-- Submit Button -->
        <div>
            <button type="submit" id="submitButton"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                <span class="inline-flex items-center">
                    <i data-lucide="log-in" class="mr-2 h-5 w-5"></i>
                    {{ __('auth.verify.sign_in') }}
                </span>
            </button>
        </div>

        <!-- Resend Code -->
        <div class="text-center">
            <button type="button" onclick="resendCode()"
                class="text-sm text-primary-600 dark:text-primary-400 hover:text-primary-500 dark:hover:text-primary-300">
                {{ __('auth.verify.resend_code') }}
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
            {{ __('auth.verify.verifying_code') }}
        </div>
    </div>
@endsection

@section('footer')
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('auth.verify.help_text') }}
    </p>
@endsection

@push('scripts')
<script>
    const form = document.getElementById('verifyForm');
    const codeInput = document.querySelector('[data-code-input]');

    // Show loading state on submit
    form.addEventListener('submit', () => {
        document.getElementById('submitButton').classList.add('opacity-50', 'cursor-not-allowed');
        document.getElementById('loadingState').classList.remove('hidden');
    });

    // Handle server-side validation errors
    @if ($errors->has('code'))
        codeInput.setError();
    @endif

    // Resend code function
    async function resendCode() {
        const email = document.querySelector('input[name="email"]').value;
        const button = document.querySelector('button[onclick="resendCode()"]');
        
        // Disable button
        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-not-allowed');
        
        try {
            const response = await fetch('{{ route("auth.challenge") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ email }),
            });

            if (response.ok) {
                // Show success message
                button.textContent = '{{ __("auth.verify.code_sent") }}';
                setTimeout(() => {
                    button.textContent = '{{ __("auth.verify.resend_code") }}';
                    button.disabled = false;
                    button.classList.remove('opacity-50', 'cursor-not-allowed');
                }, 5000);
            } else {
                throw new Error('Failed to resend code');
            }
        } catch (error) {
            console.error('Error:', error);
            button.textContent = '{{ __("auth.verify.resend_failed") }}';
            setTimeout(() => {
                button.textContent = '{{ __("auth.verify.resend_code") }}';
                button.disabled = false;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
            }, 5000);
        }
    }
</script>
@endpush