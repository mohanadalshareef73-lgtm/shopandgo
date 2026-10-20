# Shop and Go Shipping

Laravel 9 shipping management app with Arabic Blade views, separate admin and
customer logins, orders, trips, payments, treasury balances, reports, and WhatsApp
notifications. Frontend assets are built with Vite; the dashboard also uses
assets committed under `public/assets`.

## Local development

Use PHP 8.2–8.4 (PHP 8.3 is installed locally). The current Composer lock file
does not support PHP 8.5. Run commands below with a compatible PHP on your PATH.
On this Mac, its executable is `/opt/homebrew/opt/php@8.3/bin/php`.

Install the locked dependencies with `composer install` and `npm ci`. If Composer
is not installed globally, a downloaded copy is currently at
`/private/tmp/shipping-composer.phar`; run it with PHP 8.3.

For a new local environment, copy `.env.example` to `.env`, set `APP_URL` to
`http://127.0.0.1:8001`, and generate a key with `php artisan key:generate`.
For SQLite, create `storage/app/shein-local.sqlite` and set `DB_CONNECTION=sqlite`
and `DB_DATABASE` to that file's absolute path. Set `MAIL_MAILER=log` for local
email. Leave external API credentials blank unless you intend to configure them.

On a fresh local database, run:

```sh
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve --host=127.0.0.1 --port=8001
```

Admin login: `http://127.0.0.1:8001/admin/login`. The existing admin seeder creates
`admin@shop.ly` with password `TEST2026` for local development. Customer login
is at `/login`; the seeders do not create customer accounts.

## Amazon product quotes

The admin sidebar has one **السلات / Carts** page (`/carts`) for SHEIN and
Amazon. Its **المتجر** filter selects all retailers, SHEIN, or Amazon; status
counts, search and pagination follow that selection. It provides a **فحص منتج أمازون** form
(`/carts/create?retailer=amazon`), and saved pricing details on each quote.
The manual form reuses the quote client and worker, accepts quantity and a
customer phone, and leaves customer messaging to the operator. **إعدادات النظام**
lets operators edit Amazon pricing rules alongside SHEIN settings and the shared exchange rate.

The Amazon settings tab includes one editor per customer status message, like
SHEIN: receipt, rejected links, unavailable products, unsupported storefronts,
staff follow-up, submission failures, timeouts, processing failures, and
successful PDF captions with quantity and discount lines. The saved text is used
for every customer, with Arabic defaults. Former bilingual settings preserve
the saved Arabic text, or English text if only English was saved, until replaced.
Each editor documents its supported placeholders; saved text applies to new replies.
Editing requires `system-edit`, and changes are recorded in the settings log.
`REGION_NOT_ALLOWED` and `REGION_UNVERIFIED` use their dedicated editable replies
for both submission rejections and completed failures received through polling or
webhooks. The `{site}` placeholder follows the configured pricing storefront;
it does not represent the account's allowed input regions. For submission region
restrictions, `{provider_message}` includes the provider's allowed-region details.
The unsupported-site reply is separate from the general `INVALID_URL` reply and
supports `{supported_sites}` (`amazon.ae, amazon.sa, amazon.com`).
Apply `2026_10_10_000001_add_amazon_message_templates.php` with `php artisan migrate`
when deploying. Existing messages remain the defaults until saved.

WhatsApp supports Amazon product URLs on `amazon.ae`, `amazon.sa`, and
`amazon.com` (including `www`), plus share links on `amzn.eu`, `amzn.to`, `a.co`,
and `amzn.asia`. Product shapes include `/dp/<ASIN>`, `/gp/product/<ASIN>`, and
`/gp/aw/d/<ASIN>`. Links are forwarded unchanged; the provider validates them
and rejects wish lists, carts, searches, and store pages.
Other recognized Amazon marketplaces, including `amazon.fr` and `amazon.co.uk`,
are sent to the provider for an explicit `400 INVALID_URL` unsupported-site
rejection and the corresponding editable reply, without a pricing acknowledgement.

Our provider account's **settings.storefronts.amazon is AMZ-AE (`amazon.ae`)**.
The local `config('settings.storefronts.amazon')` mirrors that setting; override
it with `CART_AMAZON_STOREFRONT` only when the provider account setting changes.
A link from another supported Amazon site is repriced on our configured
Amazon storefront unless the provider account restricts input regions, in which
case submission returns `422 REGION_NOT_ALLOWED`. The application does not send
a storefront override or reject cross-site links locally.

