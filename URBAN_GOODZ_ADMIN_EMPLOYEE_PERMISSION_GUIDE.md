# Urban Goodz Admin Employee & Permission Guide

## How to Grant Urban Goodz Permissions

1. Go to **Admin Panel → Employee → Employee Role** (sidebar link)
2. Click **Add New** to create a new role, or click **Edit** on an existing role
3. In the permission checklist, scroll to the **Urban Goodz** section
4. Check the permissions you want to assign (see table below)
5. Click **Submit**

## How to Create an Admin Employee

1. Go to **Admin Panel → Employee → Add New**
2. Fill in employee details (name, email, phone, password)
3. Under **Role**, select the custom role that has the Urban Goodz permissions you want
4. Click **Submit**

## Permission Key Reference

All Urban Goodz permissions are defined in `config/urban_goodz_permissions.php`. They appear as checkboxes in `resources/views/admin-views/custom-role/create.blade.php` and `edit.blade.php`.

| Permission Key | Label | Description |
|---|---|---|
| `urban_goodz_platform_core` | Urban Goodz Platform Core | Access to Urban Goodz platform core features |
| `urban_goodz_control_center` | Urban Goodz Control Center | Access to dashboard and overview |
| `urban_goodz_payments` | Urban Goodz Payments | Access to payment center and ledgers |
| `urban_goodz_payments_view` | Payments View | View payment center and ledgers |
| `urban_goodz_payments_manage` | Payments Manage | Manage payments, capture, refunds |
| `urban_goodz_stylist_requests` | Stylist Requests | Fashion stylist request management |
| `urban_goodz_order_anywhere` | Order Anywhere | Order Anywhere admin management |
| `urban_goodz_fashion_fit` | Fashion Fit | Measurement and tailoring admin |
| `urban_goodz_ai_concierge` | AI Concierge | AI query management |
| `urban_goodz_book_anything` | Book Anything | Service booking admin |
| `urban_goodz_creator_commerce` | Creator Commerce | Creator commerce admin |
| `urban_goodz_community` | Community Marketplace | Community marketplace and posts admin |
| `urban_goodz_earn_money` | Earn Money | Referral and affiliate admin |
| `urban_goodz_logistics` | Logistics | Logistics and load board admin |
| `urban_goodz_logistics_view` | Logistics View | View logistics jobs and load board |
| `urban_goodz_logistics_manage` | Logistics Manage | Manage logistics jobs, assign drivers, quote |
| `urban_goodz_load_board_view` | Load Board View | View load board loads |
| `urban_goodz_load_board_manage` | Load Board Manage | Manage load board loads, assign drivers |
| `urban_goodz_medical_courier` | Medical Courier | Medical courier job admin |
| `urban_goodz_medical_courier_view` | Medical Courier View | View medical courier jobs |
| `urban_goodz_medical_courier_manage` | Medical Courier Manage | Manage medical courier jobs, custody logs |
| `urban_goodz_medical_courier_custody_view` | Medical Courier Custody View | View custody logs |
| `urban_goodz_medical_courier_custody_manage` | Medical Courier Custody Manage | Manage custody logs |
| `urban_goodz_events` | Events | Events management admin |
| `urban_goodz_rentals` | Rentals | Rentals admin (car, vehicle, equipment) |
| `urban_goodz_plus` | Urban Goodz+ | Premium subscription admin |
| `urban_goodz_spotlight` | Black-Owned Spotlight | Promotions admin |
| `urban_goodz_discovery` | Discovery / Search | Search analytics admin |
| `urban_goodz_file_library` | File Library | File library admin |
| `urban_goodz_business_types` | Business Types | Business type management |
| `urban_goodz_capabilities` | Capabilities | Capability management |
| `urban_goodz_business_clients_view` | Business Clients View | View business client accounts |
| `urban_goodz_business_clients_manage` | Business Clients Manage | Manage business client accounts, approve/suspend |
| `urban_goodz_business_client_users_view` | Business Client Users View | View business client user accounts |
| `urban_goodz_business_client_users_manage` | Business Client Users Manage | Create, edit, remove business client users |
| `urban_goodz_business_client_locations_view` | Business Client Locations View | View business client locations |
| `urban_goodz_business_client_locations_manage` | Business Client Locations Manage | Create, edit, remove business client locations |
| `urban_goodz_business_client_documents_view` | Business Client Documents View | View business client documents |
| `urban_goodz_business_client_documents_manage` | Business Client Documents Manage | Upload, download, remove business client documents |
| `urban_goodz_reports_view` | Reports View | View Urban Goodz reports |
| `urban_goodz_reports_export` | Reports Export | Export Urban Goodz reports |
| `urban_goodz_dedicated_routes_view` | Dedicated Routes View | View dedicated routes and packages |
| `urban_goodz_dedicated_routes_manage` | Dedicated Routes Manage | Create, edit, assign drivers to dedicated routes |
| `urban_goodz_driver_payouts_view` | Driver Payouts View | View driver payout requests |
| `urban_goodz_driver_payouts_manage` | Driver Payouts Manage | Approve, reject, process driver payouts |
| `urban_goodz_route_optimization` | Route Optimization | Run route optimization and reorder stops |

## Architecture Notes

### Route Names (Sidebar)
Employee and custom role pages are under the `admin.users.*` route group:
- `admin.users.employee.list` — Employee list
- `admin.users.employee.add-new` — Add new employee
- `admin.users.custom-role.create` — Employee Role (custom role) page

### Middleware
- Employee pages require `module:employee` middleware
- Custom role pages require `module:custom_role` middleware
- These are controlled by `\App\CentralLogics\Helpers::module_permission_check()` in the sidebar

### Permission Checkbox Rendering
Custom role views loop through `config/urban_goodz_permissions.php` modules and render checkboxes grouped by `group` (all Urban Goodz permissions have `group => urban_goodz`). The section grouping in the UI is controlled by `config/urban_goodz_admin_sections.php`.
