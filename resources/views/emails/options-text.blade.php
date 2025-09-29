@php
    /**
     * Plain-text fallback for options email
     * Vars: $options, $replyUrl
     */
@endphp

{{ __('emails.options.header') }}

{{ __('emails.options.intro') }}

@foreach($options as $option)
- {{ $option['label'] }}: {{ $option['url'] }}
@endforeach

{{ __('emails.options.reply_notice') }}

