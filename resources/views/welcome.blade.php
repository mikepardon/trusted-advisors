<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="overflow: hidden; height: 100dvh">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>{{ config('app.name', 'Trusted Advisors') }}</title>
    @vite(['resources/js/app.js'])
</head>
<body style="overflow: hidden; height: 100dvh">
    <div id="app"></div>
</body>
</html>
