/**
 * BasketApp.jsx
 *
 * Now shows pricing:
 *  - Each line displays unit price and line total.
 *  - Footer shows cart subtotal (from server totals).
 * Server is the source of truth; we only format and render.
 */

import { useEffect, useMemo, useState } from '@wordpress/element';
import {
  Button,
  Flex,
  FlexItem,
  Spinner,
  Notice,
  Card,
  CardBody,
  Tooltip,
} from '@wordpress/components';

export default function BasketApp({ restNonce }) {
  // --------------------------------------
  // STATE
  // --------------------------------------
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState('');
  const [raw, setRaw] = useState(null);

  const [busy, setBusy] = useState(false);     // whole-basket ops
  const [busyId, setBusyId] = useState(null);  // per-row ops

  // Business rule: maximum quantity per line
  const MAX_QTY = 2;

  // --------------------------------------
  // Fetch helpers
  // --------------------------------------
  const jsonHeaders = useMemo(() => {
    const h = { 'Content-Type': 'application/json' };
    if (restNonce) h['X-WP-Nonce'] = restNonce;
    return h;
  }, [restNonce]);

  // Never put headers in commonOpts (to avoid overwriting jsonHeaders)
  const commonOpts = useMemo(() => ({ credentials: 'same-origin' }), []);

  // Non-JSON requests (GET/DELETE) can still carry the nonce header
  const nonceHeader = useMemo(
    () => (restNonce ? { 'X-WP-Nonce': restNonce } : {}),
    [restNonce]
  );

  // Currency + formatter (prefer server totals currency, then first line’s)
  const currency = raw?.totals?.currency
    || items.find(i => i?.currency)?.currency
    || 'EUR';

  const money = useMemo(
    () => new Intl.NumberFormat(undefined, { style: 'currency', currency }),
    [currency]
  );

  // --------------------------------------
  // API calls
  // --------------------------------------
  async function load() {
    try {
      setLoading(true);
      setErr('');
      const res = await fetch('/wp-json/gaming-tickets/v1/cart', {
        ...commonOpts,
        headers: nonceHeader,
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      setRaw(json); // contains { items, totals }
      setItems(Array.isArray(json.items) ? json.items : []);
    } catch (e) {
      setErr(String(e.message || e));
    } finally {
      setLoading(false);
    }
  }

  async function clearAll() {
    if (!window.confirm('Clear all items from your basket?')) return;
    setBusy(true);
    try {
      const res = await fetch('/wp-json/gaming-tickets/v1/cart', {
        method: 'DELETE',
        ...commonOpts,
        headers: nonceHeader,
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch (e) {
      setErr(`Failed to clear: ${String(e.message || e)}`);
    } finally {
      setBusy(false);
    }
  }

  async function setQty(lineId, nextQty) {
    setBusyId(lineId);
    try {
      const res = await fetch(
        `/wp-json/gaming-tickets/v1/cart/items/${encodeURIComponent(lineId)}`,
        {
          method: 'PATCH',
          ...commonOpts,
          headers: jsonHeaders, // includes Content-Type + optional nonce
          body: JSON.stringify({ quantity: Math.max(0, nextQty) }),
        }
      );
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch (e) {
      setErr(`Failed to update quantity: ${String(e.message || e)}`);
    } finally {
      setBusyId(null);
    }
  }

  function decrementOne(it) {
    setQty(it.lineId, it.quantity - 1); // qty:1 -> server removes line on 0
  }

  function incrementOne(it) {
    if (it.quantity >= MAX_QTY) return;
    setQty(it.lineId, it.quantity + 1);
  }

  // --------------------------------------
  // Effects
  // --------------------------------------
  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // --------------------------------------
  // RENDER
  // --------------------------------------
  const subtotal = typeof raw?.totals?.subtotal === 'number'
    ? raw.totals.subtotal
    : items.reduce((n, i) => n + (Number(i.price || 0) * Number(i.quantity || 0)), 0);

  return (
    <Card>
      <CardBody>
        <h3 style={{ margin: 0 }}>Your Basket</h3>

        {/* Error banner */}
        {err ? (
          <Notice status="error" isDismissible={false} style={{ marginTop: 12 }}>
            {err}
          </Notice>
        ) : null}

        {/* Loading / Empty / List */}
        {loading ? (
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 12 }}>
            <Spinner /> <span>Loading basket…</span>
          </div>
        ) : items.length === 0 ? (
          <div style={{ marginTop: 12 }}>
            <p style={{ margin: '8px 0' }}>Your basket is empty.</p>
            <p style={{ margin: 0, color: '#646970', fontSize: 13 }}>
              Use <strong>+</strong> and <strong>−</strong> to adjust quantities. The maximum per ticket is 2.
            </p>
          </div>
        ) : (
          <>
            <ul style={{ listStyle: 'none', padding: 0, margin: '12px 0 0' }}>
              {items.map((it) => {
                const isBusy = busyId === it.lineId;
                const atMax = it.quantity >= MAX_QTY;
                const unit = Number(it.price || 0);
                const lineTotal = unit * Number(it.quantity || 0);

                return (
                  <li key={it.lineId} style={{ padding: '12px 0', borderTop: '1px solid #eee' }}>
                    <div style={{ fontWeight: 600 }}>{it.instanceTitle}</div>
                    <div style={{ fontSize: 12, color: '#666' }}>
                      {it.date} • {new Date(it.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </div>

                    {/* Unit price + line total */}
                    <div style={{ marginTop: 4, fontSize: 13 }}>
                      {money.format(unit)} each · <strong>Line total: {money.format(lineTotal)}</strong>
                    </div>

                    {/* Qty controls */}
                    <div style={{ marginTop: 8 }}>
                      <Flex align="center" gap={8}>
                        <FlexItem>
                          <Tooltip text={it.quantity > 1 ? 'Remove one' : 'Remove last item'}>
                            <Button
                              variant="secondary"
                              onClick={() => decrementOne(it)}
                              disabled={isBusy}
                              aria-label={it.quantity > 1 ? `Remove one ${it.instanceTitle}` : `Remove last ${it.instanceTitle}`}
                            >
                              −
                            </Button>
                          </Tooltip>
                        </FlexItem>

                        <FlexItem>
                          <span
                            aria-live="polite"
                            style={{ minWidth: 70, display: 'inline-block', textAlign: 'center' }}
                          >
                            Qty: {isBusy ? '…' : it.quantity}
                          </span>
                        </FlexItem>

                        <FlexItem>
                          <Tooltip text={atMax ? 'Maximum 2 reached' : 'Add one'}>
                            <Button
                              variant="secondary"
                              onClick={() => incrementOne(it)}
                              disabled={isBusy || atMax}
                              aria-label={`Add one ${it.instanceTitle}`}
                            >
                              +
                            </Button>
                          </Tooltip>
                        </FlexItem>

                        {isBusy ? (
                          <FlexItem>
                            <Spinner />
                          </FlexItem>
                        ) : null}
                      </Flex>
                    </div>
                  </li>
                );
              })}
            </ul>

            {/* Subtotal */}
            <div style={{ marginTop: 12, borderTop: '1px solid #eee', paddingTop: 12 }}>
              <div style={{ fontWeight: 600 }}>
                Subtotal: {money.format(Number(subtotal || 0))}
              </div>
              {/* If you later return fees/tax/grandTotal from the API, render them here. */}
            </div>
          </>
        )}

        {/* Clear basket */}
        {!loading && items.length > 0 ? (
          <Flex gap={8} style={{ marginTop: 16 }}>
            <FlexItem>
              <Button variant="secondary" isDestructive onClick={clearAll} disabled={busy}>
                {busy ? 'Clearing…' : 'Clear basket'}
              </Button>
            </FlexItem>
          </Flex>
        ) : null}

        {/* Debug: raw payload (handy while developing) */}
        {raw ? (
          <details style={{ marginTop: 14 }}>
            <summary>Debug: API JSON</summary>
            <pre
              style={{
                whiteSpace: 'pre-wrap',
                wordBreak: 'break-word',
                marginTop: 8,
                padding: 8,
                background: '#f6f7f7',
                borderRadius: 4,
              }}
            >
              {JSON.stringify(raw, null, 2)}
            </pre>
          </details>
        ) : null}
      </CardBody>
    </Card>
  );
}
