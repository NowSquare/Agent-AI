@extends('emails.layouts.base')

@section('title', __('emails.options.title'))

@section('header', __('emails.options.header'))
@section('subheader', __('emails.options.subheader'))

@section('content')
    <p>{{ __('emails.options.intro') }}</p>

    <div class="flex flex-col items-center gap-4 mt-4">
        @foreach($options as $option)
            <a href="{{ $option['url'] }}" class="button" style="width: 100%; max-width: 300px;">
                {{ $option['label'] }}
            </a>
        @endforeach
    </div>

    <p class="text-center mt-4" style="color: var(--text-secondary);">
        {{ __('emails.options.reply_notice') }}
    </p>
@endsection

@section('footer')
    <p>{{ __('emails.options.help_message') }}</p>
@endsection