An accepted product missing from the account's marketplace, or unavailable for
delivery to the account's location, fails with `PRODUCT_UNAVAILABLE`. A page on
the wrong site or with an unexpected currency fails with `REGION_UNVERIFIED`.
The provider releases the hold for these failed quotes; unsupported sites and
restricted input regions are rejected before a quote is accepted. Provider quote
fees are charged only on success; the application keeps the provider's reported
billing status and does not implement separate charging or refund calls.

Amazon uses the existing Cart Quotes client, signed `/hooks/cart-quotes`
webhook, durable quote-to-WhatsApp mapping, and `cart-quotes:process` worker.
For single-link Amazon messages, the `Idempotency-Key` remains the original
WhatsApp message ID. For multiple links, each URL receives a distinct hashed
key that stays stable across submission retries and message redelivery.
Previously saved first-link quotes retain their original keys. `CART_API_URL`, `CART_API_KEY`, and `CART_WEBHOOK_SECRET`
are read only on the server; the existing `CARTQUOTES_*` names remain supported
as fallbacks. Keep credentials in the environment.

Each Amazon quote contains one product with quantity 1 (`assumed_one`). Explicit
requests such as `3 pieces`, `quantity: 3`, or `الكمية ٣` multiply our customer
unit price locally using decimal arithmetic; the reply explains the quantity.
Amazon pricing is calculated in this app from the USD product-page sale price
(`result.items[0].usd.unitSale`, or `result.usd.sale` when the item value is absent):
**base price + $5 + ($10 below $100, otherwise 10% of the base price)**.
These are the defaults. Operators with `system-edit` can change the fixed fee,
flat surcharge, threshold, and percentage in `/settings` (up to four decimal places).
At or above the threshold the percentage replaces the flat surcharge.
For example, $80 becomes $95 and $150 becomes $170 before LYD conversion.
The surcharge is per product, before multiplying by the requested quantity.
The existing `settings.usd_to_lyd_rate` converts the marked-up total to LYD;
only the final LYD total is rounded half-up to a whole dinar, as with existing
app quotations. The provider's marked-up `result.total` and API fee are not added.
The separate `amazon_pricing` snapshot freezes the base price, surcharge,
quantity, all four rule values, rate, and customer total before delivery; retries preserve it.
Settings changes apply to new quotations; existing snapshots, PDFs and customer totals remain stable.
Missing USD page prices or an unset exchange rate defer delivery without
inventing a price. The admin list/detail show the same saved customer total.
SHEIN pricing settings and PDF calculations remain unchanged.
Discounts compare the native original/sale prices.
Amazon replies use one saved message per status, without automatic language
switching. The current WhatsApp sender sends Amazon success as an orange/charcoal Amazon product quotation PDF,
issued by SHOP AND GO, with the customer price caption and the same confirm,
edit-quantity, and cancel buttons as SHEIN. Product images are included when
available from allowlisted Amazon image hosts; the PDF never shows the internal
USD reference price or provider fee. Failed quotes receive text replies.
Authenticated operators can download the PDF from the quote detail page.
Button receipts use the existing customer/session checks, deduplication, status
audits, and saved reply templates (shared by SHEIN and Amazon in Settings).
Edit requests start the existing staff quantity-review workflow. Manual quotes
prepare the same Amazon PDF without sending unsolicited customer messages.

Alternative-seller offers keep the same `succeeded` response and webhook flow.
When the first item note begins with `Notice:`, the exact notice appears under
the product in the PDF and admin page, and in the WhatsApp caption. `Sold by …`
and `Delivery …` are shown even when the seller is the fourth note. Delivery is
explicitly excluded from the quoted total; it does not change local pricing.
The success template supports `{offer_details}`; existing templates receive
the disclosure automatically. Products without options are accepted. Used-only
offers still fail with `PRODUCT_UNAVAILABLE` and released billing. Resending a
failed link in a new WhatsApp message creates a fresh quote and leaves the old
failure untouched.

Messages containing Amazon links process every distinct supported Amazon/SHEIN
onelink URL. One acknowledgement is sent per incoming message; each product
gets its own quotation PDF and action buttons. Rejected links do not prevent
other links from being processed. Repeated identical links are submitted once,
and retrying one link does not resubmit the others. SHEIN-only messages retain
their existing multi-cart, product-guidance, wallet-pause, and PDF behavior.
Amazon submit errors produce the customer replies even when the SHEIN wallet
pause is enabled. Account, wallet, and idempotency problems create one staff
notification for administrators/employees and log the provider request ID.
Capacity errors wait at least `Retry-After` and make at most three resubmissions
with the same key. Successful webhooks always trigger a GET before delivery;
a missed webhook triggers a fallback GET at `deadlineAt` (15 minutes after submission).
A quote still pending at its deadline is checked again every minute until terminal.

