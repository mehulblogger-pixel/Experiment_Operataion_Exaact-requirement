# Phase 6 · Batch 3 — Mutation results

*Do the tests actually test anything, or do they agree with whatever the code
does?* Twenty-six deliberate defects were planted in the Batch 3 code, one at a
time, each on a **fresh copy** of the application with its **own** MariaDB
database. A mutant is **CAUGHT** only if the suite reports **more failures than
the clean baseline**.

Three rules, carried over from Batches 1 and 2 and enforced by the harness:

* **A crash is not a catch.** A suite that died did not detect anything; it
  stopped running. Reported as FATAL, never counted.
* **An anchor miss is not a catch.** If the text to be mutated is not found
  exactly once, the mutant did not exist. Reported, never counted.
* **A dirty baseline aborts the battery**, because it would measure nothing.

Suites run per mutant: `p6_batch3` · `onboarding_engines` ·
`portal_contact_link` · `cvp_governance` · `field07`.

*(Results table below — filled from the battery run.)*
