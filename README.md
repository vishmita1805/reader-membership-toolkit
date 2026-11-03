# Reader Membership Toolkit

A WordPress plugin for nonprofit newsrooms that want more readers to become supporters, without being pushy and without giving up reader privacy.

It adds a donation ask after a reader has enjoyed a few free stories, takes one time or monthly gifts through Stripe Checkout, tracks the reader and donor journey in a consent aware way, and runs simple A/B tests on the ask so decisions come from evidence instead of guesswork.

**Live demo:** https://vishmita1805.github.io/reader-membership-toolkit/demo/ (checkout is mocked, no payment happens)

## What it does

| Feature | How |
|---|---|
| Soft meter | Counts unique stories read this month in the browser. After the free limit, the ask appears. Members and readers who clicked "Maybe later" are not asked again. |
| Donations | `[rmt_donate]` shortcode, or added automatically after posts. A REST endpoint creates a Stripe Checkout Session for one time or monthly gifts. |
| Webhook | Verifies the `Stripe-Signature` header (HMAC plus timestamp check to block replays) and stores each gift once, so Stripe retries never double count. |
| Event tracking | Pushes `rmt_cta_view`, `rmt_checkout_start`, `rmt_donation_complete`, and `rmt_cta_dismiss` to the `dataLayer` for GA4, GTM, or a CDP. See the [tracking plan](docs/tracking-plan.md). |
| A/B testing | Each visitor is hashed into a stable variant. The variant is sent with every event and stored with each gift. |
| Analysis | [`sql/experiment_results.sql`](sql/experiment_results.sql) reads the GA4 BigQuery export and reports conversion by variant. |
| Privacy | No events without consent, and an allow list keeps names, emails, and payment details out of analytics. Stripe keys live in `wp-config.php`, not the database. |

## Setup

1. Copy this folder into `wp-content/plugins/` and activate **Reader Membership Toolkit**.
2. Add your Stripe test keys to `wp-config.php`:
   ```php
   define( 'RMT_STRIPE_SECRET_KEY', 'sk_test_...' );
   define( 'RMT_STRIPE_WEBHOOK_SECRET', 'whsec_...' );
   ```
3. In Stripe, point a webhook at `https://your-site/wp-json/rmt/v1/webhook` for `checkout.session.completed`.
4. Under **Settings > Reader Membership**, set the free story limit, copy, suggested amounts, and your GA4 Measurement ID.
5. Have your consent banner set `localStorage.rmt_consent = "granted"` when a reader opts in.

## Project layout

```
reader-membership-toolkit.php     plugin bootstrap
includes/
  class-rmt-settings.php          admin settings and input sanitizing
  class-rmt-cta.php               shortcode, markup, and script loading
  class-rmt-donations.php         Stripe Checkout and webhook handling
  class-rmt-experiments.php       stable A/B assignment
assets/js/rmt-core.js             pure logic (meter, variants, events), unit tested
assets/js/rmt.js                  browser wiring
docs/tracking-plan.md             event definitions
sql/experiment_results.sql        BigQuery analysis
demo/index.html                   runs without WordPress
tests/                            PHP and JS tests
```

## Tests

```bash
php tests/php/run.php   # 15 tests: amounts, Stripe params, webhook signatures, A/B split
npm test                # 8 tests: meter, variants, consent, event allow list
```

Both run in GitHub Actions on every push.

## What I would add next

* Send events server side to a CDP (RudderStack or Segment) so ad blockers do not hide donors from the funnel.
* Sync completed gifts to Salesforce through the Stripe webhook.
* A small reader account page to manage a monthly gift.
* A WordPress VIP compatible caching review, since the ask is decided in the browser and pages stay fully cacheable.

## License

MIT
