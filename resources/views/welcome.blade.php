<!doctype html>
@php
    $locale = app()->getLocale();                   // e.g. "en", "en_US", "ar"
    $baseLocale = strtolower(strtok($locale, '_')); // "en" from "en_US"
    $rtlLocales = ['ar','he','fa','ur'];
    $dir = in_array($baseLocale, $rtlLocales) ? 'rtl' : 'ltr';

    // Prefer AGENT_MAIL; fallback to MAIL_FROM_ADDRESS
    $agentMail = env('AGENT_MAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com'));
@endphp
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Agent AI') }}</title>
    <meta name="theme-color" content="#0ea5e9">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 antialiased selection:bg-sky-100">

    <!-- Ambient glow -->
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute left-1/2 top-[-18rem] h-[36rem] w-[56rem] -translate-x-1/2 rounded-full blur-3xl
                    bg-gradient-to-br from-sky-200 via-blue-100 to-indigo-100 opacity-70"></div>
    </div>

    <main class="mx-auto max-w-6xl px-6 py-24 sm:py-32">
        <!-- Badge -->
        <div class="mb-6">
            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/70 px-3 py-1 text-xs text-slate-600 backdrop-blur">
                <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-sky-500"></span>
                {{ __('Private. Practical. Predictable.') }}
            </span>
        </div>

        <!-- Hero -->
        <header class="mb-12">
            <h1 class="text-4xl font-semibold tracking-tight sm:text-6xl">
                {{ __('Turn email into outcomes.') }}
            </h1>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-slate-700">
                {{ __("Agent-AI transforms messages and attachments into clear actions—securely, locally, and with taste. Queues stay calm, audits stay simple, and your agents just work.") }}
            </p>
        </header>

        <!-- Primary CTA -->
        <section aria-labelledby="cta-title" class="mb-20">
            <h2 id="cta-title" class="sr-only">{{ __('Contact our agents') }}</h2>
            <div class="flex flex-wrap items-center gap-4">
                <a
                    href="mailto:{{ $agentMail }}"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-6 py-3 text-sm font-medium text-white shadow-sm transition
                           hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                >
                    <i data-lucide="send" class="h-4 w-4"></i>
                    {{ __('Email our agents') }}
                </a>

                <span class="text-sm text-slate-600">
                    {{ __('Prefer your own client? Write to') }}
                    <a href="mailto:{{ $agentMail }}" class="font-medium text-sky-700 underline-offset-2 hover:underline">
                        {{ $agentMail }}
                    </a>
                </span>
            </div>
        </section>

        <!-- Three pillars -->
        <section aria-labelledby="pillars-title" class="grid gap-6 sm:grid-cols-3">
            <h2 id="pillars-title" class="sr-only">{{ __('Why Agent-AI') }}</h2>

            <article class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-full bg-sky-100">
                    <i data-lucide="shield" class="h-5 w-5 text-sky-700"></i>
                </div>
                <h3 class="text-base font-semibold">{{ __('Private by design') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ __('Attachments are scanned with ClamAV; PDFs parsed locally with poppler. Your data stays in your stack.') }}
                </p>
            </article>

            <article class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-full bg-sky-100">
                    <i data-lucide="server" class="h-5 w-5 text-sky-700"></i>
                </div>
                <h3 class="text-base font-semibold">{{ __('Calm, scalable automation') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ __('Redis queues with Horizon give you smooth throughput and clear visibility—without drama.') }}
                </p>
            </article>

            <article class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-full bg-sky-100">
                    <i data-lucide="sparkles" class="h-5 w-5 text-sky-700"></i>
                </div>
                <h3 class="text-base font-semibold">{{ __('Intelligence that serves') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ __('Run workflows with local Ollama or your preferred provider—auditable, reversible, and under your control.') }}
                </p>
            </article>
        </section>

        <!-- Fine print -->
        <footer class="mt-16 flex items-center justify-between text-xs leading-6">
            <p class="text-slate-500">
                {{ __('Emails to our agents may be processed automatically to route and respond to your request. Only essential cookies are used.') }}
            </p>
            <a href="{{ route('auth.challenge.form') }}" class="text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300 flex items-center gap-1.5">
                <i data-lucide="log-in" class="h-3.5 w-3.5"></i>
                {{ __('Already have access?') }}
            </a>
        </footer>
    </main>
</body>
</html>
