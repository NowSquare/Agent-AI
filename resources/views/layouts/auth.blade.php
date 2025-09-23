<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }} - @yield('title')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Dark mode -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.classList.remove('dark')
        }
    </script>
</head>
<body class="h-full font-sans antialiased bg-gray-50 dark:bg-gray-900">
    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <!-- Logo -->
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <img class="mx-auto h-12 w-auto" src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}">
            <h2 class="mt-6 text-center text-3xl font-light tracking-tight text-gray-900 dark:text-white">
                @yield('header')
            </h2>
            @hasSection('subheader')
                <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                    @yield('subheader')
                </p>
            @endif
        </div>

        <!-- Main Content -->
        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white dark:bg-gray-800 py-8 px-4 shadow sm:rounded-lg sm:px-10">
                @if (session('error'))
                    <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900/50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                    {{ session('error') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                @if (session('success'))
                    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900/50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i data-lucide="check-circle" class="h-5 w-5 text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                    {{ session('success') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="text-center">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    @yield('footer')
                </div>
                <div class="mt-2 flex justify-center space-x-4">
                    <!-- Language Selector -->
                    <div class="relative inline-block text-left">
                        <select onchange="window.location.href = '?lang=' + this.value" class="block w-full pl-3 pr-10 py-2 text-xs bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                            @php
                                $locales = config('language.supported_locales', [
                                    'en' => 'en_US',
                                    'nl' => 'nl_NL',
                                ]);
                            @endphp
                            @foreach($locales as $code => $locale)
                                @if(in_array($code, ['en', 'nl']))
                                    <option value="{{ $code }}" {{ app()->getLocale() === $code ? 'selected' : '' }}>
                                        {{ strtoupper($code) }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <!-- Theme Toggle -->
                    <button type="button" onclick="toggleTheme()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-700 shadow-sm text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i data-lucide="sun" class="h-4 w-4 dark:hidden"></i>
                        <i data-lucide="moon" class="h-4 w-4 hidden dark:block"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Toggle Script -->
    <script>
        function toggleTheme() {
            if (localStorage.theme === 'dark') {
                localStorage.theme = 'light'
                document.documentElement.classList.remove('dark')
            } else {
                localStorage.theme = 'dark'
                document.documentElement.classList.add('dark')
            }
        }
    </script>

    <!-- Initialize Lucide Icons -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
        });
    </script>
</body>
</html>
