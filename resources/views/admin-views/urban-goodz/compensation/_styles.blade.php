<style>
    .ug-comp { --ug-ink:#14161a; --ug-muted:#6b7280; --ug-line:#e5e7eb; --ug-bg:#fff;
        --ug-accent:#0f766e; --ug-warn:#b45309; --ug-danger:#b91c1c; --ug-ok:#15803d;
        color:var(--ug-ink); }
    @media (prefers-color-scheme: dark) {
        .ug-comp { --ug-ink:#e8eaed; --ug-muted:#9aa0a6; --ug-line:#2c2f34; --ug-bg:#16181c;
            --ug-accent:#2dd4bf; --ug-warn:#fbbf24; --ug-danger:#f87171; --ug-ok:#4ade80; }
    }
    .ug-comp-header { display:flex; justify-content:space-between; align-items:flex-start;
        gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
    .ug-comp-title h1 { font-size:1.5rem; font-weight:650; margin:0 0 .25rem; letter-spacing:-.01em; }
    .ug-comp-title p { color:var(--ug-muted); margin:0; max-width:64ch; font-size:.9rem; }
    .ug-comp-tabs { display:flex; gap:.25rem; flex-wrap:wrap; border-bottom:1px solid var(--ug-line);
        margin-bottom:1.25rem; overflow-x:auto; }
    .ug-tab { padding:.55rem .85rem; font-size:.875rem; color:var(--ug-muted); text-decoration:none;
        border-bottom:2px solid transparent; white-space:nowrap; }
    .ug-tab:hover { color:var(--ug-ink); }
    .ug-tab.is-active { color:var(--ug-accent); border-bottom-color:var(--ug-accent); font-weight:600; }
    .ug-card { background:var(--ug-bg); border:1px solid var(--ug-line); border-radius:12px;
        padding:1.1rem; margin-bottom:1rem; }
    .ug-card h2 { font-size:1rem; font-weight:650; margin:0 0 .75rem; }
    .ug-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:.85rem; }
    .ug-field label { display:block; font-size:.78rem; font-weight:600; color:var(--ug-muted);
        margin-bottom:.28rem; text-transform:uppercase; letter-spacing:.03em; }
    .ug-field input, .ug-field select, .ug-field textarea { width:100%; padding:.5rem .6rem;
        border:1px solid var(--ug-line); border-radius:8px; background:var(--ug-bg);
        color:var(--ug-ink); font-size:.9rem; }
    .ug-table-wrap { overflow-x:auto; }
    table.ug-table { width:100%; border-collapse:collapse; font-size:.875rem; }
    table.ug-table th { text-align:left; font-size:.72rem; text-transform:uppercase;
        letter-spacing:.04em; color:var(--ug-muted); padding:.5rem .6rem;
        border-bottom:1px solid var(--ug-line); white-space:nowrap; }
    table.ug-table td { padding:.55rem .6rem; border-bottom:1px solid var(--ug-line); vertical-align:top; }
    .ug-num { font-variant-numeric:tabular-nums; text-align:right; }
    .ug-badge { display:inline-block; padding:.12rem .5rem; border-radius:999px; font-size:.72rem;
        font-weight:600; border:1px solid var(--ug-line); }
    .ug-badge-published { color:var(--ug-ok); border-color:currentColor; }
    .ug-badge-draft { color:var(--ug-warn); border-color:currentColor; }
    .ug-badge-archived { color:var(--ug-muted); }
    .ug-badge-deficit { color:var(--ug-danger); border-color:currentColor; }
    .ug-btn { display:inline-block; padding:.5rem .9rem; border-radius:8px; font-size:.875rem;
        font-weight:600; text-decoration:none; border:1px solid var(--ug-line);
        background:transparent; color:var(--ug-ink); cursor:pointer; }
    .ug-btn-primary { background:var(--ug-accent); border-color:var(--ug-accent); color:#fff; }
    .ug-btn-danger { color:var(--ug-danger); border-color:currentColor; }
    .ug-alert { padding:.7rem .9rem; border-radius:8px; margin-bottom:1rem; font-size:.875rem;
        border:1px solid currentColor; }
    .ug-alert-success { color:var(--ug-ok); }
    .ug-alert-warning { color:var(--ug-warn); }
    .ug-alert-error { color:var(--ug-danger); }
    .ug-alert ul { margin:.4rem 0 0; padding-left:1.1rem; }
    .ug-explain { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.8rem;
        white-space:pre; overflow-x:auto; background:rgba(127,127,127,.08);
        padding:.85rem; border-radius:8px; }
    .ug-muted { color:var(--ug-muted); }
    .ug-component-group { border:1px solid var(--ug-line); border-radius:10px; padding:.85rem;
        margin-bottom:.75rem; }
    .ug-component-group > summary { cursor:pointer; font-weight:600; font-size:.9rem; }
    .ug-component { border-top:1px solid var(--ug-line); padding-top:.7rem; margin-top:.7rem; }
    .ug-component-name { font-weight:600; font-size:.85rem; margin-bottom:.45rem; }
</style>
