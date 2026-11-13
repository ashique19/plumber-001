@props(['seo'])
@php
    $seo = is_array($seo ?? null) ? $seo : [];
@endphp
@if (filled($seo['google_site_verification'] ?? null))
    <meta name="google-site-verification" content="{{ $seo['google_site_verification'] }}">
@endif
@if (filled($seo['bing_site_verification'] ?? null))
    <meta name="msvalidate.01" content="{{ $seo['bing_site_verification'] }}">
@endif
@if (filled($seo['gtm_container_id'] ?? null))
    <script>
        window.dataLayer = window.dataLayer || [];
    </script>
    <script async src="https://www.googletagmanager.com/gtm.js?id={{ $seo['gtm_container_id'] }}"></script>
@endif
@if (filled($seo['ga_measurement_id'] ?? null))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $seo['ga_measurement_id'] }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($seo['ga_measurement_id']));
    </script>
@endif
@if (filled($seo['meta_pixel_id'] ?? null))
    <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @json($seo['meta_pixel_id']));
        fbq('track', 'PageView');
    </script>
    <noscript>
        <img height="1" width="1" style="display:none" alt=""
             src="https://www.facebook.com/tr?id={{ urlencode($seo['meta_pixel_id']) }}&ev=PageView&noscript=1">
    </noscript>
@endif
