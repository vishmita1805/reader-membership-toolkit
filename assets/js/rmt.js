/* Front end wiring: meter, A/B headline, consent aware tracking, and Stripe Checkout redirect. */
(function () {
  const cfg = window.RMT_CONFIG;
  const core = window.RMTCore;
  if (!cfg || !core) return;

  const store = {
    get(key, fallback) {
      try { const v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; } catch (e) { return fallback; }
    },
    set(key, value) {
      try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* storage blocked, meter simply resets */ }
    },
  };

  // Consent is read from a first party value set by the site's consent banner.
  function consent() {
    return store.get('rmt_consent', 'denied');
  }

  function track(name, params) {
    const evt = core.buildEvent(name, params, consent());
    if (!evt) return;
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(evt);
  }

  let visitorId = store.get('rmt_vid', null);
  if (!visitorId) {
    visitorId = (crypto.randomUUID && crypto.randomUUID()) || String(Math.random()).slice(2);
    store.set('rmt_vid', visitorId);
  }

  const variant = cfg.experiment ? core.assignVariant(cfg.experiment, visitorId, Object.keys(cfg.headlines)) : 'control';
  const base = { experiment: cfg.experiment || undefined, variant };

  const meter = core.recordRead(store.get('rmt_meter', null), cfg.articleId, new Date());
  store.set('rmt_meter', meter);

  if (new URLSearchParams(location.search).get('rmt_thanks') === '1') {
    store.set('rmt_member', true);
    track('rmt_donation_complete', base);
  }

  const el = document.querySelector('[data-rmt-cta]');
  if (!el) return;

  const dismissed = sessionStorage.getItem('rmt_dismissed') === '1';
  if (!core.shouldShowAsk(meter, cfg.freeArticles, { isMember: store.get('rmt_member', false), dismissedThisSession: dismissed })) return;

  el.querySelector('[data-rmt-headline]').textContent = cfg.headlines[variant] || cfg.headlines.control;
  el.hidden = false;
  track('rmt_cta_view', Object.assign({ article_count: meter.ids.length, article_id: cfg.articleId }, base));

  const errorEl = el.querySelector('[data-rmt-error]');
  el.querySelectorAll('[data-rmt-amount]').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const amount = Number(btn.dataset.rmtAmount);
      const frequency = el.querySelector('input[name="rmt_freq"]:checked').value;
      if (!core.validAmount(amount)) return;
      track('rmt_checkout_start', Object.assign({ amount, frequency }, base));
      btn.disabled = true;
      errorEl.hidden = true;
      try {
        const res = await fetch(cfg.checkoutUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ amount, frequency, variant }),
        });
        const data = await res.json();
        if (!res.ok || !data.url) throw new Error(data.message || 'Checkout failed');
        location.assign(data.url);
      } catch (err) {
        errorEl.textContent = err.message;
        errorEl.hidden = false;
        btn.disabled = false;
      }
    });
  });

  el.querySelector('[data-rmt-dismiss]').addEventListener('click', function () {
    sessionStorage.setItem('rmt_dismissed', '1');
    el.hidden = true;
    track('rmt_cta_dismiss', base);
  });
})();
