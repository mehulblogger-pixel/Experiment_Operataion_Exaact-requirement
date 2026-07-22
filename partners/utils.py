"""India-specific helpers for business-partner data."""

import re

GST_STATE_CODES = {
    "01": "Jammu & Kashmir", "02": "Himachal Pradesh", "03": "Punjab",
    "04": "Chandigarh", "05": "Uttarakhand", "06": "Haryana", "07": "Delhi",
    "08": "Rajasthan", "09": "Uttar Pradesh", "10": "Bihar", "11": "Sikkim",
    "12": "Arunachal Pradesh", "13": "Nagaland", "14": "Manipur", "15": "Mizoram",
    "16": "Tripura", "17": "Meghalaya", "18": "Assam", "19": "West Bengal",
    "20": "Jharkhand", "21": "Odisha", "22": "Chhattisgarh", "23": "Madhya Pradesh",
    "24": "Gujarat", "25": "Daman & Diu", "26": "Dadra & Nagar Haveli",
    "27": "Maharashtra", "28": "Andhra Pradesh (Old)", "29": "Karnataka",
    "30": "Goa", "31": "Lakshadweep", "32": "Kerala", "33": "Tamil Nadu",
    "34": "Puducherry", "35": "Andaman & Nicobar", "36": "Telangana",
    "37": "Andhra Pradesh", "38": "Ladakh",
}

GSTIN_RE = re.compile(r"^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$")
PAN_RE = re.compile(r"^[A-Z]{5}[0-9]{4}[A-Z]{1}$")


def clean_gstin(value):
    return (value or "").strip().upper().replace(" ", "")


def pan_from_gstin(gstin):
    g = clean_gstin(gstin)
    if len(g) >= 12:
        pan = g[2:12]
        if PAN_RE.match(pan):
            return pan
    return ""


def state_from_gstin(gstin):
    g = clean_gstin(gstin)
    return GST_STATE_CODES.get(g[:2], "") if len(g) >= 2 else ""


def is_valid_gstin(gstin):
    return bool(GSTIN_RE.match(clean_gstin(gstin)))


def is_valid_pan(pan):
    return bool(PAN_RE.match((pan or "").strip().upper()))


def normalize_name(name):
    """For duplicate detection: drop M/s., punctuation, ownership words, case."""
    n = (name or "").lower()
    n = re.sub(r"^m/?s\.?\s+", "", n)
    n = re.sub(r"[^a-z0-9 ]", " ", n)
    n = re.sub(r"\b(private|pvt|limited|ltd|llp|company|co)\b", " ", n)
    return re.sub(r"\s+", " ", n).strip()


def short_token(name):
    n = normalize_name(name)
    first = (n.split() or ["PARTNER"])[0]
    return re.sub(r"[^A-Z0-9]", "", first.upper())[:8] or "PARTNER"
