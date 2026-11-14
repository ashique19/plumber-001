<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @stack('head')
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-gray-900 antialiased">
    {{ $slot }}
</body>
</html>