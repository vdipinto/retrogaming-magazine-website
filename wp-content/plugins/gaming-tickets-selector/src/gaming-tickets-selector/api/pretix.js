// DEFAULT export
export default async function fetchMonthAvailability({ endpoint, monthYM, eventId }) {
    const url = `${endpoint}?month=${encodeURIComponent(monthYM)}${
      eventId ? `&event=${encodeURIComponent(eventId)}` : ''
    }`;
  
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`HTTP ${res.status} on ${url}`);
    return res.json(); // { eventId, month, instances[], updatedAt }
  }