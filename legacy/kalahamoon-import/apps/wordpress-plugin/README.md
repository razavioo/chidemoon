# Kalahamoon WordPress Plugin

Connect your WordPress site to [Kalahamoon](https://kalahamoon.com) — display products, generate affiliate links, capture leads, and leverage AI-powered product comparison. **RTL-first design** for Persian, Arabic, and Kurdish locales.

## Features

### Product Display
- **Product Box** — Single product card with image, price, discount badge, marketplace badge, and CTA button
- **Product Grid** — Responsive grid with optional ranking mode (🥇🥈🥉)
- **Comparison Table** — Side-by-side specs comparison with winner highlighting
- **CTA Button** — Styled affiliate button with marketplace logo and price
- **Product Carousel** — Horizontal swipeable carousel with RTL-aware touch support
- **Lead Form** — AJAX form connected to Kalahamoon CRM with honeypot spam protection

### Affiliate Engine
- **Multi-provider link building** — Basalam, Digikala/Affilio, custom UTM
- **Link cloaking** — Pretty URLs at `/go/{slug}` with configurable 301/302/307 redirects
- **Click tracking** — Lightweight `sendBeacon()` tracking with analytics dashboard
- **Auto-disclosure** — Automatic affiliate disclosure insertion on posts with affiliate links

### SEO & Performance
- **JSON-LD schema** — Auto-generated Product/Offer structured data for Google rich results
- **RTL-first CSS** — All styles use CSS Logical Properties (zero direction-specific overrides)
- **Performance** — Vanilla JS (~5KB total), lazy loading, `loading="lazy"` on images, no jQuery
- **Cache** — WordPress Transients with configurable TTL (default 6 hours)

### Admin
- **Dashboard widget** — Quick overview of products, clicks, and sync status
- **Analytics page** — Click stats by product and by day
- **Products page** — Full product list with images, prices, and sync status
- **Affiliate links page** — Manage cloaked links with click counts

## Requirements

- WordPress 6.4+
- PHP 8.0+
- A [Kalahamoon](https://kalahamoon.com) account (Starter plan or above)

## Installation

1. Download the `kalahamoon` folder and upload it to `/wp-content/plugins/`
2. Activate the plugin in **Plugins → Installed Plugins**
3. Go to **Kalahamoon → Settings** in the admin menu
4. Click **Connect with Kalahamoon** and approve the OAuth connection (recommended).
   A legacy `sk_live_…` API key is still accepted for self-hosted setups.
5. Click **Sync products now**

> The OAuth connection requests these scopes: `profile products:read analytics:read
> leads:write affiliate:read affiliate:write ai:product_compare ai:product_content
> ai:image_generate` — covering product sync, lead capture, affiliate links, AI
> comparison/content, and the AI Image Studio.

## Configuration

### Settings

| Setting | Default | Description |
|---------|---------|-------------|
| API Key | — | Your `sk_live_...` key from Kalahamoon |
| API URL | `https://app.kalahamoon.com` | Kalahamoon instance URL |
| Organization Slug | — | Your org slug for lead capture |
| Persian Numerals | On | Use ۱۲۳ instead of 123 |
| Currency Unit | Toman | Display as تومان or ریال |
| Redirect Type | 301 | Affiliate link redirect code |
| Sync Interval | 6 hours | Product sync frequency |
| Disclosure Text | (auto) | Affiliate disclosure text |

### Gutenberg Blocks

All blocks are in the **Kalahamoon** category in the block editor:

| Block | Description |
|-------|-------------|
| `kalahamoon/product-box` | Single product card (optional stock badge) |
| `kalahamoon/product-grid` | Responsive product grid (grid / list / masonry / carousel) |
| `kalahamoon/comparison-table` | Specs comparison table |
| `kalahamoon/price-comparison` | Multi-marketplace buy-box; cheapest seller highlighted (uses `listings[]`) |
| `kalahamoon/cta-button` | Styled buy button |
| `kalahamoon/lead-form` | Contact / lead-capture form that submits straight to your Kalahamoon CRM (honeypot-protected). Toggle `showName/showEmail/showPhone/showMessage`. |
| `kalahamoon/price-alert` | Email subscribe form for price-drop notifications |
| `kalahamoon/ai-compare` | AI-generated head-to-head product comparison |
| `kalahamoon/pros-cons` | Pros & cons two-column block (optional AI generation) |
| `kalahamoon/rating-box` | Editorial verdict with score |
| `kalahamoon/shop-the-look` | Lifestyle image with clickable product hotspots |
| `kalahamoon/faq` | Accordion FAQ with FAQPage JSON-LD |
| `kalahamoon/testimonials` | Customer testimonials grid or scroll-snap slider |
| `kalahamoon/affiliate-disclosure` | Standalone disclosure callout |

### Shortcodes

```
[kalahamoon_product id="product-uuid" layout="vertical" show_price="1" cta_text="خرید"]
[kalahamoon_products ids="id1,id2,id3" columns="3" ranked="1"]
[kalahamoon_products category="قهوه‌ساز" limit="6" columns="3"]
[kalahamoon_price id="product-uuid"]
[kalahamoon_compare ids="id1,id2" specs="جنس,وزن,قیمت"]
[kalahamoon_favorites]
```

### REST API

The plugin exposes these endpoints:

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/wp-json/kalahamoon/v1/products` | GET | Public | List cached products |
| `/wp-json/kalahamoon/v1/products/{id}` | GET | Public | Get single product |
| `/wp-json/kalahamoon/v1/clicks` | POST | Public | Log affiliate click |
| `/wp-json/kalahamoon/v1/price-alerts` | POST | Public (rate-limited + honeypot) | Subscribe to price drop |
| `/wp-json/kalahamoon/v1/leads` | POST | Connected (rate-limited) | Submit lead to Kalahamoon CRM via `/api/public/leads` |
| `/wp-json/kalahamoon/v1/ai/compare` | POST | `edit_posts` | Proxy AI product comparison |
| `/wp-json/kalahamoon/v1/ai/generate-content` | POST | `edit_posts` | Proxy AI content (description / pros_cons / buying_guide) |
| `/wp-json/kalahamoon/v1/ai/generate-image` | POST | `upload_files` | Proxy AI Image Studio generation |
| `/wp-json/kalahamoon/v1/ai/save-image` | POST | `upload_files` | Sideload an AI image to the Media Library |
| `/wp-json/kalahamoon/v1/stats` | GET | Admin | Click analytics |
| `/wp-json/kalahamoon/v1/sync` | POST | Admin | Trigger product sync |
| `/wp-json/kalahamoon/v1/webhook` | POST | HMAC | Receive Kalahamoon webhook events |

### Webhook Events

Register your WordPress site as a webhook receiver in Kalahamoon to get real-time updates:

- `order.synced` — Update product inventory
- `lead.created` — Show admin notification
- `automation.executed` — Log activity

## File Structure

```
kalahamoon/
├── kalahamoon.php                      ← Plugin bootstrap
├── uninstall.php                  ← Clean uninstall
├── includes/
│   ├── class-kalahamoon-plugin.php     ← Main orchestrator
│   ├── class-kalahamoon-activator.php  ← DB tables + defaults
│   ├── class-kalahamoon-deactivator.php
│   ├── api/
│   │   ├── class-kalahamoon-api-client.php      ← HTTP client
│   │   └── class-kalahamoon-api-products.php    ← Product sync
│   ├── core/
│   │   ├── class-kalahamoon-product-cache.php   ← CPT + taxonomies
│   │   ├── class-kalahamoon-link-builder.php    ← Affiliate links
│   │   ├── class-kalahamoon-link-cloaker.php    ← /go/ redirects
│   │   ├── class-kalahamoon-click-tracker.php   ← Click analytics
│   │   ├── class-kalahamoon-schema-output.php   ← JSON-LD
│   │   └── class-kalahamoon-disclosure.php      ← Legal disclosure
│   ├── display/
│   │   └── class-kalahamoon-shortcodes.php      ← All shortcodes
│   ├── admin/
│   │   └── class-kalahamoon-admin.php           ← Settings + admin pages
│   ├── rest/
│   │   └── class-kalahamoon-rest-controller.php ← WP REST endpoints
│   └── i18n/
│       ├── class-kalahamoon-i18n.php
│       └── class-kalahamoon-rtl.php             ← Persian numerals
├── blocks/                        ← Gutenberg blocks (auto-registered)
│   ├── product-box/  product-grid/  comparison-table/  price-comparison/
│   ├── cta-button/  lead-form/  price-alert/  ai-compare/  pros-cons/
│   ├── rating-box/  shop-the-look/  faq/  testimonials/  affiliate-disclosure/
└── public/
    ├── css/kalahamoon-public.css       ← RTL-first styles
    └── js/
        ├── kalahamoon-click-tracker.js ← sendBeacon tracking
        ├── kalahamoon-forms.js        ← lead-form + price-alert submit
        ├── kalahamoon-carousel.js     ← product carousel
        └── kalahamoon-favorites.js    ← localStorage favorites
```

## Database Tables

The plugin creates 5 custom tables on activation:

| Table | Purpose |
|-------|---------|
| `{prefix}_kalahamoon_clicks` | Affiliate click tracking log |
| `{prefix}_kalahamoon_affiliate_links` | Local affiliate link mirror |
| `{prefix}_kalahamoon_price_history` | Price change history |
| `{prefix}_kalahamoon_price_alerts` | Email subscriptions for price drops |
| `{prefix}_kalahamoon_auto_links` | Keyword → product auto-linking rules |

## API Key Scopes

When generating an API key in Kalahamoon, you can assign these scopes:

| Scope | Description |
|-------|-------------|
| `products:read` | Read product catalog |
| `affiliate:read` | View affiliate links and metrics |
| `affiliate:write` | Create/manage affiliate links |
| `ai:read` | Use AI comparison and content generation |
| `leads:write` | Submit leads from WordPress forms |
| `analytics:read` | View dashboard statistics |
| `*` | All scopes |

## Development

### Kalahamoon-side API Endpoints

The plugin communicates with these Kalahamoon API endpoints:

| Endpoint | Scope | Description |
|----------|-------|-------------|
| `GET /api/public/products` | `products:read` | Fetch products with listings |
| `POST /api/public/affiliate-links/batch` | `affiliate:write` | Provision cloaked affiliate links |
| `GET /api/public/affiliate-metrics` | `affiliate:read` | Batch clicks/conversions/revenue |
| `POST /api/public/ai/compare-products` | `ai:product_compare` | AI product comparison |
| `POST /api/public/ai/generate-description` | `ai:product_content` | AI content generation |
| `POST /api/public/ai/generate-image` | `ai:image_generate` | AI Image Studio |
| `POST /api/public/leads` | `leads:write` | Lead submission (honeypot-protected) |

## License

GPL v2 or later
