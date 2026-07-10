# Urban Goodz — Business Portal Demo Readiness

## 1. Demo Goal

Demonstrate a working **Business Portal** where a business owner can log in, see their branded dashboard, and navigate routes, locations, users, and documents scoped to their own business client account.

---

## 2. Working Super Admin URLs

| Page | URL |
|---|---|
| Login | `https://admin.urbangoodzdelivery.com/admin` |
| Business Clients | `https://admin.urbangoodzdelivery.com/admin/urban-goodz/business-clients` |
| Urban Goodz Control Center | `https://admin.urbangoodzdelivery.com/admin/urban-goodz` |

---

## 3. Working Business Portal URLs

| Page | URL |
|---|---|
| Login | `https://admin.urbangoodzdelivery.com/business/login` |
| Dashboard | `https://admin.urbangoodzdelivery.com/business/dashboard` |
| Routes | `https://admin.urbangoodzdelivery.com/business/routes` |
| Locations | `https://admin.urbangoodzdelivery.com/business/locations` |
| Users | `https://admin.urbangoodzdelivery.com/business/users` |
| Documents | `https://admin.urbangoodzdelivery.com/business/documents` |

---

## 4. Business Owner Demo Account Creation Steps

### 4.1 Prerequisites
- Super Admin credentials (provided by team lead)
- Access to `https://admin.urbangoodzdelivery.com/admin`

### 4.2 Steps

1. **Log in to Super Admin** at `https://admin.urbangoodzdelivery.com/admin`
2. Navigate to **Urban Goodz → Business Clients**
3. Click **Create**
4. Fill in the form:
   - **Company Name**: `Demo Business Client` (or any name)
   - **Email**: Use a unique email (e.g. `demo@businessclient.com`)
   - **Status**: Select `approved`
   - Fill optional fields as desired
   - Click **Save**
5. The business client is now created and approved
6. Click the client's name or the **Users** count to open the Users page
7. Click **Add User**
8. Fill in the form:
   - **First Name**: `Demo`
   - **Last Name**: `Owner`
   - **Email**: Use a unique email (e.g. `demo.owner@businessclient.com`)
   - **Temporary Password**: Choose a password (minimum 8 characters) — **do not commit to code**
   - **Confirm Password**: Re-enter the password
   - **Role**: `owner_admin`
   - **Status**: `active`
   - Check **Active**
   - Click **Create User**
9. Open an **incognito/private browser window**
10. Navigate to `https://admin.urbangoodzdelivery.com/business/login`
11. **Note the branded login page** (Urban Goodz wordmark, orange accents, clean split layout)
12. Enter the email and password from step 8
13. You should see the **Business Dashboard** with:
    - Company name displayed in the top bar
    - Welcome message with helper text
    - Dashboard cards showing 0 counts (empty state)
14. Navigate through each section:
    - **Routes** — shows "No routes submitted yet" with helper text
    - **Locations** — shows "No locations added yet" with helper text
    - **Users** — shows "No additional users added yet" with helper text
    - **Documents** — shows "No documents uploaded yet" with helper text
15. Click **Logout** from the user dropdown
16. Confirm you are redirected to the login page
17. Confirm that accessing `/business/dashboard` while logged out redirects to login

---

## 5. Recommended Demo Walkthrough Order

| Step | Section | What to Show | What It Proves |
|---|---|---|---|
| 1 | Super Admin → Business Clients | Create a business client | Super Admin can onboard clients |
| 2 | Super Admin → Users (under client) | Create a business owner user with password | Password creation with hashing works |
| 3 | `/business/login` | Branded login page | Urban Goodz branding applied |
| 4 | `/business/dashboard` | Dashboard with company name, cards, welcome message | Business name shown, scoped data |
| 5 | `/business/routes` | Empty state with helper text | Routes section works, scoped by `business_client_id` |
| 6 | `/business/locations` | Empty state with helper text | Locations section works, scoped |
| 7 | `/business/users` | Empty state with helper text | Users section works, scoped |
| 8 | `/business/documents` | Empty state with helper text | Documents section works, scoped |
| 9 | Logout | Redirect to login | Session management works |
| 10 | Post-logout access | Redirected to login when accessing dashboard | Auth guard works |

---

## 6. What Each Section Proves

| Section | Proof Point |
|---|---|
| Login page | Urban Goodz branding applied, business guard authenticates correctly |
| Dashboard | Company name visible, data is scoped by `business_client_id`, welcome helper text displayed |
| Routes | Business can view/create routes scoped to their account, empty state is clear |
| Locations | Business can view locations scoped to their account, empty state is clear |
| Users | Business can view team members scoped to their account, empty state is clear |
| Documents | Business can view documents scoped to their account, empty state is clear |
| Logout | Session ends, user cannot access protected pages after logout |

---

## 7. Known Pending Items

- Business Portal deeper workflow actions (route editing, location CRUD from business side)
- Employee roles visibility in sidebar (role-based navigation filtering)
- Command Center button polish
- Info-only modules labeled clearly (Invoices is currently read-only)

---

## 8. Workflow Pending / Info Only Explanation

Some modules are marked **Info Only** because they display data but do not yet have full business-side create/edit/delete workflows:

| Module | Status | Note |
|---|---|---|
| Routes | **Active** — Create + View working | Business can submit new routes |
| Locations | **View Only** — Currently visible | Contact admin to add/edit locations |
| Users | **View Only** — Currently visible | Contact admin to invite additional users |
| Documents | **View Only** — Currently visible | Contact admin to upload documents |
| Invoices | **Info Only** — Read-only counter | Invoices managed via admin Payment Center |

These modules work and display data correctly scoped to the business client. Deeper workflow actions can be added in a future sprint.

---

## 9. Payment Center Current Status

**Handled by Session 1.** The Payment Center is in the admin panel and is being stabilized separately. Business Portal does not manage payments directly — invoices are view-only counters.

---

## 10. Business Portal Current Status

| Component | Status | Notes |
|---|---|---|
| Auth (login/logout) | ✅ Working | Business guard, password hashed, account active checks |
| Dashboard | ✅ Working | Company name, welcome message, stat cards |
| Routes | ✅ Working | Create + list + show, scoped by `business_client_id` |
| Locations | ✅ Working | List + show, scoped by `business_client_id` |
| Users | ✅ Working | List, scoped by `business_client_id` |
| Documents | ✅ Working | List, scoped by `business_client_id` |
| UI Branding | ✅ Complete | Urban Goodz colors, ug-admin.css, text logo |
| Empty States | ✅ Complete | Descriptive helper text on all sections |
| Client Name Display | ✅ Complete | Navbar shows company name |
| No 500 Errors | ✅ Verified | All pages load without server errors |

---

## 11. Next Build Priorities (After Deadline)

1. **Business-side location management** — Add/edit locations from Business Portal
2. **Employee role visibility** — Filter nav/sections based on user role and permissions
3. **Document upload from Business Portal** — Allow business users to upload documents
4. **Route editing** — Edit and cancel existing routes from Business Portal
5. **Invoices detail view** — Show invoice list with status and download links
6. **Notifications** — Route status changes, document approval alerts
7. **Driver/vendor/customer app tie-ins** — Cross-platform integration
8. **Full Load Board payment flow** — End-to-end payment for Load Board
9. **Full Fashion Fit payment flow** — End-to-end payment for Fashion Fit
10. **Provider-agnostic payment architecture** — Abstract payment gateway layer
