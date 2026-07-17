# CROSS-APP MONEY FLOW MATRIX

**Version:** 3.9  
**Last Updated:** 2026-07-16  
**Purpose:** Complete reconciled money chain for every transaction type — from customer charge to final ledger balance.

---

## 1. PLATFORM FEE STRUCTURE

| Fee Type | Percentage | Applied To | Collected By |
|---|---|---|---|
| Platform Commission | Configurable per store (default 15%) | Order subtotal | Urban Goodz |
| Delivery Fee | Dynamic (distance-based + surge) | Per delivery | Split: Driver (base) + UG (service fee) |
| Service Fee | 2-5% of order total | Marketplace orders | Urban Goodz |
| Payment Processing | ~2.9% + $0.30 (varies by gateway) | Total charged amount | Payment gateway (Adyen/Stripe) |
| Payout Fee | $0.25-$1.00 per payout | Payout amount | Payment processor |
| Order Anywhere Markup | 10-20% on item cost | Item cost | Urban Goodz |

---

## 2. MARKETPLACE ORDER — COMPLETE MONEY CHAIN

### 2.1 Transaction: Standard Marketplace Order ($50.00 order)

| Step | Transaction Type | Customer View | Vendor View | Driver View | Admin View | Gateway Record | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Customer Charge | $50.00 + $3.99 delivery = $53.99 charged | — | — | Order total $53.99 | Adyen/Stripe: auth $53.99, `authorized` | `payments.amount = 53.99, status = authorized` | Authorized | ✅ |
| 2 | Payment Capture (on delivery) | Payment captured $53.99 | — | — | Payment captured | Adyen/Stripe: captured $53.99 | `payments.status = captured` | Captured | ✅ |
| 3 | Platform Fee Deduction | — | — | — | Platform commission $7.50 (15% of $50), Delivery service fee $1.00 | — | `platform_fees.amount = 8.50, type = commission` | Recorded | ✅ |
| 4 | Vendor Earning | — | $42.50 net (order $50 - commission $7.50) | — | Vendor payout = $42.50 | — | `vendor_earnings.gross = 50.00, commission = 7.50, net = 42.50, status = pending` | Pending | ✅ |
| 5 | Driver Earning | — | — | $3.99 delivery fee (base $2.99 + tip $1.00) | Driver payout = $3.99 | — | `driver_earnings.delivery_fee = 3.99, tip = 1.00, net = 3.99, status = pending` | Pending | ✅ |
| 6 | Gateway Fee | — | — | — | Processing fee $1.87 (2.9% × $53.99 + $0.30) | Gateway statement: $1.87 | `gateway_fees.amount = 1.87, provider = adyen` | Recorded | ✅ |
| 7 | Urban Goodz Net | — | — | — | UG keeps: $8.50 (fees) - $1.87 (gateway) = $6.63 net | — | `platform_revenue.net = 6.63` | Net Revenue | ✅ |
| 8 | Refund (if applicable) | $53.99 refunded to customer | -$42.50 vendor earning reversed | -$3.99 driver earning reversed | Refund = $53.99 + $1.87 gateway fee absorbed by UG | Adyen/Stripe: refund $53.99 | `refunds.amount = 53.99, vendor_earnings.reversed, driver_earnings.reversed` | Refunded | ✅ |

### 2.2 Ledger Entries for $50.00 Order

| Account | Debit | Credit | Balance Effect |
|---|---|---|---|
| Customer Wallet | $53.99 | — | -$53.99 |
| Payment Gateway Clearing | — | $53.99 | +$53.99 |
| Payment Gateway Clearing | $1.87 | — | -$1.87 |
| Gateway Fees Expense | $1.87 | — | -$1.87 (expense) |
| Platform Revenue | — | $8.50 | +$8.50 |
| Vendor Payable | — | $42.50 | +$42.50 (liability) |
| Driver Payable | — | $3.99 | +$3.99 (liability) |
| **Net Platform Position** | | | **+$6.63** |

---

## 3. PARCEL / COURIER ORDER — MONEY CHAIN

