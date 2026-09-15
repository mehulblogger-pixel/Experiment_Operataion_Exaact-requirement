# Phase 2 · M3 — Synonym & Matching Rules

## 1. The two rules that never bend

1. **An approved mapping always wins.** A similarity score can only ever produce
   a suggestion for a person to confirm. It never resolves and never merges.
2. **One term cannot mean two departments.** A conflicting mapping is refused
   with a message naming what the term already means.

## 2. Normalisation (§14)

Two forms are stored for every term. The original text is never destroyed.

| Form | Rule | `H.R.` | `R&D` | `Quality Control` |
|---|---|---|---|---|
| `term` | exactly as entered | `H.R.` | `R&D` | `Quality Control` |
| `term_norm` | lower-case; `&`/`+` → "and"; punctuation → separator; spaces collapsed | `h r` | `r and d` | `quality control` |
| `term_compact` | `term_norm` with spaces removed | `hr` | `randd` | `qualitycontrol` |

The compact form is why `H.R.`, `H R` and `HR` all reach the same department,
and why `Q.U.A.L.I.T.Y` finds `Quality`. Only the original is ever shown.

## 3. The six levels (§15)

| Level | Name | Meaning | Resolves? |
|---|---|---|---|
| 1 | EXACT | character-for-character an approved term | **yes** |
| 2 | NORMALISED | matches once case/punctuation are ignored | **yes** |
| 3 | SYNONYM | matches an approved synonym, alias, legacy or customer term | **yes** |
| 4 | SUGGESTED | strong deterministic candidate (≥85) | **no** — a person decides |
| 5 | WEAK | weaker candidate (70–84) | **no** — a person decides |
| 6 | UNKNOWN | nothing matched | **no** — a new value may be created |

Observed against the shipped master:

```
"Quality"          L1 EXACT       -> Quality
"QUALITY"          L2 NORMALISED  -> Quality
"  quality  "      L2 NORMALISED  -> Quality
"Q.U.A.L.I.T.Y"    L2 NORMALISED  -> Quality
"people and culture" L3 SYNONYM   -> Human Resources
"Enginering"       L4 SUGGESTED   -> (none) "Did you mean Engineering?"
"ZZZ Nonexistent"  L6 UNKNOWN     -> (none) "You can create a new one."
```

`vocab_resolve()` returns a value only at levels 1–3. Levels 4–6 return null,
by design — a mutation making a suggestion resolve fails the suite.

## 4. Scoring (§45 — deterministic, no AI)

| Condition | Score |
|---|---|
| one string is a prefix of the other | 90 |
| one contains the other | 80 |
| Levenshtein similarity ≥ 70% | that percentage |
| otherwise | not a candidate |

≥85 is level 4, 70–84 is level 5. At most five candidates are offered, best
first. No external service is called and no model is involved.

## 5. Ambiguity is never resolved automatically (§16)

`Quality` / `QA` / `QA/QC` / `Quality Assurance` / `Quality Control` may be one
department or several, depending on the customer. `NDT` may be a department or a
*discipline* — it already exists in the `trade` master.

So the engine **suggests** and the customer **decides**. The decision becomes
approved data with an author and a timestamp, not a migration guess.

## 6. Conflict (§21)

```
HR → Human Resources        (already approved)
HR → Finance                (attempted)

Refused: "HR" is already approved as a term for Human Resources.
```

The existing mapping is left exactly as it was. Re-adding the *same* mapping is
accepted quietly rather than reported as a clash.

## 7. Duplicate detection when creating (§32)

Before a new department is created, the name is matched and the code checked.

```
You entered:  Human Resource
Existing:     Human Resources

Held: "This looks like Human Resources — use that one, or confirm you want a
       separate department."
```

A **duplicate code is always refused**. A near-duplicate **name** is only held
back — a customer may legitimately need two similarly named departments, and
confirming creates it. Blocking a legitimate structure would be the worse error.

## 8. The audit trail (§44)

Each term records the original input, its normalised forms, its type, its
source, its status, any suggestion that was offered with its score, who approved
it and when. No personal data beyond the acting user's name is stored.
