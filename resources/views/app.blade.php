<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if (config('services.gtm.enabled') && config('services.gtm.id'))
            {{-- Google Tag Manager --}}
            <script>
                (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','{{ config('services.gtm.id') }}');
            </script>
            {{-- End Google Tag Manager --}}
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/images/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/images/favicon-32.jpg" sizes="32x32">
        <link rel="apple-touch-icon" href="/images/apple-touch-icon.jpg">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        @if (config('services.gtm.enabled') && config('services.gtm.id'))
            {{-- Google Tag Manager (noscript) --}}
            <noscript>
                <iframe
                    src="https://www.googletagmanager.com/ns.html?id={{ config('services.gtm.id') }}"
                    height="0"
                    width="0"
                    style="display:none;visibility:hidden"
                ></iframe>
            </noscript>
            {{-- End Google Tag Manager (noscript) --}}
        @endif

        <x-inertia::app />
    </body>
</html>
