@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Main Thread Content --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                <div class="p-6">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $thread->subject }}
                    </h1>

                    {{-- Messages Timeline --}}
                    <div class="mt-8 space-y-6">
                        @foreach($thread->emailMessages as $message)
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-gray-100 dark:bg-gray-700">
                                        <span class="text-sm font-medium leading-none text-gray-700 dark:text-gray-300">
                                            {{ substr($message->from_name ?? $message->from_email, 0, 1) }}
                                        </span>
                                    </span>
                                </div>
                                <div class="flex-grow min-w-0">
                                    <div class="flex justify-between items-center mb-1">
                                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $message->from_name ?? $message->from_email }}
                                        </h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $message->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                    <div class="prose dark:prose-invert max-w-none text-sm text-gray-700 dark:text-gray-300">
                                        {!! nl2br(e($message->clean_text)) !!}
                                    </div>

                                    @if($message->attachments->isNotEmpty())
                                        <div class="mt-4">
                                            <h4 class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Attachments
                                            </h4>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($message->attachments as $attachment)
                                                    <a href="{{ route('attachments.show', $attachment) }}"
                                                       class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                        <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                        </svg>
                                                        {{ $attachment->original_name }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Thread Metadata --}}
            <x-thread-metadata :thread="$thread" :show-history="true" />

            {{-- Actions --}}
            @if($thread->actions->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">
                        Actions
                    </h3>
                    <div class="space-y-3">
                        @foreach($thread->actions as $action)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-700 dark:text-gray-300">
                                    {{ $action->type }}
                                </span>
                                <span class="text-xs px-2 py-1 rounded-full
                                    @if($action->status === 'completed') bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100
                                    @elseif($action->status === 'failed') bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100
                                    @else bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                                    @endif">
                                    {{ $action->status }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
