-- Conversion by A/B variant from the GA4 BigQuery export.
-- Replace `your_project.analytics_123456789` with your GA4 export dataset.
-- A reader converts when they fire rmt_donation_complete after seeing the ask.

WITH events AS (
  SELECT
    user_pseudo_id,
    event_name,
    (SELECT value.string_value FROM UNNEST(event_params) WHERE key = 'variant')    AS variant,
    (SELECT value.string_value FROM UNNEST(event_params) WHERE key = 'experiment') AS experiment
  FROM `your_project.analytics_123456789.events_*`
  WHERE _TABLE_SUFFIX BETWEEN FORMAT_DATE('%Y%m%d', DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY))
                          AND FORMAT_DATE('%Y%m%d', CURRENT_DATE())
    AND event_name IN ('rmt_cta_view', 'rmt_checkout_start', 'rmt_donation_complete')
),
per_reader AS (
  SELECT
    user_pseudo_id,
    ANY_VALUE(variant) AS variant,
    LOGICAL_OR(event_name = 'rmt_cta_view')          AS saw_ask,
    LOGICAL_OR(event_name = 'rmt_checkout_start')    AS started_checkout,
    LOGICAL_OR(event_name = 'rmt_donation_complete') AS donated
  FROM events
  WHERE experiment = 'cta_headline_v1'
  GROUP BY user_pseudo_id
)
SELECT
  variant,
  COUNTIF(saw_ask)                                            AS readers_who_saw_ask,
  COUNTIF(saw_ask AND started_checkout)                       AS started_checkout,
  COUNTIF(saw_ask AND donated)                                AS donors,
  SAFE_DIVIDE(COUNTIF(saw_ask AND donated), COUNTIF(saw_ask)) AS conversion_rate
FROM per_reader
GROUP BY variant
ORDER BY variant;
