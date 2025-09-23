<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0ea5e9">

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
<body class="h-full font-sans antialiased bg-slate-50 dark:bg-slate-900 selection:bg-sky-100 dark:selection:bg-sky-900">
    <!-- Ambient glow -->
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10">
        <!-- Primary glow -->
        <div class="absolute left-1/2 top-[-18rem] h-[36rem] w-[56rem] -translate-x-1/2 rounded-full blur-3xl
                    bg-gradient-to-br from-sky-200 via-blue-100 to-indigo-100 opacity-70 dark:from-sky-900 dark:via-blue-900 dark:to-indigo-900"></div>
        <!-- Secondary glow -->
        <div class="absolute right-1/2 bottom-[-12rem] h-[24rem] w-[36rem] translate-x-1/2 rounded-full blur-3xl
                    bg-gradient-to-br from-blue-100 via-indigo-100 to-sky-200 opacity-40 dark:from-blue-900 dark:via-indigo-900 dark:to-sky-900"></div>
    </div>

    <!-- Back to home -->
    <div class="fixed top-4 left-4 z-50">
        <a href="{{ route('welcome') }}" class="group inline-flex items-center gap-2 rounded-full bg-white/50 px-3 py-2 text-sm text-slate-600 shadow-sm backdrop-blur transition hover:bg-white/75 hover:shadow dark:bg-slate-800/50 dark:text-slate-300 dark:hover:bg-slate-800/75">
            <i data-lucide="chevron-left" class="h-4 w-4 transition-transform group-hover:-translate-x-0.5"></i>
            {{ __('Back to home') }}
        </a>
    </div>

    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            @yield('header')
            @hasSection('subheader')
                @yield('subheader')
            @endif
        </div>

        <!-- Main Content -->
        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="relative overflow-hidden rounded-2xl bg-white/95 p-8 shadow-xl ring-1 ring-slate-200/50 backdrop-blur transition dark:bg-slate-800/95 dark:ring-slate-700/50">
                <!-- Decorative corner -->
                <div aria-hidden="true" class="pointer-events-none absolute right-0 top-0 h-32 w-32 -translate-y-8 translate-x-8">
                    <div class="absolute inset-0 -rotate-45 scale-x-150 bg-gradient-to-t from-slate-50/75 via-slate-100 to-slate-50 dark:from-slate-800/75 dark:via-slate-700 dark:to-slate-800"></div>
                </div>
                <!-- Decorative dots -->
                <div aria-hidden="true" class="pointer-events-none absolute left-4 bottom-4 h-24 w-24">
                    <div class="absolute h-1.5 w-1.5 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                    <div class="absolute left-6 top-6 h-1.5 w-1.5 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                    <div class="absolute left-12 h-1.5 w-1.5 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                </div>

                @if (session('error'))
                    <div class="mb-6 rounded-xl bg-red-50 p-4 dark:bg-red-900/50">
                        <div class="flex items-start gap-3">
                            <i data-lucide="alert-circle" class="mt-0.5 h-5 w-5 flex-none text-red-400"></i>
                            <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                {{ session('error') }}
                            </p>
                        </div>
                    </div>
                @endif

                @if (session('success'))
                    <div class="mb-6 rounded-xl bg-green-50 p-4 dark:bg-green-900/50">
                        <div class="flex items-start gap-3">
                            <i data-lucide="check-circle" class="mt-0.5 h-5 w-5 flex-none text-green-400"></i>
                            <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                {{ session('success') }}
                            </p>
                        </div>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex items-center justify-between px-2">
                <!-- Help text -->
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    @yield('footer')
                </div>

                <!-- Controls -->
                <div class="flex items-center gap-3">
                    <!-- Language Selector -->
                    <div class="relative">
                        <select onchange="window.location.href = '?lang=' + this.value" class="h-9 appearance-none rounded-xl bg-white/95 pl-3 pr-8 text-sm text-slate-700 shadow-sm ring-1 ring-slate-200/50 backdrop-blur transition hover:bg-white hover:shadow focus:outline-none focus:ring-2 focus:ring-sky-500 dark:bg-slate-800/95 dark:text-slate-300 dark:ring-slate-700/50 dark:hover:bg-slate-800 dark:hover:shadow-lg">
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
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                            <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition group-hover:text-slate-500 dark:text-slate-500 dark:group-hover:text-slate-400"></i>
                        </div>
                    </div>

                    <!-- Theme Toggle -->
                    <button type="button" onclick="toggleTheme()" class="group inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/95 text-slate-700 shadow-sm ring-1 ring-slate-200/50 backdrop-blur transition hover:bg-white hover:shadow focus:outline-none focus:ring-2 focus:ring-sky-500 dark:bg-slate-800/95 dark:text-slate-300 dark:ring-slate-700/50 dark:hover:bg-slate-800 dark:hover:shadow-lg">
                        <i data-lucide="sun" class="h-4 w-4 transition group-hover:rotate-12 group-hover:scale-110 dark:hidden"></i>
                        <i data-lucide="moon" class="hidden h-4 w-4 transition group-hover:rotate-12 group-hover:scale-110 dark:block"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Toggle Script -->
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = localStorage.theme === 'dark';
            
            // Prepare for transition
            html.classList.add('transform', 'duration-300', 'ease-in-out');
            
            if (isDark) {
                localStorage.theme = 'light';
                html.classList.remove('dark');
            } else {
                localStorage.theme = 'dark';
                html.classList.add('dark');
            }
            
            // Clean up transition classes
            setTimeout(() => {
                html.classList.remove('transform', 'duration-300', 'ease-in-out');
            }, 300);
        }
    </script>
</body>
</html>
