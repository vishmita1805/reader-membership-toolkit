const test = require('node:test');
const assert = require('node:assert');
const core = require('../../assets/js/rmt-core.js');

test('variant assignment is stable for the same visitor', () => {
  const a = core.assignVariant('cta_headline_v1', 'visitor-123', ['control', 'impact']);
  const b = core.assignVariant('cta_headline_v1', 'visitor-123', ['control', 'impact']);
  assert.strictEqual(a, b);
});

test('variant assignment splits traffic roughly evenly', () => {
  const counts = { control: 0, impact: 0 };
  for (let i = 0; i < 10000; i++) counts[core.assignVariant('cta_headline_v1', 'v' + i, ['control', 'impact'])]++;
  assert.ok(Math.abs(counts.control - counts.impact) < 400, JSON.stringify(counts));
});

test('missing visitor falls back to control', () => {
  assert.strictEqual(core.assignVariant('x', '', ['control', 'impact']), 'control');
});

test('meter counts unique articles and resets each month', () => {
  let m = core.recordRead(null, 1, new Date('2026-11-03'));
  m = core.recordRead(m, 1, new Date('2026-11-04'));
  m = core.recordRead(m, 2, new Date('2026-11-05'));
  assert.deepStrictEqual(m.ids, ['1', '2']);
  m = core.recordRead(m, 3, new Date('2026-12-01'));
  assert.deepStrictEqual(m, { month: '2026-12', ids: ['3'] });
});

test('ask shows only after free articles, never to members or after dismiss', () => {
  const meter = { month: '2026-11', ids: ['1', '2', '3', '4'] };
  assert.strictEqual(core.shouldShowAsk(meter, 3, {}), true);
  assert.strictEqual(core.shouldShowAsk({ month: '2026-11', ids: ['1', '2', '3'] }, 3, {}), false);
  assert.strictEqual(core.shouldShowAsk(meter, 3, { isMember: true }), false);
  assert.strictEqual(core.shouldShowAsk(meter, 3, { dismissedThisSession: true }), false);
});

test('no event is built without consent', () => {
  assert.strictEqual(core.buildEvent('rmt_cta_view', { variant: 'control' }, 'denied'), null);
});

test('events drop anything that is not on the allow list', () => {
  const evt = core.buildEvent('rmt_checkout_start', { amount: 25, frequency: 'monthly', email: 'a@b.com', name: 'Jo' }, 'granted');
  assert.deepStrictEqual(evt, { event: 'rmt_checkout_start', amount: 25, frequency: 'monthly' });
});

test('amount validation', () => {
  assert.ok(core.validAmount(25));
  assert.ok(!core.validAmount(0));
  assert.ok(!core.validAmount(10001));
  assert.ok(!core.validAmount(2.5));
});
