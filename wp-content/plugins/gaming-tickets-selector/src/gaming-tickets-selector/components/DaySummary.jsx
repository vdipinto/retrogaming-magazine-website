// src/gaming-tickets-selector/components/DaySummary.jsx
import { useState, useEffect } from '@wordpress/element';
import QuantityStepper from './QuantityStepper';

const FALLBACK_MAX = 10; // UI fallback if we don't know stock

export default function DaySummary({
  date,
  instancesForDay = [],
  eventId,
  dispatchTarget, // kept for compatibility
  maxPerOrder, // optional number (e.g., 2)
}) {
  // --- derive default index (highest remaining first, else first) ---
  function computeDefaultIdx(list) {
    if (!list.length) return -1;
    const sorted = [...list].sort(
      (a, b) => (b?.remaining ?? 0) - (a?.remaining ?? 0)
    );
    const idx = list.findIndex((i) => i?.id === sorted[0]?.id);
    return idx === -1 ? 0 : idx;
  }

  const [instanceIdx, setInstanceIdx] = useState(computeDefaultIdx(instancesForDay));
  useEffect(() => {
    setInstanceIdx(computeDefaultIdx(instancesForDay));
  }, [instancesForDay]);

  const instance = instancesForDay[instanceIdx] || null;

  // Limits
  const stockMax =
    instance?.remaining != null ? Math.max(1, Number(instance.remaining) || 1) : FALLBACK_MAX;
  const effectiveMax =
    typeof maxPerOrder === 'number' && maxPerOrder > 0
      ? Math.min(stockMax, maxPerOrder)
      : stockMax;

  // Qty state (reclamp when limits change)
  const [qty, setQty] = useState(1);
  useEffect(() => {
    setQty((q) => Math.min(Math.max(1, q), effectiveMax));
  }, [effectiveMax]);

  if (!date) return null;

  const prettyDate = date.toLocaleDateString(undefined, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
  const selectedDateISO = date.toISOString().slice(0, 10); // "YYYY-MM-DD"

  // Nice label for the select
  function formatLine(it) {
    const time = new Date(it.start).toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit',
    });
    const stockLabel =
      it.status === 'soldout'
        ? 'Sold out'
        : it.remaining != null
        ? `${it.remaining} tickets left`
        : 'Limited';
    return `${time} - ${it.title || 'Ticket'} - ${stockLabel}`;
  }

  // --- price / total from payload ---
  const unitPrice = Number(instance?.price ?? 0);
  const currency = instance?.currency || null; // don’t default to EUR, show raw
  const totalPrice = unitPrice * qty;
  const moneyFmt = currency
    ? new Intl.NumberFormat(undefined, { style: 'currency', currency })
    : null;

  async function handleAddToBasket() {
    if (!instance) return;
    const safeQty = Math.min(Math.max(1, qty), effectiveMax);

    const payload = {
      type: 'ticket.add',
      eventId: eventId ?? null,
      instanceId: instance.id,
      instanceTitle: instance.title ?? null,
      date: selectedDateISO,
      start: instance.start,
      quantity: safeQty,
      itemId: String(instance.itemId),
    };

    window.dispatchEvent(new CustomEvent('gts:add-to-basket', { detail: payload }));

    const root = document.querySelector('.gtx-root');
    const nonce = root?.dataset?.restNonce;

    try {
      const res = await fetch('/wp-json/gaming-tickets/v1/cart/items', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(nonce ? { 'X-WP-Nonce': nonce } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        const text = await res.text();
        throw new Error(`HTTP ${res.status} – ${text}`);
      }
      window.location.assign('/basket/');
    } catch (e) {
      alert('Sorry, something went wrong adding to your basket.');
      console.error('[GTS] add-to-basket failed', e);
    }
  }

  return (
    <div className="gts-summary">
      <h4 className="gts-summary-title">Selected date</h4>
      <div className="gts-summary-date">{prettyDate}</div>

      {instancesForDay.length === 0 && (
        <div className="gts-summary-empty">No instances available on this date.</div>
      )}

      {instancesForDay.length > 1 && (
        <label className="gts-field" style={{ display: 'block', marginTop: 8 }}>
          <span className="gts-label" style={{ display: 'block', marginBottom: 6 }}>
            Start time / instance
          </span>
          <select
            className="gts-select"
            value={instanceIdx}
            onChange={(e) => setInstanceIdx(parseInt(e.target.value, 10))}
          >
            {instancesForDay.map((it, idx) => (
              <option key={it.id || idx} value={idx} disabled={it.status === 'soldout'}>
                {formatLine(it)}
              </option>
            ))}
          </select>
        </label>
      )}

      {/* Header row */}
      <div
        className="gts-line-head"
        style={{
          display: 'grid',
          gridTemplateColumns: '1fr auto',
          fontSize: 12,
          letterSpacing: '0.08em',
          color: '#8c8f94',
          padding: '8px 0',
          borderTop: '1px solid #e2e4e7',
          marginTop: 10,
        }}
      >
        <span>TYPE</span>
        <span>QTY</span>
      </div>

      {/* Product row */}
      <div
        className="gts-line"
        style={{
          display: 'grid',
          gridTemplateColumns: '1fr auto',
          alignItems: 'center',
          padding: '10px 0 14px',
          borderBottom: '1px solid #e2e4e7',
          gap: 12,
        }}
      >
        <div className="gts-line-type" style={{ fontSize: 14, color: '#23282d' }}>
          {instance?.title || 'Ticket'}
          {instance && unitPrice > 0 && currency && (
            <span className="gts-line-price" style={{ color: '#666', marginLeft: 8 }}>
              @ {moneyFmt?.format(unitPrice)}
            </span>
          )}
        </div>

        <div className="gts-line-qty" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <QuantityStepper min={1} max={effectiveMax} value={qty} onChange={setQty} />
        </div>
      </div>

      {typeof maxPerOrder === 'number' && maxPerOrder > 0 && (
        <div className="gts-qty-hint" style={{ color: '#666', fontSize: 12, marginTop: 6 }}>
          Max {maxPerOrder} per order
        </div>
      )}

      <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginTop: 12 }}>
        {unitPrice > 0 && currency && (
          <div style={{ fontWeight: 600 }}>
            Total: {moneyFmt?.format(totalPrice)}
          </div>
        )}
        <button
          type="button"
          className="gts-add-btn"
          disabled={!instance || instance.status === 'soldout'}
          onClick={handleAddToBasket}
          style={{
            marginLeft: 'auto',
            padding: '8px 14px',
            borderRadius: 4,
            border: '1px solid #dcdcde',
            background: '#fff',
            cursor: !instance || instance.status === 'soldout' ? 'not-allowed' : 'pointer',
          }}
        >
          Add to basket
        </button>
      </div>

      {/* Debug payload: raw API instances */}
      <details style={{ marginTop: 12 }}>
        <summary style={{ cursor: 'pointer' }}>Debug: raw instances</summary>
        <pre
          style={{
            whiteSpace: 'pre-wrap',
            wordBreak: 'break-word',
            marginTop: 8,
            padding: 8,
            background: '#f6f7f7',
            borderRadius: 4,
            fontSize: 12,
          }}
        >
{JSON.stringify(
  {
    selectedIndex: instanceIdx,
    selectedInstanceId: instance?.id ?? null,
    instancesForDay, // unmodified from API call, includes currency
  },
  null,
  2
)}
        </pre>
      </details>
    </div>
  );
}
