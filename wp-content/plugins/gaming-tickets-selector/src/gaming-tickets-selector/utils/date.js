// src/utils/date.js
export const pad = (n) => (n < 10 ? `0${n}` : String(n));
export const ymToDate = (ym) => new Date(`${ym}-01T00:00:00Z`);
export const dateToYM = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
export const dateToYMD = (d) =>
  `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

// normalize an ISO timestamp to local midnight (DatePicker comparison friendliness)
export const isoToLocalMidnight = (iso) => {
  const utc = new Date(iso);
  return new Date(utc.getFullYear(), utc.getMonth(), utc.getDate());
};