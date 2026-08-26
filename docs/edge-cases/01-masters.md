# Module 01 — Masters (editable-lookup engine) · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: pervasive lookup engine, no "is this safe to remove?"

The editable-lookup engine (`lib/lookups.php`, `lookup_types` + `lookup_values`, the `lk_*` helpers)
is used **364 times across 94 files** via the values-if-present-else-const pattern. It is mature on
seeding (only-when-empty, so admin edits survive) and fallback (an emptied built-in list falls back
to the shipped constant). But it has **no usage counts and no data-quality detection**, and the only
admin removal path is a **hard DELETE** — deleting a value silently orphans every record that stored
it into a **raw code** (a single deleted value has no per-value fallback). The `active` flag is a
dead capability (shown but never toggled). An admin literally cannot tell whether a value is safe to
remove.

---

## 1. Built (additive, read-only, no hard control)

1. `lk_usage_map()` — a **curated, verified** map of lookup key → `[table, column, const-fallback]`
   for the lists that back a single clean code column on a real register (quote/inquiry status +
   source, lead source, the four vendor-profile lists). Conservative — only 1:1 mappings we're sure
   of.
2. `lk_value_usage($key, $code)` — how many records currently store that code; **null** for an
   untracked list (never a misleading "0 = safe").
3. **Value editor**: a **"Used by N records"** column and a stronger delete confirmation when N>0
   ("they will show a raw code if you remove it — remove anyway?"). **Advisory** — the delete is
   *warned*, never *blocked* (blocking would be a new hard control).
4. `lk_dangling_total()` + a **dangling-value integrity check** ("every stored dropdown code still
   exists in its list") and a **duplicate-code integrity check** in the `integrity_checks()`
   framework — both skip-safe, surfaced on the §7.11 Data Control console (and now the nightly
   integrity run from Module 29).
5. The **first tests** over lookup usage / dangling / duplicate detection.

---

## 2. Edge cases handled

1. An untracked list → usage null, no "Used by" column shown, delete keeps its plain confirm.
2. A value with an empty code → usage null (nothing to count).
3. A code valid only via the **const fallback** (lookup table empty) is not counted as dangling —
   `lk_options_or(key, const)` resolves it.
4. A table absent on this install → the dangling sum skips it (try/catch), never fails the check.
5. The duplicate-code check is generic (any list, any dup non-empty code); the dangling check is
   bounded to the curated map (safe, understood columns only).
6. Delete remains possible (advisory warning only) — no access change, no new hard control.

## 3. Guardrails (green)

`lk_options_or` and the whole seeding/fallback engine, the admin screens, the routes — all unchanged.
No new permission; no schema change; delete is warned, not blocked; nothing deleted. `test_reset_
masters`, `test_activity_quickadd` untouched.

## 4. Deferred (noted)

Exposing the dormant `active` flag as a "deactivate instead of delete" toggle — the natural next step,
but blocking-delete-when-in-use is a new hard control (needs a go). Broadening the usage map to
multi-table columns (e.g. SBU across many tables).

## 5. Tests

`tests/test_module01_masters.php` (16 assertions): the usage map is 1:1; usage counts per value and
returns null for an untracked list; a bogus stored code is counted as dangling; the duplicate-code and
dangling-value integrity checks fire; the value editor shows Used-by and warns (not blocks) on delete.