Deploy the additive `2026_10_09_000001_add_amazon_quote_metadata`,
`2026_10_09_000002_add_amazon_pricing_snapshot`, and
`2026_10_09_000003_add_amazon_pricing_settings` migrations with
`php artisan migrate --force`, rebuild cached configuration, and keep the existing
minute scheduler running. No additional webhook endpoint or worker is needed.

To send a test quote, configure the existing restricted WhatsApp trial number
(`CARTQUOTES_WHATSAPP_TEST_NUMBER`, and the optional linked-device sender ID),
then send `Price please https://www.amazon.ae/dp/B0DF4NM7CY?th=1` from that
number to a configured business WhatsApp session. Expect the checking-price
reply, then a product/price reply or availability guidance after the signed
callback is processed. Check the existing cart record for its provider ID,
storefront, billing state, and payload. This sends a real billable quote;
automated tests use mocked provider/WhatsApp requests:

```sh
php vendor/bin/phpunit --filter 'AmazonQuoteIntegrationTest|AmazonQuotationTest'
php vendor/bin/phpunit --filter 'AmazonQuoteIntegrationTest|AmazonQuotationTest|CartQuoteIntegrationTest|SheinLinkClassifierTest|ManualCartTest|WhatsAppWebhookTest'
```

## SHEIN wallet pause

Only confirmed SHEIN cart links are submitted to the quotation provider.
Product links receive an Arabic reply asking the customer to add all desired
products to a cart and share its link. Edit this reply at `/settings` under
**الرد على روابط المنتجات بدلاً من السلة**. It is sent once per incoming message,
including messages containing multiple products; mixed messages still process
their cart links. Product guidance does not require provider wallet credit.
Short SHEIN links are resolved using their embedded destination or structured
share metadata. Redirects remain restricted to the configured SHEIN hosts;
unresolved links receive the same guidance and are never sent to the provider.
Manual cart checks also require a confirmed cart link. Apply migration
`2026_10_05_000002_add_shein_product_reply.php` during deployment.

The `/settings` page includes **إيقاف ردود سلات شي إن عند نفاد رصيد المحفظة**,
enabled by default. The app checks the provider's available credit before new
cart submissions, receipt acknowledgements, and quotation PDF delivery. Zero
available credit, an unverifiable wallet, or HTTP 402 pauses the affected cart
without sending a customer message. General WhatsApp AI replies continue normally.

Incoming carts stay saved with the same idempotency keys. The existing
`cart-quotes:process` scheduler checks paused carts every minute and resumes when
credit becomes available. Completed results and PDFs are preserved, so a recharge
does not resubmit an already accepted cart. Operators can see the pause reason in
the cart list and the wallet panel. Disabling this setting restores normal
processing without wallet checks.

Apply `2026_10_05_000001_add_shein_wallet_pause_setting.php` with `php artisan
migrate --force` during deployment. Keep `php artisan schedule:run` running every
minute so waiting carts resume automatically.

Use `npm run dev` when editing the Vite sources. Blade changes are picked up by
Laravel without rebuilding frontend assets.

## Code map

- `routes/web.php`: admin and customer routes; `routes/api.php`: WhatsApp webhook.
- `app/Http/Controllers/OrderController.php`: orders, items, payments, delivery,
  trip status updates, notes, invoices, and outgoing notifications.
- `app/Http/Controllers/CustomerController.php` and `CustomerAuthController.php`:
  customer administration and the customer portal.
- `app/Models`: Eloquent models and relationships.
- `resources/views/admin`, `resources/views/customers`, and `resources/views/layout`:
  pages and shared layouts, including inline JavaScript.
- `database/migrations` and `database/seeders`: schema and initial configuration.
- `app/Services` and `app/Jobs`: Gemini replies and WhatsApp delivery.

The WhatsApp webhook tests run with
`php vendor/bin/phpunit tests/Feature/WhatsAppWebhookTest.php` and mock external
services. The development dependency `jasonmccreary/laravel-test-assertions`
provides `JMac\Testing\Traits\AdditionalAssertions` for Blueprint-generated tests.
Run the full suite with `php vendor/bin/phpunit`. It currently executes 67 tests:
9 pass, while older generated tests produce 46 errors and 12 failures, including
references to missing models, controllers, and routes. Those counts describe the
original suite before the cart quote integration tests were added.

## Deploying the SHEIN changes

Keep the existing application/database configuration and API credentials. Add or
update these values in the server's `.env` (replace the credential placeholders):

