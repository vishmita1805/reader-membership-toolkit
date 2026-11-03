/*
 * Pure logic for the Reader Membership Toolkit, kept free of the DOM so it can be unit tested in Node.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.RMTCore = factory();
})(typeof self !== 'undefined' ? self : this, function () {
  // FNV-1a 32 bit hash. Small, fast, and stable across browsers.
  function hash(str) {
    let h = 0x811c9dc5;
    for (let i = 0; i < str.length; i++) {
      h ^= str.charCodeAt(i);
      h = Math.imul(h, 0x01000193) >>> 0;
    }
    return h >>> 0;
  }

  // Same visitor and experiment always get the same variant.
  function assignVariant(experiment, visitorId, variants) {
    if (!experiment || !visitorId || !variants || variants.length === 0) return 'control';
    return variants[hash(experiment + ':' + visitorId) % variants.length];
  }

  // Counts unique articles read in the current calendar month.
  function recordRead(meter, articleId, now) {
    const month = now.toISOString().slice(0, 7);
    const state = meter && meter.month === month ? { month, ids: meter.ids.slice() } : { month, ids: [] };
    const id = String(articleId);
    if (id && !state.ids.includes(id)) state.ids.push(id);
    return state;
  }

  function shouldShowAsk(meter, freeArticles, opts) {
    if (opts && (opts.isMember || opts.dismissedThisSession)) return false;
    return meter.ids.length > freeArticles;
  }

  // Events go to the dataLayer only when the reader has granted analytics consent.
  // Personal data such as emails or names is never allowed in event params.
  const ALLOWED_PARAMS = ['variant', 'experiment', 'amount', 'frequency', 'article_count', 'article_id'];
  function buildEvent(name, params, consent) {
    if (consent !== 'granted') return null;
    const clean = {};
    for (const key of ALLOWED_PARAMS) {
      if (params && params[key] !== undefined && params[key] !== null) clean[key] = params[key];
    }
    return Object.assign({ event: name }, clean);
  }

  function validAmount(value) {
    const n = Number(value);
    return Number.isInteger(n) && n >= 1 && n <= 10000;
  }

  return { hash, assignVariant, recordRead, shouldShowAsk, buildEvent, validAmount };
});
