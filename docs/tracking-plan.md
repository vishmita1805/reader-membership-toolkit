# Tracking plan

Every event follows the same rules:

* It is only sent after the reader grants analytics consent (`rmt_consent = "granted"`).
* It only carries parameters from an allow list. No names, emails, or payment details ever reach analytics.
* Names use `rmt_` plus a verb in snake case so they are easy to find in GA4 and BigQuery.

| Event | When it fires | Parameters | Question it answers |
|---|---|---|---|
| `rmt_cta_view` | The donation ask becomes visible | `experiment`, `variant`, `article_count`, `article_id` | How many readers see an ask, and after how many stories? |
| `rmt_checkout_start` | A reader picks an amount | `experiment`, `variant`, `amount`, `frequency` | Which amounts and frequencies do people choose? |
| `rmt_donation_complete` | The reader returns from Stripe with `?rmt_thanks=1` | `experiment`, `variant` | Did the ask turn into a gift? |
| `rmt_cta_dismiss` | The reader clicks "Maybe later" | `experiment`, `variant` | Is the ask annoying people? |

The Stripe webhook is the source of truth for money. Analytics events are for understanding the journey, so the two are compared in BigQuery rather than trusted alone.

## Journey

```
first visit -> reads free stories -> sees ask (rmt_cta_view)
            -> picks amount (rmt_checkout_start) -> Stripe Checkout
            -> returns (rmt_donation_complete) -> marked as member, no more asks
```

## Sending events to a CDP

The plugin pushes to `window.dataLayer`. From there, Google Tag Manager or a CDP such as RudderStack or Segment can forward the same events to GA4, Braze, Salesforce, and BigQuery, so every tool shares one definition of each event.
