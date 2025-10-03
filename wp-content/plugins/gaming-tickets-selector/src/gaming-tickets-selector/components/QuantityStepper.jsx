// src/components/QuantityStepper.jsx
import { useState, useEffect } from '@wordpress/element';

export default function QuantityStepper({ min = 1, max = 10, onChange, value }) {
  const [qty, setQty] = useState(Math.min(Math.max(value ?? min, min), max));

  useEffect(() => {
    onChange?.(qty);
  }, [qty]);

  const dec = () => setQty((q) => Math.max(min, q - 1));
  const inc = () => setQty((q) => Math.min(max, q + 1));
  const onInput = (e) => {
    const n = parseInt(e.target.value, 10);
    if (!Number.isNaN(n)) setQty(Math.min(Math.max(n, min), max));
  };

  return (
    <div className="gts-qty">
      <button type="button" className="gts-qty-btn" onClick={dec} aria-label="Decrease">−</button>
      <input type="number" className="gts-qty-input" value={qty} min={min} max={max} onChange={onInput}/>
      <button type="button" className="gts-qty-btn" onClick={inc} aria-label="Increase">+</button>
      <span className="gts-qty-hint">Max {max}</span>
    </div>
  );
}
