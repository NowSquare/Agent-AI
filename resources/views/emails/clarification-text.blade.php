@php
    /**
     * Plain-text fallback for clarification email
     * Vars: $summary, $confirmUrl, $cancelUrl
     */
@endphp

{{ __('emails.clarification.header') }}

{{ __('emails.clarification.interpretation') }}
{{ $summary }}

{{ __('emails.clarification.confirm_button') }}: {{ $confirmUrl }}
{{ __('emails.clarification.cancel_button') }}: {{ $cancelUrl }}

{{ __('emails.clarification.reply_notice') }}