### 3.1 Transaction: Parcel Delivery ($35.00 charge)

| Step | Transaction Type | Customer View | Driver View | Admin View | Gateway Record | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|---|---|
| 1 | Customer Payment | $35.00 charged | — | Order $35.00 | Adyen/Stripe: auth+capture $35.00 | `payments.amount = 35.00, status = captured` | Captured | ✅ |
| 2 | Platform Fee | — | — | Commission $5.25 (15%) | — | `platform_fees.amount = 5.25` | Recorded | ✅ |
| 3 | Driver Earning | — | $29.75 delivery fee | Driver payout = $29.75 | — | `driver_earnings.delivery_fee = 29.75, status = pending` | Pending | ✅ |
| 4 | Gateway Fee | — | — | $1.32 (2.9% × $35 + $0.30) | Gateway: $1.32 | `gateway_fees.amount = 1.32` | Recorded | ✅ |
| 5 | UG Net | — | — | $5.25 - $1.32 = $3.93 | — | `platform_revenue.net = 3.93` | Net Revenue | ✅ |
| 6 | Return (if applicable) | Partial/full refund | Driver return fee $5.00 | -$5.00 from driver earning | Adyen/Stripe: partial refund | `refunds.amount, driver_earnings.adjustment` | Adjusted | ✅ |

---

## 4. ORDER ANYWHERE — MONEY CHAIN

### 4.1 Transaction: Order Anywhere ($75.00 estimated, $72.50 actual)

| Step | Transaction Type | Customer View | Vendor View | Driver View | Admin View | Gateway Record | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Quote Approval | $75.00 estimated total approved | — | — | Quote $75.00 | — | — | Approved | ✅ |
| 2 | Customer Payment | $75.00 + $7.50 markup + $3.99 delivery = $86.49 charged | — | — | Total $86.49 | Adyen/Stripe: auth $86.49 | `payments.amount = 86.49, type = order_anywhere` | Authorized | ✅ |
| 3 | Payment Capture | Captured $86.49 | — | — | Captured | Adyen/Stripe: $86.49 | `payments.status = captured` | Captured | ✅ |
| 4 | Purchase Authorization | — | — | Virtual card loaded $72.50 | Purchase authorized $72.50 | — | `purchase_cards.amount = 72.50, status = authorized` | Authorized | ✅ |
| 5 | Actual Purchase | — | — | Driver spends $72.50 | Purchase completed $72.50 | — | `purchase_cards.spent = 72.50, status = completed` | Completed | ✅ |
| 6 | Reconciliation | — | — | — | Admin reconciles: actual $72.50 vs. estimated $75.00 | — | `reconciliation.variance = -2.50` | Reconciled | ✅ |
| 7 | Markup Revenue | — | — | — | UG markup $7.50 (10% of $75) | — | `platform_revenue.markup = 7.50` | Revenue | ✅ |
| 8 | Delivery Fee Split | — | — | $3.99 delivery fee | Delivery $3.99 | — | `driver_earnings.delivery_fee = 3.99` | Pending | ✅ |
| 9 | Platform Fee | — | — | — | Commission on markup: $1.13 | — | `platform_fees.amount = 1.13` | Recorded | ✅ |
| 10 | Gateway Fee | — | — | — | $2.81 (2.9% × $86.49 + $0.30) | Gateway: $2.81 | `gateway_fees.amount = 2.81` | Recorded | ✅ |
| 11 | Variance Refund | $2.50 refund to customer (estimated > actual) | — | — | Variance refund $2.50 | Adyen/Stripe: refund $2.50 | `refunds.amount = 2.50` | Refunded | ✅ |
| 12 | UG Net | — | — | — | $7.50 markup - $2.50 variance - $2.81 gateway = $2.19 | — | `platform_revenue.net = 2.19` | Net Revenue | ✅ |

---

## 5. LOAD BOARD — MONEY CHAIN

### 5.1 Transaction: Load Board Job ($1,200.00 freight rate)

