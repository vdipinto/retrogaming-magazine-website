import DatePicker from 'react-datepicker';
import { dateToYMD } from '../utils/date';

export default function Calendar({
  monthYM,
  openToDate,
  includeDates,
  highlightDates,
  byDate,
  selected,
  onChange,
  onMonthChange,
}) {
  return (
    <DatePicker
      inline
      selected={selected}
      onChange={onChange}
      openToDate={openToDate}
      onMonthChange={(d) => onMonthChange(d)}
      includeDates={includeDates}
      highlightDates={highlightDates}
      wrapperClassName="gts-picker"
      calendarClassName="gts-calendar"
      dayClassName={(date) => {
        const items = byDate.get(dateToYMD(date));
        if (!items || items.length === 0) return '';
        let hasSoldout = false;
        let hasLimited = false;
        for (const it of items) {
          if (it.status === 'soldout') hasSoldout = true;
          else if (it.status === 'limited') hasLimited = true;
        }
        if (hasSoldout) return 'gts-day-soldout';
        if (hasLimited) return 'gts-day-limited';
        return 'gts-day-onsale';
      }}
      renderDayContents={(day, date) => {
        const items = byDate.get(dateToYMD(date));
        const first = items && items[0];
        const label = first?.title ? String(first.title) : null;
        return (
          <div title={label || ''} className="gts-cell">
            <span className="gts-daynum">{day}</span>
            {label ? <div className="gts-strip">{label}</div> : null}
          </div>
        );
      }}
      calendarStartDay={0}
    />
  );
}
