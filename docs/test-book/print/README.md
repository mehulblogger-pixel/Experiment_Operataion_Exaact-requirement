# Printable test book

Builds the colour, print-ready PDF version of the test book: every test fully
expanded, Pass / Fail / Blocked boxes and a notes line on every step, a verdict
line per test, a contents list and a results tally per area.

1. `python3 print_book.py <site/book.json> <out>/html` — one HTML per volume
   (00 how to use & test users, then one per area) plus `manifest.json`.
2. `NODE_PATH=$(npm root -g) node render.js <out>/html <out>/pdf` — A4 PDFs with
   page numbers (Chromium via Playwright).
3. Join the volume PDFs into one complete book if wanted (any PDF tool).

No passwords are printed: the test-user table carries names, roles and scope only.
