{{--
    Urban Goodz branded error layout.

    Design constraints (deliberate):
      * NO database queries  - the DB may be the thing that is broken.
      * NO external fonts/CDN - the network may be the thing that is broken.
      * NO admin CSS bundle  - asset compilation may be the thing that is broken.
      * NO stack trace, exception message, file path, SQL, or env value is ever
        rendered. Only a random per-request reference is shown.

    The underlying exception is still logged in full by Laravel's handler; the
    same reference appears in storage/logs/laravel.log as "ug_ref", so support
    can find the exact entry from what the user reports.

    Expected section vars: $ugCode, $ugHeading, $ugMessage
--}}
@php
    $ugRef       = \App\Support\UgRequestId::get();
    $ugCode      = $ugCode      ?? 500;
    $ugHeading   = $ugHeading   ?? 'This page could not be loaded';
    $ugMessage   = $ugMessage   ?? 'Something went wrong on our side while loading this page. The issue has been recorded and our team can look it up using the reference below.';
    $ugDashboard = \Illuminate\Support\Facades\Route::has('admin.dashboard') ? route('admin.dashboard') : url('/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $ugCode }} &middot; Urban Goodz Admin</title>
    <link rel="icon" href="{{ asset('public/assets/admin/img/ug-favicon.svg') }}" type="image/svg+xml">
    <style>
        :root{
            --ug-orange:#ED9914;
            --ug-canvas:#E2D3BF;
            --ug-dijon:#E5E276;
            --ug-black:#161616;
            --ug-white:#FFFFFF;
        }
        *{box-sizing:border-box;}
        html,body{margin:0;padding:0;height:100%;}
        body{
            background:var(--ug-black);
            color:var(--ug-white);
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            display:flex;align-items:center;justify-content:center;
            padding:24px;line-height:1.6;
        }
        .ug-card{
            width:100%;max-width:560px;background:#1e1e1e;
            border:1px solid rgba(226,211,191,.18);border-radius:14px;
            padding:40px 36px;text-align:center;
        }
        .ug-mark{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:28px;}
        .ug-mark-badge{
            width:38px;height:38px;border-radius:9px;background:var(--ug-orange);
            color:var(--ug-black);font-weight:800;font-size:19px;
            display:flex;align-items:center;justify-content:center;letter-spacing:-1px;
        }
        .ug-mark-text{font-weight:700;font-size:17px;letter-spacing:.3px;color:var(--ug-canvas);}
        .ug-code{
            font-size:60px;font-weight:800;line-height:1;color:var(--ug-orange);
            margin:0 0 14px;letter-spacing:-2px;
        }
        h1{font-size:21px;font-weight:600;margin:0 0 12px;color:var(--ug-white);}
        p.ug-msg{font-size:15px;color:var(--ug-canvas);margin:0 0 28px;}
        .ug-ref-label{
            font-size:11px;text-transform:uppercase;letter-spacing:1.2px;
            color:rgba(226,211,191,.65);margin:0 0 6px;
        }
        .ug-ref{
            font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
            font-size:15px;color:var(--ug-dijon);background:rgba(229,226,118,.08);
            border:1px solid rgba(229,226,118,.25);border-radius:8px;
            padding:11px 14px;display:block;word-break:break-all;margin:0 0 28px;
        }
        .ug-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
        .ug-btn{
            display:inline-block;padding:11px 22px;border-radius:8px;
            font-size:14px;font-weight:600;text-decoration:none;border:1px solid transparent;
        }
        .ug-btn-primary{background:var(--ug-orange);color:var(--ug-black);}
        .ug-btn-primary:hover{background:#d78a10;}
        .ug-btn-ghost{border-color:rgba(226,211,191,.35);color:var(--ug-canvas);}
        .ug-btn-ghost:hover{border-color:var(--ug-canvas);}
        .ug-foot{
            margin-top:28px;padding-top:20px;
            border-top:1px solid rgba(226,211,191,.12);
            font-size:12px;color:rgba(226,211,191,.55);
        }
        @media (max-width:480px){.ug-card{padding:30px 22px;}.ug-code{font-size:48px;}}
    </style>
</head>
<body>
    <main class="ug-card" role="main">
        <div class="ug-mark">
            <span class="ug-mark-badge" aria-hidden="true">UG</span>
            <span class="ug-mark-text">Urban Goodz Admin</span>
        </div>

        <p class="ug-code">{{ $ugCode }}</p>
        <h1>{{ $ugHeading }}</h1>
        <p class="ug-msg">{{ $ugMessage }}</p>

        <p class="ug-ref-label">Reference ID</p>
        <code class="ug-ref">{{ $ugRef }}</code>

        <div class="ug-actions">
            <a class="ug-btn ug-btn-primary" href="{{ $ugDashboard }}">Back to Dashboard</a>
            <a class="ug-btn ug-btn-ghost" href="{{ url()->current() }}">Try Again</a>
        </div>

        <p class="ug-foot">
            Quote the reference above when contacting Urban Goodz platform support.
        </p>
    </main>
</body>
</html>
