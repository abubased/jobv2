<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- SEO Meta Tags --}}
        <meta name="description" content="{{ $metaDescription ?? 'Rukhsana Packaging - Professional packaging solutions and quality materials for your business needs.' }}">
        <meta name="keywords" content="{{ $metaKeywords ?? 'packaging, rukhsana packaging, packaging materials, business packaging, quality packaging' }}">
        <meta name="author" content="{{ config('app.name') }}">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ url()->current() }}">

        {{-- Open Graph / Facebook / WhatsApp / LinkedIn --}}
        <meta property="og:type" content="{{ $ogType ?? 'website' }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="{{ $metaTitle ?? config('app.name') . ' - Professional Packaging Solutions' }}">
        <meta property="og:description" content="{{ $metaDescription ?? 'Quality packaging materials and solutions for businesses. Get premium packaging services with Rukhsana Packaging.' }}">
        <meta property="og:image" content="{{ $ogImage ?? asset('images/og-image.jpg') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $ogImageAlt ?? config('app.name') . ' Preview' }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:locale" content="{{ app()->getLocale() }}">

        {{-- Twitter Card --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:url" content="{{ url()->current() }}">
        <meta name="twitter:title" content="{{ $metaTitle ?? config('app.name') . ' - Professional Packaging Solutions' }}">
        <meta name="twitter:description" content="{{ $metaDescription ?? 'Quality packaging materials and solutions for businesses.' }}">
        <meta name="twitter:image" content="{{ $ogImage ?? asset('images/og-image.jpg') }}">
        <meta name="twitter:image:alt" content="{{ $ogImageAlt ?? config('app.name') . ' Preview' }}">
        @if(isset($twitterSite))
        <meta name="twitter:site" content="{{ $twitterSite }}">
        @endif
        @if(isset($twitterCreator))
        <meta name="twitter:creator" content="{{ $twitterCreator }}">
        @endif

        {{-- Additional Social Media Meta Tags --}}
        <meta property="fb:app_id" content="{{ config('services.facebook.app_id', '') }}">
        <meta name="theme-color" content="#ffffff">
        <meta name="msapplication-TileColor" content="#ffffff">

        
        {{-- TikTok Pixel Code Start --}}
        <script>
            !function (w, d, t) {
            w.TiktokAnalyticsObject = t;
            var ttq = w[t] = w[t] || [];
            ttq.methods = ["page", "track", "identify", "instances", "debug",
                "on", "off", "once", "ready", "alias", "group",
                "enableCookie", "disableCookie", "holdConsent",
                "revokeConsent", "grantConsent"];

            ttq.setAndDefer = function (t, e) {
                t[e] = function () {
                t.push([e].concat(Array.prototype.slice.call(arguments, 0)));
                };
            };

            for (var i = 0; i < ttq.methods.length; i++) {
                ttq.setAndDefer(ttq, ttq.methods[i]);
            }

            ttq.instance = function (t) {
                for (var e = ttq._i[t] || [], n = 0;
                n < ttq.methods.length; n++) {
                ttq.setAndDefer(e, ttq.methods[n]);
                }
                return e;
            };

            ttq.load = function (e, n) {
                var r = "https://analytics.tiktok.com/i18n/pixel/events.js";
                ttq._i = ttq._i || {};
                ttq._i[e] = [];
                ttq._i[e]._u = r;
                ttq._t = ttq._t || {};
                ttq._t[e] = +new Date;
                ttq._o = ttq._o || {};
                ttq._o[e] = n || {};

                var s = d.createElement("script");
                s.type = "text/javascript";
                s.async = true;
                s.src = r + "?sdkid=" + e + "&lib=" + t;

                var a = d.getElementsByTagName("script")[0];
                a.parentNode.insertBefore(s, a);
            };

            ttq.load('DB54ATBC77UEFVDNOM70');
            ttq.page();
            }(window, document, 'ttq');
        </script>
        {{-- TikTok Pixel Code End --}}

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
        <script>
            (function(){
                function fixIframeParentPadding(el){
                try{
                    var p = el && el.parentElement;
                    if(!p) return;
                    var computed = window.getComputedStyle(p).padding;
                    if(computed === '50px' || p.style.padding === '50px') {
                    p.style.padding = '20px';
                    }
                }catch(e){/* ignore */}
                }
                function fixExisting(){
                document.querySelectorAll('iframe').forEach(fixIframeParentPadding);
                }
                if (document.readyState === 'complete' || document.readyState === 'interactive') {
                fixExisting();
                } else {
                document.addEventListener('DOMContentLoaded', fixExisting);
                }
                var observer = new MutationObserver(function(mutations){
                mutations.forEach(function(m){
                    m.addedNodes.forEach(function(node){
                    if(node && node.nodeType === 1){
                        if(node.tagName === 'IFRAME') fixIframeParentPadding(node);
                        if(node.querySelectorAll) node.querySelectorAll('iframe').forEach(fixIframeParentPadding);
                    }
                    });
                });
                });
                try{ observer.observe(document.documentElement || document.body, { childList: true, subtree: true }); }catch(e){}
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
              /* Force any inline padding:50px to 20px (covers padding:50px and padding: 50px variants) */
            [style*="padding:50px"] , [style*="padding: 50px"] , [style*="padding:50px;"] , [style*="padding: 50px;"] {
                padding: 20px !important;
            }
        </style>

        <title inertia>{{ $metaTitle ?? config('app.name', 'আবেদন ফরম') }}</title>

        {{-- Favicons --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Fonts --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        {{-- Vite Assets --}}
        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
