# Phase 3 · M3 CORRECTION #10 — AUDIT (before any code was written)

## 1 · What V1 actually was

Correction #9 made observation **read-only**, which was right, and then answered a
**three-valued** question with a boolean:

```
column=false  column_error=''  index_error='cond_key index: the column is unavailable'
```

— with **no attempt having occurred**, against a column that one ordinary call
would create. Two separate faults in one line:

1. a structure that is merely **absent** is reported like one that **failed**;
2. the index's message asserts *"the column is unavailable"* — a claim about a
   failure that has not happened and may never happen.

`act_optional_state()` exists for diagnosis. *Is it broken, or has nobody asked
yet?* is the only question it is ever asked, and it could not answer it.

## 2 · The state model (§1)

| | Meaning | How it is established |
|---|---|---|
| `READY` | the structure is there | a cheap metadata read |
| `FAILED` | attempted, and could not be made | **recorded evidence of a real attempt** |
| `NOT_ATTEMPTED` | nothing has tried | attempt count is zero |

Derived **independently** for the column and the index (§5), preserving correction
#9's separation.

**FAILED is never inferred from absence** (§1, §10). It requires the ledger to show
an attempt.

## 3 · Where the evidence of an attempt comes from (§10)

Correction #9 kept the attempt counter in a `static` **inside** the retry logic. It
existed, but nothing could see it — which is why "attempted" and "never attempted"
were indistinguishable from outside.

The smallest additive change: the counter becomes observable state, keyed per
**workspace epoch**, written only from inside a real attempt:

```php
function act_opt_rec($which)                  // read — anyone, including an observer
function act_opt_note($which, $tries, $error) // write — only from inside an attempt
```

Per-epoch matters: a different company's database has not attempted anything merely
because this one failed. Mutation `V1-M9` exists to hold that.

No second migration engine; no change to the bounded retry policy; no change to
core audit behaviour.

## 4 · Messages (§4)

`Ready` · `Not attempted` · `Failed: <recorded reason>`.

The string *"the column is unavailable"* is gone. Nothing now describes a failure
that has not been observed.

## 5 · One existing assertion was WRONG and had to be corrected

`C9.6` required:

```php
t_ok(strpos($stC['index_error'], 'column is unavailable') !== false, …);
```

at a point where **nothing had attempted anything**. That is V1's defect written
into a test — an assertion demanding a claim about a failure that had not occurred.
It now requires the honest answer (`NOT_ATTEMPTED` for both) and, additionally,
that the index does **not** assert a column failure that never happened.

Correcting a test that encoded the defect is not weakening it: the replacement is
strictly more specific about what must be true.

## 6 · How the new tests avoid the trap they test for (§10)

Every `FAILED` assertion is preceded by an attempt **proved to have happened**
(`column_tries > 0`); every `NOT_ATTEMPTED` by an attempt count **proved to be
zero**. Neither is inferred from the absence of the other — which is the inference
V1 was.

`C10.2` closes the loop: having asserted `NOT_ATTEMPTED`, it then makes one real
attempt and shows it **succeeds** — so `FAILED` would have been a lie about a
structure one call from existing.

## 7 · Scope (§13)

V1 only. S2, S3, H1 and H3 untouched. U2 recorded, not altered. The core audit
path, the bounded retry policy and correction #9's column/index separation are
unchanged and remain covered by their own suites.
