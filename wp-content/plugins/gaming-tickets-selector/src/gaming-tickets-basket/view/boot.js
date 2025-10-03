console.log('[GTS basket] boot loaded');

import { createRoot } from 'react-dom/client';
import BasketApp from '../components/BasketApp';

function init() {
  document.querySelectorAll('.gts-basket-root').forEach((node) => {
    const restNonce = node.dataset.restNonce || '';
    const root = createRoot(node);
    root.render(<BasketApp restNonce={restNonce} />);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