```dotenv
WHATSAPP_API_URL=https://watsybackend.teccube.teccube.ly/api
WHATSAPP_API_TOKEN=YOUR_EXISTING_WHATSY_API_KEY
WHATSAPP_WEBHOOK_TOKEN=YOUR_WHATSAPP_WEBHOOK_TOKEN
WHATSAPP_MAIN_SESSION_ID=9eea2f5d165342ea8e9732215da1df7e
CART_API_URL=https://41-254-46-138.sslip.io:8443
CART_API_KEY=YOUR_CART_QUOTES_API_KEY
CART_WEBHOOK_SECRET=YOUR_COMPLETE_WHSEC_SECRET
CART_WEBHOOK_SECRET_PREVIOUS=
CARTQUOTES_WHATSAPP_TEST_NUMBER=
CARTQUOTES_WHATSAPP_TEST_SENDER_ID=
```

The main Session ID above belongs to `shop2go10267`. It is the fallback before
an administrator saves a selection at `/settings/whatsapp`; a database selection
takes precedence. The page discovers all numbers through the API. Remove any
obsolete `WHATSAPP_MAIN_NUMBER` and `WHATSAPP_NUMBER_*` variables. Leave both
test sender fields empty for normal operation, or set them to restrict a trial.
Leave `CART_WEBHOOK_SECRET_PREVIOUS` empty except for a few minutes during
secret rotation. `CARTQUOTES_SHEIN_HOSTS` is optional; its default allowlist is in
`.env.example`.

Use PHP 8.2–8.4 with the required Composer extensions, including GD and mbstring.
After checking out this branch and updating `.env`, run:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=CartPermissionsSeeder --force
php artisan config:cache
php artisan queue:restart
```

Use the specific permission seeder on existing installations. Laravel applies
only pending migrations; no manual SQL or database reset is required. All nine
new migrations use the prefix `2026_10_04_`:

| Suffix | Database change |
| --- | --- |
| `000001_create_cart_quotes_tables` | Creates cart quotations and webhook receipts. |
| `000002_create_settings_table` | Creates settings and the initial USD-to-LYD rate row. |
| `000003_add_operator_tracking_to_cart_quotes` | Adds follow-up status, notes and assigned operator. |
| `000004_add_origin_to_cart_quotes` | Identifies manual versus WhatsApp carts. |
| `000005_add_shein_quotation_settings` | Adds company percentage, messages, PDF notes and quotation/PDF snapshots. |
| `000006_remove_public_quotation_tokens` | Removes public PDF token/expiry columns; downloads require operator authentication. |
| `000007_add_cart_pdf_overrides` | Stores operator edits for individual quotation PDFs. |
| `000008_add_whatsapp_sessions` | Adds number snapshots and the receiving Session ID to incoming messages. |
| `000009_add_whatsapp_main_session_uuid` | Saves the main sender by UUID and preserves any previous slot selection. |

The initial exchange rate is unset. Set the rate and company percentage in
`/settings` before automatic PDF delivery, then confirm the main sender in
`/settings/whatsapp`. PDFs, their image cache and mPDF temporary files require
writable `storage` directories.

Keep Laravel's scheduler running every minute for polling and delivery retries:

```cron
* * * * * cd /path/to/shipping && /path/to/php artisan schedule:run >> /dev/null 2>&1
```

Configure `https://shopandgo.ly/webhooks/cart-quotes` as the quotation provider callback. Each Whatsy
number needs its own webhook setting pointing to
`https://YOUR_APP_DOMAIN/api/webhook/whatsapp?token=YOUR_WHATSAPP_WEBHOOK_TOKEN`.

## SHEIN settings and quotation PDFs

Open **إعدادات النظام → إعدادات شي إن** (`/settings`) and enter the number
of Libyan dinars for one US dollar. The rate starts unset, accepts positive
values with up to six decimal places, and is stored as a database decimal.
Users need `system-list` or `system-edit` to view it, and `system-edit` to save.
Changes are recorded in the existing activity log.

The same page controls the company percentage (default 0%, up to four decimal
places), acknowledgement message, completed quotation message and calculation
note printed in the PDF. Each unit is calculated as
`USD unit price × (1 + company percentage / 100) × USD-to-LYD rate`.
Unit and line prices retain their precision. Totals are calculated from source
USD lines, rather than rounded unit prices, and only the final LYD total is
rounded half-up to the nearest whole dinar. Decimal arithmetic uses `brick/math`, never
binary floats. Unavailable lines are listed without prices and excluded from
the total. Assumed quantities are explicitly flagged.

