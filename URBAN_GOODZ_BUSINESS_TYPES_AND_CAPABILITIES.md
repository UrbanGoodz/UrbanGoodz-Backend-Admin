# Urban Goodz Business Types and Capabilities

## Business Types Registry (18)

| # | Slug | Display Name | Description |
|---|---|---|---|
| 1 | `restaurant` | Restaurants / Food Trucks | Food and beverage service |
| 2 | `grocery` | Grocery / Markets | Grocery and market goods |
| 3 | `retail` | Retail / Shopping | General retail and shopping |
| 4 | `beauty-supply` | Beauty Supply / Hair Providerz | Beauty products and hair services |
| 5 | `pharmacy` | Pharmacy / Health | Pharmacy and health products |
| 6 | `liquor` | Liquor / Beveragez | Alcoholic beverages |
| 7 | `thc-cbd` | THC / CBD | Cannabis and CBD products |
| 8 | `home-based` | Home-Based Businessz | Home-based businesses |
| 9 | `events` | Local Events / Creators | Events and creator gatherings |
| 10 | `car-rental` | Car Rentalz | Vehicle rental services |
| 11 | `equipment-rental` | Equipment Rentalz | Equipment and tool rental |
| 12 | `courier` | Courier / Parcel | Parcel and document delivery |
| 13 | `medical-courier` | Medical Courier | Medical and lab transport |
| 14 | `professional-services` | Professional Services | Consulting and professional services |
| 15 | `fashion-fit` | Fashion Fit | Fashion measurement and tailoring |
| 16 | `creator-commerce` | Creator Commerce | Content creator commerce |
| 17 | `general` | General / Order Anywhere | General-purpose Order Anywhere |
| 18 | `logistics` | Logistics / Load Board | Freight and logistics load board |

---

## Capabilities Registry (18)

### Core Capabilities
| # | Slug | Display Name | Description | Default | Required |
|---|---|---|---|---|---|
| 1 | `direct-checkout` | Direct Checkout | Customers purchase directly from store | ✅ Yes | ✅ Yes |
| 2 | `public-listing` | Public Listing | Store appears in browse/search results | ✅ Yes | ✅ Yes |
| 3 | `admin-managed` | Admin Managed | Store is managed by Urban Goodz admin | ✅ Yes | ✅ Yes |

### Fulfillment Capabilities
| # | Slug | Display Name | Description |
|---|---|---|---|
| 4 | `order-anywhere` | Order Anywhere | Customers request items not in catalog |
| 5 | `rentals` | Rentals | Rental/booking-based ordering |

### Fashion Capabilities
| # | Slug | Display Name | Description |
|---|---|---|---|
| 6 | `fashion-fit` | Fashion Fit | Measurement-based fashion ordering |

### AI Capabilities
| # | Slug | Display Name | Description |
|---|---|---|---|
| 7 | `ai-concierge` | AI Concierge | AI-powered customer assistance |
| 8 | `discovery` | Discovery | AI-powered product discovery |
| 9 | `ask` | Ask Urban Goodz | Q&A with AI about products/services |

### Services Capabilities
| # | Slug | Display Name | Description |
|---|---|---|---|
| 10 | `book-anything` | Book Anything | Service appointment booking |

### Content Capabilities
| # | Slug | Display Name | Description |
|---|---|---|---|
| 11 | `creator-commerce` | Creator Commerce | Content creator storefront |

### Social Capabilities
| # | Slug | Display Name | Description |
|---|---|---|---|
| 12 | `community-marketplace` | Community Marketplace | Community posts and social features |
| 13 | `events` | Events | Event listings and ticketing |

### Logistics Capabilities
| # | Slug | Display Name | Description |
|---|---|---|---|
| 14 | `logistics` | Logistics | Freight/load board logistics |
| 15 | `medical-courier` | Medical Courier | Certified medical transport |

### Monetization Capabilities
| # | Slug | Display Name | Description |
|---|---|---|---|
| 16 | `earn-money` | Earn Money | Affiliate/earnings opportunities |

### Subscription Capabilities
| # | Slug | Display Name | Description |
|---|---|---|---|
| 17 | `plus` | Urban Goodz Plus | Premium subscription features |

