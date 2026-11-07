# Known undefined route-name references (baseline)

`tests/Fixtures/known-missing-route-names.txt` is a ratchet: it may only shrink.
Each entry is a `route('name')` literal that has no matching route. Groups:

- `*ride-share*`, `*.rider.*`, `drivemond`: the DriveMond/ride-share module is not installed in this build.
  These links live in sidebar/header blocks that are not rendered when the module is absent
  (the admin page sweep renders the sidebar on every page without error). Remove them when the module is
  formally dropped, or add the routes if it is adopted.
- `installation/*`, `step0..step4`, `install.db`, `import_sql`, `purchase.code`: the installer wizard,
  unreachable once the app is installed.
- `admin.account-transaction.update`, `admin.banner.addstore`, `admin.banner.campaign`: forms on legacy pages whose
  backing routes were removed; the forms would 500 on submit if their page is opened with data. NEED an owner decision:
  rebuild the route or remove the form.
- `paystack.payment`, `register`: legacy payment/registration links; need an owner decision.
