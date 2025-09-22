@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-lg text-center">
    <div class="mb-6">
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
            <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Action Already Processed</h1>
        <p class="text-gray-600">This action has already been {{ $action->status }}.</p>
    </div>

    <div class="text-sm text-gray-500 mb-6">
        <p>Action ID: {{ $action->id }}</p>
        <p>Type: {{ ucfirst(str_replace('_', ' ', $action->type)) }}</p>
        <p>Status: {{ ucfirst($action->status) }}</p>
        @if($action->completed_at)
            <p>Completed: {{ $action->completed_at->format('M j, Y g:i A') }}</p>
        @endif
    </div>

    <a href="{{ route('welcome') }}"
       class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
        Return Home
    </a>
</div>
@endsection
