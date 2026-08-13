> [!WARNING]
> **PRELIMINARY ARCHITECTURE BLUEPRINT**
> 
> This document describes proposed navigation, design, migration, and consolidation architecture.
> 
> It does not certify:
> - route functionality
> - controller functionality
> - database correctness
> - permissions
> - payments
> - payouts
> - AI functionality
> - browser behavior
> - mobile behavior
> - staging readiness
> - production readiness

# URBAN GOODZ ADMIN INFORMATION ARCHITECTURE

## 1. Command Center
- **PURPOSE**: Primary executive dashboard with separate data domains (Platform Health, Marketplace, Logistics, Business Ops, Commerce, AI).
- **PRIMARY USERS**: Super Admins, Executives, Dispatch Managers
- **SUBMENUS**: Executive Summary, Platform Health, Live Operations
- **PAGES**: `/admin/command-center`
- **KEY METRICS**: Active users, GMV, Dispatch Loads, API Status, Open Alerts
- **PRIMARY ACTIONS**: System override, Emergency pause, Clear alerts
- **LEGACY COMPONENTS ABSORBED**: 6amMart Dashboard
- **NEW COMPONENTS**: Real-time cross-module metrics
- **PERMISSION**: Existing authenticated Admin guard + explicit roles + granular permission slugs + module permissions + approval boundaries + audit logs

## 2. Marketplace
- **PURPOSE**: Management of digital/physical stores, vendors, products
- **PRIMARY USERS**: Store Admins, Vendor Managers
- **SUBMENUS**: Stores, Vendors, Items, Campaigns
- **PAGES**: `/admin/marketplace/*`
- **KEY METRICS**: Sellable products, Active vendors, Marketplace GMV
- **LEGACY COMPONENTS ABSORBED**: `admin.vendor.*`, `admin.item.*`, `admin.campaign.*`

## 3. Orders & Fulfillment
- **PURPOSE**: Universal order tracking regardless of vertical (marketplace, custom, fashion, medical)
- **PRIMARY USERS**: Dispatchers, Customer Support
- **SUBMENUS**: Live Orders, Scheduled Orders, Exceptions, Returns
- **PAGES**: `/admin/orders/*`
- **KEY METRICS**: Pending, Processing, On-route, Failed, Delivered
- **LEGACY COMPONENTS ABSORBED**: `admin.order.*`

## 4. Business Operations
- **PURPOSE**: B2B Portal management
- **PRIMARY USERS**: Account Executives
- **SUBMENUS**: Clients, Locations, Invoices, Routing
- **PAGES**: `/admin/business-ops/*`
- **NEW COMPONENTS**: B2B Portal, Business Billing

## 5. Dispatch & Logistics
- **PURPOSE**: Core routing engine for all fleets
- **PRIMARY USERS**: Dispatchers, Fleet Managers
- **SUBMENUS**: Live Map, Open Loads, Assigned Loads, Route Zones
- **NEW COMPONENTS**: Load Sourcing, Dispatcher Portal, Zone setup (legacy assimilated)

## 6. Courier & Medical
- **PURPOSE**: Specialized handling for STAT deliveries
- **SUBMENUS**: STAT Runs, Certifications, Compliance
- **NEW COMPONENTS**: Medical Courier Module

## 7. Vendors & Providers
- **PURPOSE**: Unified account management for sellers and service providers
- **SUBMENUS**: Onboarding, Approvals, Subscriptions

## 8. Drivers
- **PURPOSE**: Delivery agent management
- **SUBMENUS**: Roster, Shifts, Vehicles, Ratings
- **LEGACY COMPONENTS ABSORBED**: `admin.delivery-man.*`

## 9. Customers
- **PURPOSE**: Shopper management
- **SUBMENUS**: Directory, Wallets, Loyalty, Support
- **LEGACY COMPONENTS ABSORBED**: `admin.users.customers.*`

## 10. Payments & Payouts
- **PURPOSE**: Unified ledger for all money movement
- **SUBMENUS**: Pricing Rules, Taxes, Gateways, Driver Payouts, Vendor Withdrawals, Refunds
- **NEW COMPONENTS**: Unified Pricing & Payouts Control Center

## 11. Order Anywhere
- **PURPOSE**: Cross-app custom order management
- **SUBMENUS**: Request Feed, Quotations, Exceptions

## 12. Fashion Fit
- **PURPOSE**: AR sizing and fashion vertical
- **SUBMENUS**: Requests, Tailors, Return Handling

## 13. Services
- **PURPOSE**: Booking management

## 14. Rentals
- **PURPOSE**: Equipment reservation tracking

## 15. Creators, Reels & Events
- **PURPOSE**: Social commerce

## 16. Community Marketplace
- **PURPOSE**: C2C sales

## 17. AI Chief of Staff
- **PURPOSE**: Executive AI overview and action center
- **SUBMENUS**: Daily Briefing, Approvals, Cost
- **NEW COMPONENTS**: Safe Automation, Action Levels
- **PERMISSION**: Existing authenticated Admin guard + explicit roles + granular permission slugs + module permissions + approval boundaries + audit logs

## 18. AI Operations
- **PURPOSE**: Configuration of AI modules
- **SUBMENUS**: Copilot Settings, Genie Settings, Knowledge Base

## 19. Load Sourcing
- **PURPOSE**: Freight management

## 20. Reports & Analytics
- **PURPOSE**: System-wide exports

## 21. System Health
- **PURPOSE**: Technical monitoring

## 22. Settings
- **PURPOSE**: Global configs (Business, System, Localization, Security)
