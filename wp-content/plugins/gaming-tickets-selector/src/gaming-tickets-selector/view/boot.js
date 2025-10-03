import { mountNode } from './index';

function init() {
  document.querySelectorAll('.gtx-root').forEach((node) => mountNode(node));
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