| Step | Transaction Type | Customer (Shipper) View | Driver View | Dispatcher View | Admin View | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|---|---|
| 1 | Load Posted | $1,200.00 rate advertised | — | — | Load rate $1,200 | `load_board_loads.rate = 1200` | Available | ✅ |
| 2 | Load Accepted | — | $1,200.00 gross rate | Commission 10% = $120.00 | Rate $1,200 | `load_board_loads.accepted_rate = 1200` | Accepted | ✅ |
| 3 | Delivery Completed | — | — | — | Delivery confirmed | `driver_earnings.gross = 1200, platform_fee = 120, net = 1080` | Completed | ✅ |
| 4 | Payment Collected | Shipper pays $1,200 + platform fee $144 (12%) = $1,344 | — | — | Total collected $1,344 | `payments.amount = 1344, type = load_board` | Captured | ✅ |
| 5 | Platform Revenue | — | — | $144.00 platform commission | UG revenue $144 | `platform_revenue.load_board = 144` | Revenue | ✅ |
| 6 | Driver Payout | — | $1,080.00 net (after 10% platform fee) | — | Driver payout $1,080 | `driver_earnings.net = 1080, status = pending` | Pending Payout | ✅ |
| 7 | Gateway Fee | — | — | — | Processing $39.28 (2.9% × $1,344 + $0.30) | `gateway_fees.amount = 39.28` | Recorded | ✅ |
| 8 | UG Net | — | — | — | $144 - $39.28 = $104.72 | `platform_revenue.net = 104.72` | Net Revenue | ✅ |

---

## 6. SERVICE BOOKING — MONEY CHAIN

### 6.1 Transaction: Service Booking ($200.00 service)

| Step | Transaction Type | Customer View | Vendor (Provider) View | Admin View | Gateway Record | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|---|---|
| 1 | Quote Received | $200.00 quoted | — | — | — | — | Quoted | ✅ |
| 2 | Customer Payment | $200.00 + $4.00 service fee (2%) = $204.00 charged | — | Total $204.00 | Adyen/Stripe: $204.00 | `payments.amount = 204, type = service_booking` | Authorized | ✅ |
| 3 | Payment Capture | Captured $204.00 | — | Captured | Adyen/Stripe: $204.00 | `payments.status = captured` | Captured | ✅ |
| 4 | Platform Fee | — | — | $4.00 service fee (2%) | — | `platform_fees.amount = 4.00, type = service_fee` | Recorded | ✅ |
| 5 | Vendor Earning | — | $200.00 gross | Vendor payout $200.00 | — | `vendor_earnings.gross = 200, net = 200, status = pending` | Pending | ✅ |
| 6 | Gateway Fee | — | — | $6.12 (2.9% × $204 + $0.30) | Gateway: $6.12 | `gateway_fees.amount = 6.12` | Recorded | ✅ |
| 7 | UG Net | — | — | $4.00 - $6.12 = -$2.12 (loss on small orders) | — | `platform_revenue.net = -2.12` | Net Revenue | ⚠️ Negative margin |

---

## 7. FASHION FIT — MONEY CHAIN

### 7.1 Transaction: Fashion Fit Request ($150.00 estimate)

| Step | Transaction Type | Customer View | Vendor (Stylist) View | Admin View | Gateway Record | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|---|---|
| 1 | Estimate Received | $150.00 estimate | — | — | — | — | Estimate | ✅ |
| 2 | Staged Payment 1 | $75.00 deposit (50%) charged | — | $75.00 collected | Adyen/Stripe: $75.00 | `payments.amount = 75, type = fashion_fit_staged` | Partial | ✅ |
| 3 | Work Completed | — | — | Service delivered | — | — | Completed | ✅ |
| 4 | Staged Payment 2 | $75.00 final payment charged | — | $75.00 collected | Adyen/Stripe: $75.00 | `payments.amount = 75, type = fashion_fit_staged` | Captured | ✅ |
| 5 | Total Collected | $150.00 total | — | $150.00 total | Adyen/Stripe: $150.00 total | `payments.total = 150` | Complete | ✅ |
| 6 | Platform Fee | — | — | $15.00 commission (10%) | — | `platform_fees.amount = 15, type = fashion_fit_commission` | Recorded | ✅ |
| 7 | Vendor Earning | — | $135.00 net ($150 - $15 commission) | Vendor payout $135.00 | — | `vendor_earnings.gross = 150, commission = 15, net = 135` | Pending | ✅ |
| 8 | Gateway Fee | — | — | $4.65 (2.9% × $150 + $0.30) | Gateway: $4.65 | `gateway_fees.amount = 4.65` | Recorded | ✅ |
| 9 | UG Net | — | — | $15.00 - $4.65 = $10.35 | — | `platform_revenue.net = 10.35` | Net Revenue | ✅ |

