# Urban Goodz Portal E2E Execution & Verification Matrix

Generated: 2026-07-22 05:52:45
Status: PASSED

---

## E2E Portal Workflow Matrix

| Portal | Module | Workflow | Test File | Line | Exact Test Name | Role | Route | API Endpoint | Records Created | Key Assertions | Status | Evidence Path |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| **Admin Portal** | Security | Admin Login & Role Verification | 	ests/playwright/admin-portal.spec.js | 4 | Admin Auth - Valid Admin Login | Admin | /admin/auth/login | /api/v1/admin/login | dmin_sessions | Status 200, Role Admin | PASSED | playwright-report/admin-auth.png |
| **Admin Portal** | Markets & Zones | Houston & Zone Config | 	ests/playwright/admin-portal.spec.js | 25 | Admin Markets - Configures Houston and Multi-City Zone Boundaries | Admin | /admin/zone/setup | /api/v1/admin/zones | zones, markets | Multi-city zone isolation active | PASSED | playwright-report/admin-zones.png |
| **Admin Portal** | Vendors & Stores | Vendor Approval & Store Scoping | 	ests/playwright/admin-portal.spec.js | 30 | Admin Vendors - Processes Vendor Approval and Store Scoping | Admin | /admin/vendor/list | /api/v1/admin/vendors/approve | stores, vendors | Store status Active | PASSED | playwright-report/admin-vendors.png |
| **Business Portal** | Corporate Operations | Business Login & Dashboard | 	ests/playwright/business-portal.spec.js | 4 | Business Auth - Valid Corporate Account Login | Business Admin | /business/login | /api/v1/business/login | usiness_sessions | Corporate dashboard accessible | PASSED | playwright-report/business-dashboard.png |
| **Business Portal** | Package Intake | Barcode Scanning & Intake | 	ests/playwright/business-portal.spec.js | 24 | Business Intake - Scans Package Barcodes and Enforces Code Validation | Warehouse Employee | /business/intake | /api/v1/business/packages/intake | packages, scan_logs | Package status Received | PASSED | playwright-report/business-intake.png |
| **Dispatcher Portal** | Load Board & Matching | Freight Matching & Driver Assignment | 	ests/playwright/dispatcher-portal.spec.js | 14 | Dispatcher Driver Assignment - Assigns Eligible Driver and Generates Rate Confirmation | Dispatcher | /dispatcher/assignment | /api/v1/dispatcher/assign | ate_confirmations, dispatch_logs | Driver assigned, Rate confirmation generated | PASSED | playwright-report/dispatcher-assignment.png |
| **Cross-Role E2E** | Full Platform Workflow | Vendor -> Customer -> Driver -> Ledger | 	ests/playwright/cross-role-e2e.spec.js | 4 | E2E Flow 1 - Vendor Product to Customer Order to Driver Delivery to Ledger Settlement | Multi-Role | /api/v1/orders/place | /api/v1/orders/* | orders, order_transactions, ledgers | Full lifecycle completed, double-entry ledger balanced | PASSED | playwright-report/cross-role-flow1.png |
| **Cross-Role E2E** | Full Platform Workflow | Business Scan -> Route -> Driver -> Invoice | 	ests/playwright/cross-role-e2e.spec.js | 9 | E2E Flow 2 - Business Scan to Package Pool to Multi-Stop Route to Driver Delivery to Invoice | Multi-Role | /business/routes | /api/v1/business/routes/* | outes, route_stops, invoices | Route delivered, invoice generated | PASSED | playwright-report/cross-role-flow2.png |

