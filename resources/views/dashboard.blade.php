@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <h2 class="text-2xl font-bold mb-4">Dashboard</h2>

                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-2">Welcome to Agent AI</h3>
                    <p class="text-gray-600">
                        Your email-centered automation system is ready. You can now:
                    </p>
                    <ul class="list-disc list-inside mt-2 text-gray-600">
                        <li>Send emails to trigger automated actions</li>
                        <li>Receive email responses and notifications</li>
                        <li>Manage your account settings</li>
                        <li>View your email threads and actions</li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-blue-800">Email Integration</h4>
                        <p class="text-blue-600 text-sm mt-1">
                            Connected via Postmark webhooks
                        </p>
                    </div>

                    <div class="bg-green-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-green-800">Thread Management</h4>
                        <p class="text-green-600 text-sm mt-1">
                            RFC 5322 threading support
                        </p>
                    </div>

                    <div class="bg-purple-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-purple-800">AI Processing</h4>
                        <p class="text-purple-600 text-sm mt-1">
                            Ready for LLM integration
                        </p>
                    </div>
                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
                    <div class="space-y-2">
                        <a href="{{ route('dashboard') }}" class="block w-full text-left px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded">
                            View Threads (Coming Soon)
                        </a>
                        <a href="{{ route('dashboard') }}" class="block w-full text-left px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded">
                            Account Settings (Coming Soon)
                        </a>
                        <a href="{{ route('dashboard') }}" class="block w-full text-left px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded">
                            API Tokens (Coming Soon)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