### Marketing Capabilities
| # | Slug | Display Name | Description |
|---|---|---|---|
| 18 | `spotlight` | Spotlight | Featured/spotlight promotions |

---

## Business Type: Capability Matrix

### 1. restaurant — Restaurants / Food Trucks
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ✅ | ❌ |
| ai-concierge | ✅ | ❌ |
| discovery | ✅ | ❌ |
| earn-money | ✅ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Orders (direct), Menu/Products, Inventory, Staff, Messages, Payouts, Reports, Settings

**Order Flows:** Direct checkout (customer orders from menu), Order Anywhere (customer requests non-menu item)

---

### 2. grocery — Grocery / Markets
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ✅ | ❌ |
| ai-concierge | ✅ | ❌ |
| discovery | ✅ | ❌ |
| earn-money | ✅ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Orders (direct), Products, Inventory, Staff, Messages, Payouts, Reports, Settings

**Order Flows:** Direct checkout, Order Anywhere

---

### 3. retail — Retail / Shopping
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ✅ | ❌ |
| ai-concierge | ✅ | ❌ |
| discovery | ✅ | ❌ |
| earn-money | ✅ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Orders (direct), Products, Inventory, Staff, Messages, Payouts, Reports, Settings

**Order Flows:** Direct checkout, Order Anywhere

---

### 4. beauty-supply — Beauty Supply / Hair Providerz
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ✅ | ❌ |
| ai-concierge | ✅ | ❌ |
| discovery | ✅ | ❌ |
| book-anything | ✅ | ❌ |
| earn-money | ✅ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Orders (direct), Products, Staff, Messages, Payouts, Reports, Settings

**Order Flows:** Direct checkout, Order Anywhere, Service booking (appointments)

---

### 5. pharmacy — Pharmacy / Health
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ✅ | ❌ |
| ai-concierge | ✅ | ❌ |
| discovery | ✅ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Orders (direct + delivery), Products, Inventory, Staff, Messages, Payouts, Reports, Settings

**Order Flows:** Direct checkout, Order Anywhere

---

### 6. liquor — Liquor / Beveragez
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ✅ | ❌ |
| ai-concierge | ❌ | ❌ |
| discovery | ❌ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Orders (direct + delivery), Products, Inventory, Staff, Messages, Payouts, Reports, Settings

**Order Flows:** Direct checkout, Order Anywhere

---

### 7. thc-cbd — THC / CBD
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ✅ | ❌ |
| ai-concierge | ❌ | ❌ |
| discovery | ❌ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Orders (direct + delivery), Products, Inventory, Staff, Messages, Payouts, Reports, Settings

**Order Flows:** Direct checkout, Order Anywhere

---

### 8. home-based — Home-Based Businessz
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ✅ | ❌ |
| ai-concierge | ✅ | ❌ |
| discovery | ✅ | ❌ |
| earn-money | ✅ | ❌ |
| community-marketplace | ✅ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Orders (direct), Products, Staff, Messages, Payouts, Reports, Settings

**Order Flows:** Direct checkout, Order Anywhere

---

### 9. events — Local Events / Creators
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| events | ✅ | ✅ |
| order-anywhere | ❌ | ❌ |
| ai-concierge | ❌ | ❌ |
| discovery | ❌ | ❌ |
| earn-money | ✅ | ❌ |
| community-marketplace | ✅ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Event Listings, Orders, Attendees, Messages, Payouts, Reports, Settings

**Order Flows:** Ticket/event checkout

---

### 10. car-rental — Car Rentalz
| Capability | Default | Required |
|---|---|---|
| rentals | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ❌ | ❌ |
| ai-concierge | ❌ | ❌ |
| discovery | ❌ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Rentals (bookings), Fleet, Customers, Messages, Payouts, Reports, Settings

**Order Flows:** Rental booking flow

---

### 11. equipment-rental — Equipment Rentalz
| Capability | Default | Required |
|---|---|---|
| rentals | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ❌ | ❌ |
| ai-concierge | ❌ | ❌ |
| discovery | ❌ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Rentals (bookings), Inventory/Equipment, Customers, Messages, Payouts, Reports, Settings

**Order Flows:** Rental booking flow

---

### 12. courier — Courier / Parcel
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| logistics | ✅ | ✅ |
| order-anywhere | ❌ | ❌ |
| ai-concierge | ❌ | ❌ |
| discovery | ❌ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Tasks, Dispatch, Drivers, Messages, Payouts, Reports, Settings

