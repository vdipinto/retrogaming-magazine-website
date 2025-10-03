import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import Calendar from './Calendar';
import DaySummary from './DaySummary';
import fetchMonthAvailability from '../api/pretix';
import { ymToDate, dateToYM, dateToYMD, isoToLocalMidnight } from '../utils/date';

export default function TicketsWidget({ endpoint, month, eventId, maxPerOrder }) {
  const [monthYM, setMonthYM] = useState(month);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState(null);
  const [data, setData]       = useState(null); // { eventId, month, instances[], updatedAt }
  const [selected, setSelected] = useState(null);

  const dispatchTarget = useRef(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true); setError(null); setData(null);
        const json = await fetchMonthAvailability({ endpoint, monthYM, eventId });
        if (!cancelled) { setData(json); setLoading(false); }
      } catch (e) {
        if (!cancelled) { setError(String(e)); setLoading(false); }
      }
    })();
    return () => { cancelled = true; };
  }, [endpoint, monthYM, eventId]);

  const { includeDates, highlightDates, byDate } = useMemo(() => {
    const inst = data?.instances || [];
    const available = [];
    const limited   = [];
    const map = new Map();

    for (const i of inst) {
      const local = isoToLocalMidnight(i.start);
      const key   = dateToYMD(local);
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(i);
      if (i.status === 'soldout') continue;
      if (i.status === 'limited') limited.push(local);
      available.push(local);
    }

    return {
      includeDates: available,
      highlightDates: {
        'react-datepicker__day--limited': limited,
        'react-datepicker__day--onsale': available,
      },
      byDate: map,
    };
  }, [data]);


  // we use useMemo to memoize the instancesForSelected to avoid re-rendering the component when the selected date changes. This means that the component will only re-render when the selected date changes, and not when the byDate map changes.
  const instancesForSelected = useMemo(() => {
    if (!selected) return [];
    return byDate.get(dateToYMD(selected)) || [];
  }, [selected, byDate]);

  if (loading) return <div className="gts-message">Loading availability…</div>;
  if (error)   return <div className="gts-error"><strong>Failed to load</strong><div>{error}</div></div>;

  return (
    <div className="gts-widget" ref={dispatchTarget}>
      <div className="gts-header">
        <strong>{data?.eventId ?? '—'}</strong> — {data?.month ?? '—'}
      </div>

      <Calendar
        monthYM={monthYM}
        openToDate={ymToDate(monthYM)}
        includeDates={includeDates}
        highlightDates={highlightDates}
        byDate={byDate}
        selected={selected}
        onChange={setSelected}
        onMonthChange={(d) => setMonthYM(dateToYM(d))}
      />

      <DaySummary
        date={selected}
        instancesForDay={instancesForSelected}
        eventId={data?.eventId}
        dispatchTarget={dispatchTarget}
        maxPerOrder={maxPerOrder} // <- optional number (e.g., 2). If undefined, no per-order cap on UI side
      />
    </div>
  );
}
