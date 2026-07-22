'use strict';
// India GST helpers: derive PAN + state from a GSTIN, and validate formats.

const GST_STATE_CODES = {
  '01': 'Jammu & Kashmir', '02': 'Himachal Pradesh', '03': 'Punjab', '04': 'Chandigarh',
  '05': 'Uttarakhand', '06': 'Haryana', '07': 'Delhi', '08': 'Rajasthan',
  '09': 'Uttar Pradesh', '10': 'Bihar', '11': 'Sikkim', '12': 'Arunachal Pradesh',
  '13': 'Nagaland', '14': 'Manipur', '15': 'Mizoram', '16': 'Tripura', '17': 'Meghalaya',
  '18': 'Assam', '19': 'West Bengal', '20': 'Jharkhand', '21': 'Odisha',
  '22': 'Chhattisgarh', '23': 'Madhya Pradesh', '24': 'Gujarat', '25': 'Daman & Diu',
  '26': 'Dadra & Nagar Haveli', '27': 'Maharashtra', '28': 'Andhra Pradesh (Old)',
  '29': 'Karnataka', '30': 'Goa', '31': 'Lakshadweep', '32': 'Kerala', '33': 'Tamil Nadu',
  '34': 'Puducherry', '35': 'Andaman & Nicobar', '36': 'Telangana', '37': 'Andhra Pradesh',
  '38': 'Ladakh',
};

const GSTIN_RE = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
const PAN_RE = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;

const cleanGstin = (v) => (v || '').trim().toUpperCase().replace(/\s+/g, '');

function panFromGstin(gstin) {
  const g = cleanGstin(gstin);
  if (g.length >= 12) {
    const pan = g.slice(2, 12);
    if (PAN_RE.test(pan)) return pan;
  }
  return '';
}

function stateFromGstin(gstin) {
  const g = cleanGstin(gstin);
  return g.length >= 2 ? (GST_STATE_CODES[g.slice(0, 2)] || '') : '';
}

const isValidGstin = (g) => GSTIN_RE.test(cleanGstin(g));

function normalizeName(name) {
  let n = (name || '').toLowerCase();
  n = n.replace(/^m\/?s\.?\s+/, '');
  n = n.replace(/[^a-z0-9 ]/g, ' ');
  n = n.replace(/\b(private|pvt|limited|ltd|llp|company|co)\b/g, ' ');
  return n.replace(/\s+/g, ' ').trim();
}

function shortToken(name) {
  const first = (normalizeName(name).split(' ')[0] || 'PARTNER');
  return (first.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8)) || 'PARTNER';
}

module.exports = {
  cleanGstin, panFromGstin, stateFromGstin, isValidGstin, normalizeName, shortToken,
};