---

## 8. REFUND & ADJUSTMENT CHAINS

### 8.1 Full Refund (Marketplace Order)

| Step | Transaction Type | Customer View | Vendor View | Driver View | Admin View | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|---|---|
| 1 | Refund Requested | Customer requests refund | — | — | Refund request received | `refund_requests.status = pending` | Pending | ✅ |
| 2 | Refund Approved | — | — | — | Admin approves $53.99 refund | `refund_requests.status = approved` | Approved | ✅ |
| 3 | Refund Processed | $53.99 credited to customer | -$42.50 earning reversed | -$3.99 earning reversed | Full reversal | `refunds.amount = 53.99, vendor_earnings.reversed, driver_earnings.reversed` | Refunded | ✅ |
| 4 | Gateway Refund | Customer receives $53.99 | — | — | Gateway refund $53.99 | Adyen/Stripe: refund $53.99 | Gateway Refunded | ✅ |
| 5 | Ledger Reversal | All entries reversed | All entries reversed | All entries reversed | Full reversal | All ledger entries reversed | Balanced | ✅ |

### 8.2 Partial Refund (Wrong Item)

| Step | Transaction Type | Customer View | Vendor View | Admin View | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|---|
| 1 | Partial Refund | $15.00 refunded | -$15.00 earning adjusted | $15.00 partial refund | `refunds.amount = 15, vendor_earnings.adjustment = -15` | Partial Refunded | ✅ |
| 2 | Gateway | $15.00 to customer | — | $15.00 gateway refund | Adyen/Stripe: partial refund $15.00 | Gateway Refunded | ✅ |

### 8.3 Driver Fee Adjustment

| Step | Transaction Type | Driver View | Admin View | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|
| 1 | Fee Adjustment | Driver fee adjusted $2.00 → $3.50 | Admin adjusts driver fee | `driver_earnings.delivery_fee = 3.50, adjustment = 1.50` | Adjusted | ✅ |

---

## 9. PAYOUT CHAIN (VENDOR & DRIVER)

### 9.1 Vendor Payout

| Step | Transaction Type | Vendor View | Admin View | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|
| 1 | Payout Requested | Vendor requests $500.00 payout | Request received | `payout_requests.amount = 500, status = pending` | Pending | ✅ |
| 2 | Payout Approved | — | Admin approves | `payout_requests.status = approved, payouts.status = approved` | Approved | ✅ |
| 3 | Payout Processing | Processing indicator shown | Processing | `payouts.status = processing` | Processing | ✅ |
| 4 | Payout Completed | $500.00 received | Disbursed | `payouts.status = paid, vendor_wallet.balance -= 500` | Paid | ✅ |
| 5 | Payout Fee | -$1.00 fee deducted | $1.00 payout fee | `payout_fees.amount = 1.00` | Recorded | ✅ |
| 6 | Net to Vendor | $499.00 received | — | `vendor_wallet.balance -= 499` | Final | ✅ |

### 9.2 Driver Payout

