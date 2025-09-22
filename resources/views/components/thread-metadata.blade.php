<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4']) }}>
    {{-- Basic Metadata --}}
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Thread Information
            </h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                Version {{ $thread->version }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-500 dark:text-gray-400">Last Activity</span>
                <p class="font-medium text-gray-900 dark:text-white">
                    {{ $thread->last_activity_at?->diffForHumans() ?? 'Never' }}
                </p>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Messages</span>
                <p class="font-medium text-gray-900 dark:text-white">
                    {{ $thread->emailMessages()->count() }}
                </p>
            </div>
        </div>

        @if($thread->metadata->isNotEmpty())
            <div class="mt-4">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Custom Metadata
                </h4>
                <div class="space-y-2">
                    @foreach($thread->metadata as $meta)
                        <div class="flex items-start">
                            <span class="text-xs text-gray-500 dark:text-gray-400 min-w-[100px]">
                                {{ $meta->key }}:
                            </span>
                            <span class="text-xs text-gray-700 dark:text-gray-300 ml-2">
                                {{ is_array($meta->value) ? json_encode($meta->value) : $meta->value }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Version History (if enabled) --}}
    @if($showHistory && $thread->version_history)
        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                Version History
            </h4>
            <div class="space-y-3">
                @foreach(array_reverse($thread->version_history) as $history)
                    <div class="flex items-start text-xs">
                        <div class="flex-shrink-0 w-16 text-gray-500 dark:text-gray-400">
                            v{{ $history['version'] }}
                        </div>
                        <div class="flex-grow">
                            <p class="text-gray-700 dark:text-gray-300">
                                {{ $history['reason'] ?? 'No reason provided' }}
                            </p>
                            <p class="text-gray-500 dark:text-gray-400 mt-1">
                                {{ \Carbon\Carbon::parse($history['timestamp'])->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>