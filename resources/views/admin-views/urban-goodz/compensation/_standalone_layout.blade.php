<!doctype html>
{{--
    Minimal standalone layout for the compensation surface.

    Production renders these pages inside layouts.admin.app. That layout pulls in
    the full 6amMart admin chrome, including a sidebar partial that resolves from
    runtime module/session state and is not available in an isolated test request.

    Setting config('urban_goodz_compensation.layout') to this view lets the
    compensation pages be rendered and asserted on their own. It is never the
    default — production keeps the real admin layout.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Driver Pricing & Compensation')</title>
    @stack('css_or_js')
</head>
<body>
    @yield('content')
    @stack('script')
</body>
</html>