**Order Flows:** Dispatch/task flow

---

### 13. medical-courier — Medical Courier
| Capability | Default | Required |
|---|---|---|
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| medical-courier | ✅ | ✅ |
| logistics | ✅ | ✅ |
| order-anywhere | ❌ | ❌ |
| ai-concierge | ❌ | ❌ |
| discovery | ❌ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Tasks, Dispatch, Drivers, Certifications, Messages, Payouts, Reports, Settings

**Order Flows:** Certified dispatch/task flow

---

### 14. professional-services — Professional Services
| Capability | Default | Required |
|---|---|---|
| book-anything | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| ai-concierge | ✅ | ❌ |
| discovery | ✅ | ❌ |
| order-anywhere | ❌ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Bookings, Services, Staff, Clients, Messages, Payouts, Reports, Settings

**Order Flows:** Service booking flow

---

### 15. fashion-fit — Fashion Fit
| Capability | Default | Required |
|---|---|---|
| fashion-fit | ✅ | ✅ |
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| order-anywhere | ❌ | ❌ |
| ai-concierge | ✅ | ❌ |
| discovery | ✅ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Measurement Requests, Gallery, Tailors/Stylists, Orders, Messages, Payouts, Reports, Settings

**Order Flows:** Measurement request → quote → checkout → fulfillment

---

### 16. creator-commerce — Creator Commerce
| Capability | Default | Required |
|---|---|---|
| creator-commerce | ✅ | ✅ |
| direct-checkout | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| ai-concierge | ✅ | ❌ |
| discovery | ✅ | ❌ |
| earn-money | ✅ | ❌ |
| community-marketplace | ✅ | ❌ |
| order-anywhere | ❌ | ❌ |
| spotlight | ✅ | ❌ |

**Admin Sections:** Dashboard, Content, Products, Orders, Fans/Followers, Messages, Payouts, Reports, Settings

**Order Flows:** Direct checkout, Subscription/fan support

---

### 17. general — General / Order Anywhere
| Capability | Default | Required |
|---|---|---|
| admin-managed | ✅ | ✅ |
| order-anywhere | ✅ | ✅ |
| ai-concierge | ✅ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| public-listing | ❌ | ❌ |
| direct-checkout | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Order Anywhere Requests (view only), Messages, Payouts, Reports, Settings

**Order Flows:** Order Anywhere request → admin quote → customer pay → fulfillment

**Note:** This business type exists for stores that ONLY handle Order Anywhere. Regular stores with their own business type also have Order Anywhere as an optional capability.

---

### 18. logistics — Logistics / Load Board
| Capability | Default | Required |
|---|---|---|
| logistics | ✅ | ✅ |
| public-listing | ✅ | ✅ |
| admin-managed | ✅ | ✅ |
| direct-checkout | ✅ | ✅ |
| order-anywhere | ❌ | ❌ |
| ai-concierge | ❌ | ❌ |
| discovery | ❌ | ❌ |
| earn-money | ❌ | ❌ |
| community-marketplace | ❌ | ❌ |
| spotlight | ❌ | ❌ |

**Admin Sections:** Dashboard, Loads, Carriers, Dispatch, Drivers, Messages, Payouts, Reports, Settings

**Order Flows:** Load posting → carrier bid → award → fulfillment

---

## Order Anywhere Ownership

**Key Rule:** Order Anywhere is NOT a business type. It is a *capability* that can be enabled on any business type (except rentals, logistics, and events).

**Ownership:**
- Order Anywhere is owned by Master Admin / Urban Goodz Operations
- Individual stores/vendors do NOT receive Order Anywhere admin panels
- Stores with the `order-anywhere` capability receive requests but cannot manage the queue

**Correct Flow:**
```
Customer → AI Concierge → Order Anywhere Request → Master Admin Queue
  → Admin reviews → Admin quotes price → Customer approves/pays
  → Admin assigns store/driver → Fulfillment → Proof of delivery
```

**What Vendors See:**
Vendors with `order-anywhere` capability see incoming Order Anywhere requests assigned to their store. They can accept/reject and provide availability, but they do not see the full queue, cannot quote prices independently, and cannot manage the Order Anywhere lifecycle.
