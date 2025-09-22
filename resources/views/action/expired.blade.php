@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-lg text-center">
    <div class="mb-6">
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Action Expired</h1>
        <p class="text-gray-600">This action confirmation link has expired.</p>
    </div>

    <div class="text-sm text-gray-500 mb-6">
        <p>Action ID: {{ $action->id }}</p>
        <p>Type: {{ ucfirst(str_replace('_', ' ', $action->type)) }}</p>
    </div>

    <a href="{{ route('welcome') }}"
       class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
        Return Home
    </a>
</div>
@endsection