The provider payload is preserved. Pricing settings and the resulting itemized
customer quotation are frozen when its PDF is first prepared, so settings
changes apply to new quotations. PDFs use [mPDF](https://mpdf.github.io/) for
Arabic layout in a portrait, company-blue quotation on a white page with the
transparent SHOP AND GO logo and product thumbnails,
names, variants, quantities, availability, separate original/sale USD prices,
customer unit/line LYD prices, exchange rate, percentage and notes. The final
summary shows both USD and LYD totals. The configured percentage is included
in each displayed original/sale item price and is never shown as a separate
charge or mentioned in customer PDF notes. Internal pricing remains available
to operators. USD prices/totals, LYD unit/line prices and the exchange rate display
with exactly two decimal places (`0.00`). The final LYD total displays as an integer.
Display formatting is not fed back into calculations. Recurring unit divisions
retain 16 decimal places; original source line amounts are used for totals.
Legacy PDFs recalculate from their frozen provider prices and saved settings
under this policy without modifying the original snapshot. Native original prices use the
provider's own line conversion to USD when available; missing original prices
are left blank rather than invented. Layout upgrades regenerate PDFs from the
saved pricing snapshot without applying today's settings.
SHEIN discount labels use the original and final customer unit prices. Product
notes from SHEIN appear in orange, with sales and stock notes in gold. Unavailable
items appear in a separate gray section with faded grayscale thumbnails and
remain excluded from totals; native discount labels are retained when both
prices use the same currency.
Open a completed cart to download its PDF. Missing rates, item details or a USD
price basis prevent a misleading quotation and leave automatic delivery retryable.

Users with `cart-edit` can use **تعديل PDF فقط** on a completed cart to change
quantities, base original/sale USD prices, and the rate/percentage for that PDF.
An optional final USD unit price replaces the automatic percentage calculation.
Unavailable products remain excluded. Overrides are stored separately and never
rewrite the provider payload, saved quotation or system settings. Saving or
restoring overrides generates the PDF without sending WhatsApp or creating a
provider quote. If an existing automatic delivery is still retrying, its caption
uses the same effective quantities/total as the PDF. Concurrent editor saves
are rejected until the operator refreshes. The PDF's compact colored status badge follows
the operator status: عرض سعر, قيد المتابعة, قيد الشراء, تم الشراء or مستبعدة.

Files remain in private local storage and operator downloads require cart-list
permission. WhatsApp receives the PDF itself, encoded as base64, with
`X-API-Key` authentication on `POST /api/message`. The JSON fields are `phone`
(international digits in an array), `session_uuid`, `file_base64`, `file_name` and `message`
(the configured caption plus item count and LYD total). Files must be PDFs of
at most 7 MB. No public file URLs or multipart uploads are used. HTTP 202 means
accepted for background sending, not confirmed delivery to the customer's device.

## Whatsy cart reply buttons

Apply `2026_10_07_000001_add_cart_whatsapp_reply_buttons` with `php artisan migrate`.
Automatic quotation messages include the PDF, caption and a `buttons` array in
one `POST /api/message`: `confirm:CART-<id>` / تأكيد الطلب,
`edit:CART-<id>` / تعديل الكميات, and `cancel:CART-<id>` / إلغاء الطلب.
The service validates two or three unique IDs (up to 256 characters) and unique
titles (up to 20 characters), and supplies `buttons_text` for an empty caption.
The cart saves its reference, actual recipient phone, sending session UUID and
queue-acceptance timestamp. HTTP 400 indicates invalid input/buttons or an
unavailable session; 401 indicates an API-key problem; 403 indicates quota;
413 indicates a PDF over 7 MB. These statuses are logged without credentials.

Set an independent, random `WHATSAPP_WEBHOOK_TOKEN` of at least 32 characters.
There is no fallback to the API key. Generate a value with
`openssl rand -hex 32`, put it in the environment, then configure each Whatsy
session with `/api/webhook/whatsapp?token=<that-secret>` over HTTPS. Clear/rebuild
Laravel's configuration cache after changing it. Keep query strings out of proxy
access logs. Missing, incorrect and short configured secrets are rejected.

For `interactive.button_reply` taps, the webhook only validates and inserts a
durable receipt into `cart_whatsapp_button_events`; it does not call Whatsy,
Gemini or lock the cart. This keeps tap acknowledgement within the provider's
five-second window under normal database availability. The unique event ID
deduplicates webhook retries. Typed text and other message types retain their
existing handling. Whatsy's poll fallback uses the same button-reply format;
the context message ID is stored for diagnosis, not used to establish ownership.

Laravel's scheduler runs `cart-quotes:process-replies` every minute, independently
of the synchronous default queue connection. Keep `schedule:run` in cron or
`schedule:work` running locally. Run a single pass manually with:

```sh
php artisan cart-quotes:process-replies
```

The worker splits the ID at the first colon and verifies the saved reference,
customer phone and receiving session before changing the cart. Customer decisions
are stored separately from quote-generation status and displayed on the cart page.
Confirm sets the visible system status and customer decision to confirmed (مؤكدة).
Edit sets the system status to awaiting quantity changes (بانتظار تعديل الكميات) and asks
the customer to send PDF item numbers and new quantities for the team to review
and issue an updated quotation. Typed quantity messages continue through the
existing text handling; this feature does not automatically recalculate a quote.
Cancel sets the system status and customer decision to cancelled (ملغاة),
preserving the quotation/PDF. Confirmation and cancellation are terminal
for customer taps. Operator buying/purchased/dismissed states also block taps.
Repeated edits and confirmations have no additional effect. These statuses appear
in the cart list, filters, detail page, operator status selector and PDF stamp.

Apply `2026_10_07_000002_create_cart_status_audits` with `php artisan migrate`.
The cart detail page includes **سجل تغييرات الحالة**, a paginated, read-only audit
history ordered newest first. Every applied customer action and operator status
or note edit stores the previous/new status, previous/new customer decision,
note changes, timestamp in the application's Africa/Tripoli timezone and actor
type/name. Customer actions also store the verified phone, session and button
event ID; operator actions store the authenticated user ID. Actor names are
snapshots, so later profile changes/deletions do not erase attribution. Status
saves and their audits share a database transaction. Retries, ignored taps and
unchanged saves do not create extra history entries. History starts when this
feature is installed; earlier unrecorded changes are not reconstructed.

Staff status changes keep the customer decision in sync for confirmed, editing,
cancelled, buying, purchased and dismissed states. Setting a cart to new reopens
the customer decision and is audited; its original reply buttons can then be used
again. Merely editing notes does not reopen a confirmed or cancelled cart.

Replies use the saved customer phone and the same session. Decisions and pending
reply text are committed before sending, so failures retry the reply without
applying the action again. Superseded edit instructions are discarded. Logs include
event ID, action, cart reference, customer and session for receipt, application,
ignored/duplicate events and reply attempts. A timeout after provider acceptance
or a crash before saving reply acceptance can still result in a duplicate reply:
Whatsy does not expose a send idempotency key. HTTP 202 always means queued.
WhatsApp may display an AI tag on messages with buttons.

```sh
php vendor/bin/phpunit --filter 'CartWhatsAppButtonsTest|WhatsAppDocumentTest|CartQuoteIntegrationTest|WhatsAppWebhookTest'
```

## Whatsy numbers and automatic sender

Open **إعدادات واتساب** (`/settings/whatsapp`) to see the account's current
numbers and select the main sender. This page fetches `GET /api/sessions` with
`X-API-Key` authentication on every load; **تحديث الأرقام** refreshes names and
last-known connection states. There are no fixed number slots or manual ID fields.
Link or remove numbers in Whatsy, then refresh this page.

The selection is saved by Session UUID and verified against a fresh account
list before saving, so reordering the list cannot change the main sender.
`shop2go10267` remains the main sender selected during local setup.
A disconnected number can be selected explicitly; sends then fail with HTTP 400.
If Whatsy is unavailable, the page shows an error, preserves the saved sender,
and disables selection. A removed main number is flagged without switching to
another number. Pricing and quotation settings remain at `/settings`.

For installations without a saved selection, set the optional
`WHATSAPP_MAIN_SESSION_ID` environment value to the sending number's UUID.
The migration preserves the sender selected through the former fixed slots.

Order creation, shipment/status changes, order notes, and other outbound messages
use the main sender. Incoming AI replies, cart acknowledgements, and delayed
quotation PDFs use the receiving `entry[].id`, saved on the incoming message.
Conversation history is scoped to the customer and receiving Session ID.
Manually created carts and older messages without a saved session use the main sender.
All sending paths include `session_uuid` and authenticate with `X-API-Key`.
A missing/invalid Session ID blocks sending; a provider HTTP 400 is recorded for
that session and never switches to another number. Quote deliveries remain in
the existing retry flow; order sends keep their existing failure reporting.

Run `php artisan migrate` before using these settings, then clear cached
configuration after editing environment values and restart queue workers.
Configure the webhook URL separately on **each** number in Whatsy:
`https://your-app.example/api/webhook/whatsapp?token=YOUR_WEBHOOK_TOKEN`.
The webhook requires a valid receiving Session ID on entries containing messages.
Set the current number's Session ID before connecting an additional number.

## Operator cart follow-up

Open **السلات / Carts** (`/carts`) to see each received cart, its customer's sending
WhatsApp number, quote progress, quoted total and follow-up status. Search by
customer, phone or URL; use `#123` for an exact cart number. Status filters and
pagination make it possible to work through the list. The page-size selector
offers 10, 25 (default), 50 or 100 carts and preserves that choice with search,
status filters and page navigation. The redesigned list uses a status overview,
compact wallet balances, scrollable status tabs and a responsive toolbar/table.

The cart detail page contains the original message, the quotation's product
details, reply history, a WhatsApp chat link, and editable follow-up notes.
Operator statuses are **جديدة**, **قيد المتابعة**, **قيد الشراء**, **تم الشراء**,
and **مستبعدة**. Operators can reopen carts and change status as the customer's
decision changes. Updates record the operator and time in the cart and existing
activity log. Quote processing has its own status and preserves these notes
and follow-up decisions.

Use **فحص سلة شي إن** to add a cart manually. Enter a complete SHEIN cart URL
and the customer's WhatsApp number with its country code. `+218 91 1234567` and
`00218911234567` are normalized to `218911234567`. The form creates the cart in
the same list and requests a quotation using the configured provider API key.
Each form submission has a stable key, so repeated POSTs reuse the saved cart
and provider request. Temporary provider failures are saved and retried by the
scheduler; pending results are polled normally.

Manual entries are marked **إضافة يدوية** and fetch prices for operator review.
They use the saved phone for the WhatsApp follow-up link and do not send
automatic client messages. This allows checking carts before WhatsApp delivery
is configured. Incoming WhatsApp carts keep their acknowledgement and automatic
reply flow. Use **تحديث العرض** on the detail page to refresh progress.

Deploy the migration and run `php artisan db:seed --class=CartPermissionsSeeder`.
The repeatable seeder adds `cart-list`, `cart-edit`, and `cart-create` and grants
them to the existing Admin role. Give operators `cart-list` to view carts and
`cart-edit` to save follow-up changes. Add `cart-create` for manual checks through
the existing roles screen. Fresh installations
include these permissions automatically.

```sh
php vendor/bin/phpunit --filter 'WhatsAppDocumentTest|SheinQuotationTest|ManualCartTest|CartManagementTest|ExchangeRateSettingsTest|CartQuoteIntegrationTest|WhatsAppWebhookTest'
```

## SHEIN quotations through WhatsApp

Text messages received at `POST /api/webhook/whatsapp?token=...` are checked for
SHEIN links before Gemini is called. After the provider accepts at least one
link, the customer receives one acknowledgement for the message:

> استلمنا رابط سلة شي إن. سنجهز عرض السعر ونرسله لك قريباً.

WhatsApp group messages are ignored: no carts are created and no automatic
replies are sent. Whatsy can put the participant's phone or `@lid` in `from`,
so the filter also checks the group chat embedded in serialized message IDs
(`false_<group>@g.us_<message>_<participant>`), plus `chat_id` and `is_group`
when supplied. Existing carts with group senders or serialized group message
IDs are excluded from automatic retries and quotation delivery as well.
If a provider sends only a short message ID and a participant sender, it must
also supply `chat_id` or `is_group`. Without that context, shipping cannot
distinguish such messages from direct chats. Direct LID messages remain eligible.

For a restricted trial, set `CARTQUOTES_WHATSAPP_TEST_NUMBER=218916655677`.
Only SHEIN links sent by this WhatsApp number are processed or replied to;
other senders and non-SHEIN messages receive no automatic reply. The sender
may include a leading `+`. Existing automatic cart retries also respect this
restriction. Manual operator checks still work. Leave the value empty to restore
normal handling, and restart the local server and scheduler after changing it.
If the provider represents this same sender by a LID, configure
`CARTQUOTES_WHATSAPP_TEST_SENDER_ID=71184955744465`. Only this exact ID, with or
without `@lid`, is accepted as an alias of the configured test phone. Its carts
are linked to that phone and replies are addressed to that phone; unrelated LIDs
remain blocked. The business account's metadata is never used as the sender.

Each unique URL in a message is saved and submitted to `POST /v1/quotes` with a
stable idempotency key. Duplicate WhatsApp deliveries reuse the saved request;
network retries reuse the same key. After success, the same sender receives:

> تم تجهيز عرض سعر سلة شي إن. تجد التفاصيل في ملف PDF.
> عدد القطع: 7، الإجمالي: … LYD.

The same message carries the generated quotation as a PDF attachment.

The client receives the itemized LYD quotation calculated from the saved settings.
Money remains a decimal string; the API's billing fee is not added to the customer total.
Long product descriptions and messages containing multiple links are supported.
Each unique SHEIN URL is processed independently; unsupported hosts are ignored.
Rejected SHEIN submissions receive no automatic reply. For accepted quotes,
unavailable-item failures ask for a new link, other failures ask the customer to
retry after a few minutes, and expired quotes ask for the link again. Provider
cancellations remain silent. Replies are appended to the original message's history.

Configure these in the ignored local `.env`, using real secrets rather than
the redacted examples. Never commit them:

```dotenv
WHATSAPP_API_URL=https://your-whatsapp-provider.example/api
WHATSAPP_API_TOKEN=
WHATSAPP_WEBHOOK_TOKEN=
CART_API_URL=https://41-254-46-138.sslip.io:8443
CART_API_KEY=
CART_WEBHOOK_SECRET=
CART_WEBHOOK_SECRET_PREVIOUS=
CARTQUOTES_WHATSAPP_TEST_NUMBER=
CARTQUOTES_WHATSAPP_TEST_SENDER_ID=
```

The API key requires `quotes:write` and `quotes:read`. Use the complete `whsec_…`
secret unchanged, including its `whsec_` prefix; do not base64-decode it.
During rotation, set `CART_WEBHOOK_SECRET_PREVIOUS` to the old secret and
`CART_WEBHOOK_SECRET` to the new one, rebuild the configuration cache, then
remove the previous secret after a few minutes and rebuild the cache again.
Existing `CARTQUOTES_BASE_URL`, `CARTQUOTES_API_KEY`, and `CARTQUOTES_WEBHOOK_SECRET`
remain supported as legacy aliases. Run `php artisan config:clear` after local
config changes (rebuild the configuration cache on deployments).

Register **`https://shopandgo.ly/webhooks/cart-quotes`** in the Cart Quotes
Webhooks dashboard, without a trailing slash. Serve this exact host and path over
HTTPS on port 443 without a redirect, login, CSRF check, or proxy/WAF challenge.
`/hooks/cart-quotes` and `/hooks/cartquotes` remain accepted aliases.

The receiver captures raw bytes before JSON parsing and bypasses Laravel's JSON
input normalization on these paths. It requires `Webhook-Id`,
`Webhook-Timestamp`, `Webhook-Signature`, and `Webhook-Key-Version`, authenticates
HMAC-SHA256 over `timestamp + "." + raw_body` with constant-time comparison,
and rejects authentication failures with 401. Timestamps must be digits and
within ±300 seconds. Key version is required metadata; either configured secret
can authenticate during rotation regardless of version.

The callback expects `data.quoteId`, saves the event in the database inbox under
a unique `Webhook-Id`, and returns 200 without API calls, PDF generation, or
customer delivery. Duplicates return 200 without replacing the stored event.
Unknown quotes and `webhook.test` events are logged and acknowledged; future
event types are recorded and ignored. Failures/expiry/cancellations apply the
signed event data in the background. Successful events trigger an authenticated
GET for the full result. Provider totals, fees, and item money remain decimal
strings (including LYD's three decimals); the existing customer exchange-rate
and fee rules still determine the outgoing quotation price.

Deploy the additive `2026_10_09_000004_store_cart_quote_webhook_events` migration.
It retains existing receipts and adds their payload and a submission timestamp.

Apply the new migration and run the background processor:

```sh
php artisan migrate
php artisan cart-quotes:process  # one pass, useful for troubleshooting
php artisan schedule:work       # keep running during local development
```

Production needs Laravel's scheduler running every minute, for example a cron
entry `* * * * * cd /path/to/shipping && /path/to/php artisan schedule:run`.
The worker reads the full quote using `GET /v1/quotes/{id}` after callbacks,
checks pending quotes at their deadline when a callback is lost, and retries unsent WhatsApp replies.
The durable inbox runs through the scheduler, independently of `QUEUE_CONNECTION=sync`;
no queue worker or external API is invoked inside the webhook request.
Failures use increasing retry delays up to five minutes and honor a numeric
`Retry-After` header. `cart_quotes.last_error` records credential-free diagnostics.
Concurrent processing is serialized with database row locks. A provider timeout
after it already delivered a WhatsApp message can still cause a duplicate on
retry because the existing WhatsApp API does not expose a delivery idempotency key.

The configurable `CARTQUOTES_SHEIN_HOSTS` allowlist accepts those hosts and their
subdomains, including app share URLs and `shein.top` short links. Its defaults
use domains listed by [SHEIN's security center](https://security.shein.com/index.php)
and the short domain in [SHEIN's press release](https://prtimes.jp/a/?c=84888&f=d84888-36-3decf9617b443435d6d0fd3e9b65dc77.pdf&r=36).
Lookalike domains and URLs containing credentials or explicit ports are excluded.
The quote API determines whether the submitted URL contains a supported cart.

Run the integration and existing WhatsApp tests without sending real messages
or incurring quote fees:

```sh
php vendor/bin/phpunit --filter 'CartQuoteWebhookTest|CartQuoteIntegrationTest|AmazonQuoteIntegrationTest|WhatsAppWebhookTest'
```

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
