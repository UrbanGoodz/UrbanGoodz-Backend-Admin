<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Urban Goodz Driver')</title>
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/vendor.min.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/theme.minc619.css?v=1.0') }}">
</head>
<body>
<main class="content container-fluid py-4">
    <div class="mb-3">
        <a href="{{ route('delivery-man.urban-goodz.index') }}" class="btn btn--secondary btn-sm">Urban Goodz</a>
        <a href="{{ route('delivery-man.urban-goodz.order-anywhere.index') }}" class="btn btn--secondary btn-sm">Order Anywhere</a>
        <a href="{{ route('delivery-man.urban-goodz.payments.index') }}" class="btn btn--secondary btn-sm">Earnings</a>
    </div>
    @yield('content')
</main>
</body>
</html>
