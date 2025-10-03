import { createRoot } from 'react-dom/client';
import TicketsWidget from '../components/TicketsWidget';
import { pad } from '../utils/date';

export function mountNode(node) {
    const endpoint = node.dataset.restEndpoint || '';
    const initialMonth = node.dataset.initialMonth || '';
    const eventId = node.dataset.eventId || '';
    const maxPerOrder = parseInt(node.dataset.maxPerOrder || '0', 10) || undefined;
  
    const now = new Date();
    const month =
      initialMonth || `${now.getUTCFullYear()}-${pad(now.getUTCMonth() + 1)}`;
  
    const root = createRoot(node);
    root.render(
      <TicketsWidget
        endpoint={endpoint}
        month={month}
        eventId={eventId}
        maxPerOrder={maxPerOrder}
      />
    );
  }