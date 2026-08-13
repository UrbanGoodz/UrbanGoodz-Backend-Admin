#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(process.argv[2] || process.cwd());
const file = path.join(root, 'docs', 'sprints', 'URBAN_GOODZ_FULL_PLATFORM_RECOVERY_SPRINT.csv');

function parseCsv(text) {
  const rows = [];
  let row = [], field = '', quoted = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (quoted) {
      if (c === '"' && text[i + 1] === '"') { field += '"'; i++; }
      else if (c === '"') quoted = false;
      else field += c;
    } else if (c === '"') quoted = true;
    else if (c === ',') { row.push(field); field = ''; }
    else if (c === '\n') { row.push(field.replace(/\r$/, '')); rows.push(row); row = []; field = ''; }
    else field += c;
  }
  if (field || row.length) { row.push(field); rows.push(row); }
  const header = rows.shift();
  return rows.filter((r) => r.some(Boolean)).map((r) => Object.fromEntries(header.map((h, i) => [h, r[i] || ''])));
}

const quote = (value) => /[",\r\n]/.test(String(value ?? ''))
  ? `"${String(value ?? '').replace(/"/g, '""')}"`
  : String(value ?? '');

const lanes = {
  'Security': 'LANE A — Admin, authentication, permissions, security',
  'Authorization': 'LANE A — Admin, authentication, permissions, security',
  'AI Chief of Staff': 'LANE G — AI, dynamic pricing, load sourcing',
  'Financial Control': 'LANE B — Database, orders, payments, ledgers',
  'Database': 'LANE B — Database, orders, payments, ledgers',
  'Testing': 'LANE K — E2E tests, Appium, Playwright, evidence',
  'Pricing': 'LANE G — AI, dynamic pricing, load sourcing',
  'Payouts': 'LANE B — Database, orders, payments, ledgers',
  'Dispatcher': 'LANE C — Public site, registration, Business and Dispatcher portals',
  'Payments': 'LANE B — Database, orders, payments, ledgers',
  'Permissions': 'LANE A — Admin, authentication, permissions, security',
  'Business Portal': 'LANE C — Public site, registration, Business and Dispatcher portals',
  'Mobile': 'LANE E — Vendor and Driver applications',
  'Mobile Security': 'LANE D — Shopper application',
  'Mobile Identity': 'LANE D — Shopper application',
  'Mobile Compatibility': 'LANE D — Shopper application',
  'Load Sourcing': 'LANE G — AI, dynamic pricing, load sourcing',
  'Notifications': 'LANE J — Notifications, Firebase, queues, cron, webhooks',
  'Observability': 'LANE J — Notifications, Firebase, queues, cron, webhooks',
  'Analytics': 'LANE L — Deployment, environments, release artifacts',
  'Realtime': 'LANE J — Notifications, Firebase, queues, cron, webhooks',
  'Storage': 'LANE J — Notifications, Firebase, queues, cron, webhooks',
  'Maps': 'LANE F — Courier, scanning, medical, routes',
  'Creator': 'LANE I — Creators, reels, video, messaging, events',
  'Rentals': 'LANE H — Fashion Fit, services, rentals',
  'Services': 'LANE H — Fashion Fit, services, rentals',
  'Documentation': 'LANE L — Deployment, environments, release artifacts',
  'Quality': 'LANE K — E2E tests, Appium, Playwright, evidence',
  'Release': 'LANE L — Deployment, environments, release artifacts',
  'Dashboard': 'LANE A — Admin, authentication, permissions, security',
  'Data Integrity': 'LANE B — Database, orders, payments, ledgers',
  'Data Governance': 'LANE B — Database, orders, payments, ledgers',
  'Finance': 'LANE B — Database, orders, payments, ledgers',
  'Branding': 'LANE L — Deployment, environments, release artifacts',
  'Deployment': 'LANE L — Deployment, environments, release artifacts',
  'Vendor Dependencies': 'LANE L — Deployment, environments, release artifacts',
  'Legacy Cleanup': 'LANE L — Deployment, environments, release artifacts',
};

const filesById = {
  'P0-01': 'app/Services/AdyenPaymentGateway.php; tests/Feature/UrbanGoodzPaymentAuditTest.php',
  'P0-02': 'app/Http/Controllers/LoginController.php; tests/Feature/AdminLoginRecoveryRegressionTest.php',
  'P0-03': 'routes/admin.php; app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzDedicatedRouteController.php; permission tests',
  'P0-04': 'resources/views/admin-views/custom-role/create.blade.php; resources/views/admin-views/custom-role/edit.blade.php; role tests',
  'P0-05': 'app/Services/UrbanGoodz/UrbanGoodzDriverPricingService.php; app/Models/UrbanGoodzDriverPricingPolicy.php; pricing tests',
  'P0-06': 'app/Services/UrbanGoodz/DynamicPricingService.php; app/Services/UrbanGoodz/UrbanGoodzDriverPricingService.php; pricing tests',
  'P0-07': 'app/Services/UrbanGoodz/UrbanGoodzDriverPricingService.php; app/Models/DeliveryManWallet.php; earning tests',
  'P0-08': 'app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzDedicatedRouteController.php; payout provider/service; ledger tests',
  'P0-09': 'routes/admin_ai_operations.php; resources/views/layouts/admin/partials/_sidebar.blade.php; permission templates; route tests',
  'P0-10': 'app/Http/Controllers/Admin/AiOperationsController.php; app/Services/UrbanGoodz/AiChiefOfStaffService.php; feature tests',
  'P0-11': 'app/Services/UrbanGoodz/AiChiefOfStaffService.php; tests/Feature/UrbanGoodzAiWorkforceTest.php',
  'P0-12': 'app/Services/UrbanGoodz/AiChiefOfStaffService.php; app/Services/UrbanGoodz/AIActionValidator.php; approval tests',
  'P0-13': 'app/Services/UrbanGoodz/AiChiefOfStaffService.php; AI audit models/migrations; audit tests',
  'P0-14': 'database/schema or reviewed baseline migrations; migration CI',
  'P0-15': 'tests/Feature/UrbanGoodzAiCopilotTest.php; tests/Feature/UrbanGoodzAiMigrationTest.php',
  'P0-16': 'phpunit configuration; database schema baseline; docs/qa/evidence',
  'P0-17': 'tests/Browser/AdminLoginTest.spec.js; Playwright config; restricted artifacts',
  'P0-18': 'tests/playwright/*.spec.js; tests/Browser/*.spec.js',
  'P0-19': 'routes/admin.php; routes/business.php; Admin and Business pricing views/controllers; inventory matrix',
  'P0-20': 'payout/wallet/ledger controllers services models routes; wiring matrix',
  'P0-21': 'new reviewed pricing/payout control-center specification; no implementation before approval',
  'P0-22': 'routes/business.php; DispatcherPortalController.php; dispatcher commission views/models',
  'P0-23': 'routes/admin.php; business client/dispatcher controllers; commission models/views; authorization tests',
  'P0-24': 'UrbanGoodzPaymentService.php; checkout/quote/invoice controllers; payment ledger/split tests',
  'P0-25': 'DashboardController.php; dashboard.blade.php; dashboard metric tests',
  'P0-26': 'read-only reconciliation command/report; Item Store Vendor Order OrderTransaction relationship tests',
  'P0-27': 'provenance migration/model scopes; import/seed tooling; KPI tests',
  'P0-28': 'DashboardController.php; payment/order transaction/ledger reconciliation service and tests',
  'P0-29': 'approved dashboard specification; dashboard Blade/controller/view-model after metric approval',
  'P0-30': 'database/seeders/DatabaseSeeder.php; synthetic seeders; environment-guard tests',
  'P0-31': 'Shopper lib/features/auth/domain/reposotories/auth_repository.dart; app_constants.dart; upgrade tests',
  'P0-32': 'Shopper ios/Runner.xcodeproj/project.pbxproj; owned Firebase configuration delivered through approved secret workflow',
  'P0-33': '.env.example; Shopper lib/helper/pusher_helper.dart; realtime configuration tests',
  'P0-34': 'app/Mail/SubscriptionCancel.php; Modules/Rental/Emails/ProviderSubscriptionCancel.php; database/partial/*.sql; mail/import tests',
  'P0-35': 'deployment evidence/runbook only; no production mutation; relevant branding/cache/document-root identity artifacts',
  'P1-15': 'app/CentralLogics/Helpers.php; 6amTech-linked setup views; dependency decision record',
  'P1-16': 'Shopper lib/util/app_constants.dart; pubspec.yaml only if a separately approved namespace migration is justified',
  'P1-17': 'script.sh; package-lock.json metadata; legacy workspace/text-clipping artifacts',
};

const tablesById = {
  'P0-05': 'urban_goodz_driver_pricing_policies; urban_goodz_driver_earnings; delivery_man_wallets',
  'P0-07': 'urban_goodz_driver_earnings; delivery_man_wallets',
  'P0-08': 'urban_goodz_driver_payout_requests; urban_goodz_driver_earnings; urban_goodz_payment_ledgers',
  'P0-10': 'business_needs; human_action_items; ai_tasks; ai_approvals',
  'P0-11': 'vendors; items; orders; business_needs; human_action_items',
  'P0-13': 'ai_audit_events; ai_action_logs; ai_approvals; business_needs; human_action_items',
  'P0-14': 'orders and all committed migration targets',
  'P0-22': 'urban_goodz_dispatch_commissions; urban_goodz_business_clients; urban_goodz_load_board_loads',
  'P0-23': 'urban_goodz_dispatch_commissions; urban_goodz_business_clients; urban_goodz_payment_ledgers',
  'P0-24': 'orders; order_transactions; order_anywhere_requests; urban_goodz_payment_ledgers; urban_goodz_payment_splits; wallets',
  'P0-25': 'items; stores; vendors; users; delivery_men; orders; order_transactions; Urban Goodz domain tables',
  'P0-26': 'items; stores; vendors; orders; order_details; order_transactions',
  'P0-27': 'items; stores; orders; imports plus new reviewed provenance fields',
  'P0-28': 'orders; order_transactions; payment transactions; payment ledgers; wallets; payouts',
  'P0-30': 'users; vendors; stores; storage; measurement_requests; urban_goodz_load_board_loads; reference tables',
  'P0-31': 'device local storage only; no production database mutation',
  'P0-32': 'Firebase project resources only; no database migration',
  'P0-34': 'business_settings; data_settings; email_templates',
  'P0-35': 'read-only production identity/settings/cache evidence only',
};

const raw = parseCsv(fs.readFileSync(file, 'utf8'));
const headers = [
  'TASK ID','PRIORITY','SURFACE','PROBLEM','EVIDENCE','ROOT CAUSE','FILES','DATABASE TABLES',
  'DEPENDENCIES','SAFE PARALLEL LANE','ASSIGNED AGENT TYPE','ESTIMATED ENGINEERING HOURS',
  'IMPLEMENTATION','TEST','ACCEPTANCE CRITERIA','ROLLBACK','BLOCKS TESTERS','BLOCKS DEPLOYMENT','STATUS'
];
const rows = raw.map((r) => {
  const days = Number.parseFloat(r.ESTIMATE) || 1;
  const priority = r.PRIORITY;
  return {
    'TASK ID': r.ID,
    'PRIORITY': priority,
    'SURFACE': r.LANE,
    'PROBLEM': r.TASK,
    'EVIDENCE': r['REQUIRED EVIDENCE'],
    'ROOT CAUSE': `Current source/evidence does not satisfy the stated ${r.LANE} control boundary; see inventory and wiring trace.`,
    'FILES': filesById[r.ID] || 'Resolve exact scoped files from URBAN_GOODZ_WIRING_TRACE_MATRIX.csv before implementation',
    'DATABASE TABLES': tablesById[r.ID] || 'See URBAN_GOODZ_DATABASE_TABLE_USAGE_MATRIX.csv; no production mutation authorized',
    'DEPENDENCIES': r['DEPENDS ON'],
    'SAFE PARALLEL LANE': lanes[r.LANE] || 'LANE L — Deployment, environments, release artifacts',
    'ASSIGNED AGENT TYPE': /Testing|Quality/.test(r.LANE) ? 'QA automation and evidence reviewer'
      : /Database|Data|Finance|Payment|Payout/.test(r.LANE) ? 'Senior backend/database/security engineer'
      : /Mobile/.test(r.LANE) ? 'Senior Flutter integration engineer'
      : /AI|Load|Pricing/.test(r.LANE) ? 'Senior AI/backend safety engineer'
      : 'Senior full-stack domain engineer',
    'ESTIMATED ENGINEERING HOURS': Math.round(days * 8),
    'IMPLEMENTATION': r.TASK,
    'TEST': r['REQUIRED EVIDENCE'],
    'ACCEPTANCE CRITERIA': r['ACCEPTANCE CRITERIA'],
    'ROLLBACK': 'Revert the task commit and restore the reviewed pre-change state; use domain-specific data reversal only from an approved backup/runbook.',
    'BLOCKS TESTERS': ['P0','P1'].includes(priority) ? 'YES' : 'NO',
    'BLOCKS DEPLOYMENT': priority === 'P0' ? 'YES' : 'NO',
    'STATUS': 'NOT STARTED',
  };
});
fs.writeFileSync(file, [headers.join(','), ...rows.map((r) => headers.map((h) => quote(r[h])).join(','))].join('\n') + '\n');
console.log(`Enriched ${rows.length} recovery tasks at ${file}`);