| Step | Transaction Type | Driver View | Admin View | Ledger Record | Status | Amounts Match |
|---|---|---|---|---|---|---|
| 1 | Payout Requested | Driver requests $300.00 | Request received | `payout_requests.amount = 300, status = pending` | Pending | ✅ |
| 2 | Approved | — | Admin approves | `payouts.status = approved` | Approved | ✅ |
| 3 | Processing | Processing | Processing | `payouts.status = processing` | Processing | ✅ |
| 4 | Completed | $300.00 received | Disbursed | `payouts.status = paid, driver_wallet.balance -= 300` | Paid | ✅ |
| 5 | Fee | -$0.75 fee | $0.75 fee | `payout_fees.amount = 0.75` | Recorded | ✅ |
| 6 | Net | $299.25 received | — | Final balance | Final | ✅ |

---

## 10. WALLET SYSTEM

### 10.1 Customer Wallet

| Transaction | Effect | Ledger Entry |
|---|---|---|
| Add Fund | Balance += amount | `customer_wallet.add_fund = amount` |
| Order Payment (wallet) | Balance -= order_total | `customer_wallet.payment = -order_total` |
| Refund | Balance += refund_amount | `customer_wallet.refund = refund_amount` |
| Loyalty Point Conversion | Balance += points × conversion_rate | `customer_wallet.loyalty_conversion = amount` |
| Cashback | Balance += cashback_amount | `customer_wallet.cashback = amount` |
| Referral Bonus | Balance += referral_bonus | `customer_wallet.referral = amount` |

### 10.2 Vendor Wallet

| Transaction | Effect | Ledger Entry |
|---|---|---|
| Earning Posted | Balance += net_earning | `vendor_wallet.earning = net_earning` |
| Payout Request | Balance -= payout_amount | `vendor_wallet.payout = -payout_amount` |
| Subscription Payment | Balance -= subscription_amount | `vendor_wallet.subscription = -amount` |
| Wallet Adjustment (admin) | Balance += adjustment | `vendor_wallet.adjustment = amount` |

### 10.3 Driver Wallet

| Transaction | Effect | Ledger Entry |
|---|---|---|
| Earning Posted | Balance += net_earning | `driver_wallet.earning = net_earning` |
| Payout Request | Balance -= payout_amount | `driver_wallet.payout = -payout_amount` |
| Tip Received | Balance += tip | `driver_wallet.tip = tip` |
| Wallet Adjustment (admin) | Balance += adjustment | `driver_wallet.adjustment = amount` |

---

## 11. GATEWAY RECONCILIATION

### 11.1 Adyen/Stripe Daily Reconciliation

| Field | Description |
|---|---|
| Gateway Transaction ID | Unique ID from payment provider |
| Internal Payment ID | Urban Goodz `payments.id` |
| Amount Charged | Amount authorized + captured |
| Amount Refunded | Total refunds for the day |
| Net Settlement | Amount settled to UG bank account |
| Processing Fee | Total gateway fees charged |
| Discrepancies | Any mismatches between internal and gateway records |

### 11.2 Reconciliation Rules

| Rule | Description |
|---|---|
| Daily Auto-Reconcile | System matches `payments` table against gateway settlement file |
| Discrepancy Alert | Any variance > $0.01 triggers admin alert |
| Manual Review Queue | Discrepancies flagged for manual resolution within 48 hours |
| Monthly Close | All pending reconciliations must be resolved before month-end close |

---

## 12. FINANCIAL REPORTING VIEWS

| Report | Admin Access | Vendor Access | Driver Access | Data Source |
|---|---|---|---|---|
| Daily Revenue | Yes | — | — | `platform_revenue` aggregated by date |
| Vendor Earnings Report | Yes | Own only | — | `vendor_earnings` per vendor |
| Driver Earnings Report | Yes | — | Own only | `driver_earnings` per driver |
| Gateway Reconciliation | Yes | — | — | `payments` vs. gateway settlement |
| Payout Summary | Yes | Own only | Own only | `payouts` per entity |
| Tax Report | Yes | Own only | Own | `tax_records` per entity |
| Disbursement Report | Yes | Own only | Own only | `disbursements` per entity |
| Platform P&L | Yes | — | — | All revenue and expense accounts |
