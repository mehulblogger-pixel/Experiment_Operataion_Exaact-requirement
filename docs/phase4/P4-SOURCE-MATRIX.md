# Phase 4 — Source Matrix

*Every fulfilment source the business named, what record it points at, what the
system asks before accepting it, and where the vocabulary comes from.*

---

## Where the list comes from (§9 — prefer existing vocabulary)

The source list is the **existing configurable `req_sourcing_model` lookup**,
already registered in Masters and already used by the requisition cost model. It
was extended with the values the business named; **no new master was built**, and
a workspace can add its own sources on the Masters screen without a line of code.

The shipped values remain valid even if a workspace narrows its own list. That is
deliberate: narrowing a lookup must never make yesterday's allocations unreadable.

---

## The matrix

| Source | Points at | Table | Module needed | Entity required? |
|---|---|---|---|---|
| Own payroll / internal | — | — | hiring | No — and naming one is **refused** |
| Direct recruitment | — | — | hiring | No — and naming one is **refused** |
| Internal transfer | A person | `inspectors` | hiring | Optional |
| Manpower supply agency | A supplier | `business_partners` | hiring | Optional |
| Third-party (sub-contract) agency | A sub-contractor | `business_partners` | hiring | Optional |
| Supplier | A supplier | `business_partners` | hiring | Optional |
| Client bench | A client | `business_partners` | hiring | Optional |
| Marketplace | A posted requirement | `cx_requirements` | hiring **+ connect** | Optional |
| Freelancer / consultant | A professional | `cx_professionals` | hiring **+ connect** | Optional |
| Consultant | A professional | `cx_professionals` | hiring **+ connect** | Optional |

"Optional" means the promise can be recorded before the supplier is chosen —
*five from an agency, which one to be decided*. What is **not** optional is that
if an entity **is** named, it must be real.

---

## What is asked before a source is accepted

In this order, each failing closed:

1. **Is it a configured source?** Anything not in the list — an array, a word, a
   boolean, a negative number, `1e3`, an empty string — is `BAD_SOURCE`. Nothing
   is coerced. *(S4, seven probes.)*
2. **Is the named entity a usable id?** An array or `"abc"` is `BAD_VALUE`, never
   silently read as "no entity". *(S5.4.)*
3. **Does this source type even have an entity?** Own payroll and direct
   recruitment do not, so naming one is `SOURCE_ENTITY_UNKNOWN` rather than
   quietly ignored — an ignored value is a value somebody will later rely on.
   *(S5.5.)*
4. **Has this workspace bought the module the entity lives in?** Marketplace and
   professionals sit behind `connect`. Without it the source is refused outright:
   **hiding the option from a dropdown is not a control**. *(S5.7, mutant T25.)*
5. **Does the record exist HERE?** Tenancy is structural — one database per
   tenant — so a row from another workspace is simply absent, and the engine
   refuses what it cannot find rather than writing an id it could not verify.
   *(S5.1, S5.6, mutant T24.)*

Only then are the ordinary allocation questions asked (permission, scope,
executability, the ceiling).

---

## The label beside the entity

`source_label` is free text — "Sterling", "the Pune agency" — and is **display
only, never a security identity**. Nothing is looked up by it, nothing is matched
on it, and no decision reads it. Where a real record is meant, `source_entity_id`
carries it.

---

## Sources and cost

A source is **not** a cost model. The existing `req_sourcing_model` value on the
requisition drives the cost build-up and is untouched. An allocation using the
same vocabulary does not change, override or feed that calculation; it records a
**promise of people**, which is a different question from what they cost. Joining
the two is a deliberate future decision, not an accident of a shared list.
