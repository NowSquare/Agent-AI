@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-lg">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Confirm Action</h1>
        <p class="text-gray-600">Please review and confirm this action</p>
    </div>

    <div class="bg-gray-50 p-6 rounded-lg mb-6">
        <h2 class="text-lg font-semibold mb-4">Action Details</h2>

        <div class="space-y-3">
            <div>
                <span class="font-medium text-gray-700">Type:</span>
                <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm">{{ ucfirst(str_replace('_', ' ', $action->type)) }}</span>
            </div>

            @if($action->payload_json)
                <div>
                    <span class="font-medium text-gray-700">Details:</span>
                    <div class="mt-2">
                        @foreach($action->payload_json as $key => $value)
                            <div class="text-sm">
                                <span class="text-gray-600">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                <span class="ml-2">{{ is_array($value) ? json_encode($value) : $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($action->expires_at)
                <div>
                    <span class="font-medium text-gray-700">Expires:</span>
                    <span class="ml-2">{{ $action->expires_at->format('M j, Y g:i A') }}</span>
                </div>
            @endif
        </div>
    </div>

    @if($thread)
        <div class="bg-blue-50 p-4 rounded-lg mb-6">
            <h3 class="font-medium text-blue-900 mb-2">Related Thread</h3>
            <p class="text-blue-800 text-sm">{{ $thread->subject }}</p>
        </div>
    @endif

    <div class="flex space-x-4">
        <form method="POST" action="{{ route('action.confirm', $action->id) }}" class="flex-1">
            @csrf
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition duration-200">
                Confirm Action
            </button>
        </form>

        <a href="{{ route('welcome') }}"
           class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-medium py-3 px-6 rounded-lg text-center transition duration-200">
            Cancel
        </a>
    </div>

    <div class="mt-6 text-center text-sm text-gray-500">
        <p>This link will expire and cannot be used again after confirmation.</p>
    </div>
</div>

<script>
// Auto-submit for testing (remove in production)
document.addEventListener('DOMContentLoaded', function() {
    // Optional: auto-confirm after 3 seconds for testing
    setTimeout(() => {
        console.log('Action confirmation page loaded');
    }, 1000);
});
</script>
@endsection
