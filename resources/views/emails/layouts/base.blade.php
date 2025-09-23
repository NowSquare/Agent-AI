<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Agent AI')</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-primary: #1a1a1a;
            --text-secondary: #666666;
            --accent-color: #0891b2;
            --accent-hover: #0e7490;
            --border-color: #e5e7eb;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-primary: #1a1a1a;
                --bg-secondary: #2d2d2d;
                --text-primary: #ffffff;
                --text-secondary: #a3a3a3;
                --border-color: #404040;
            }
        }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--bg-primary);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--bg-secondary);
            padding: 24px;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .header p {
            margin: 8px 0 0;
            color: var(--text-secondary);
        }

        .content {
            background: var(--bg-primary);
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background: var(--accent-color);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            margin: 16px 0;
            text-align: center;
            transition: background-color 0.2s;
        }

        .button:hover {
            background: var(--accent-hover);
        }

        .button-secondary {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary) !important;
        }

        .button-secondary:hover {
            background: var(--bg-secondary);
        }

        .footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-secondary);
        }

        .footer a {
            color: var(--accent-color);
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .mt-4 { margin-top: 16px; }
        .mb-4 { margin-bottom: 16px; }
        .flex { display: flex; }
        .flex-col { flex-direction: column; }
        .gap-4 { gap: 16px; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>@yield('header', 'Agent AI')</h1>
            @hasSection('subheader')
                <p>@yield('subheader')</p>
            @endif
        </div>

        <div class="content">
            @yield('content')
        </div>

        <div class="footer">
            @yield('footer')
            <p class="mt-4">
                {{ __('emails.footer.questions') }} 
                <a href="mailto:{{ config('mail.support_email', 'support@example.com') }}">
                    {{ config('mail.support_email', 'support@example.com') }}
                </a>
            </p>
            <p>
                {{ __('emails.footer.company') }} &copy; {{ date('Y') }} {{ config('app.name') }}
            </p>
        </div>
    </div>
</body>
</html>
