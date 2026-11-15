# EXAACT V51.1 audit — rules every QA agent follows

You are one agent in an audit of the EXAACT PHP app. The owner is a non-developer. Accuracy matters more than speed. Never hide a failure, a gap or a limitation.

## Paths

- **Repo (READ ONLY for you):** `/home/user/Experiment_Operataion_Exaact-requirement`. The app source is in `phpapp/`.
  - Business rules: `docs/01-roles.md`, `docs/02-permission-matrix.md`, `docs/03-object-lifecycles.md`, `docs/04-flows/*.md`.
- **Robot root:** `R=/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad/robot`
  - Read `$R/harness/README.md` FIRST. It explains how to write and run a test-book case: helpers, `t.step`, `t.needs`, `t.outdated`.
  - Look at 2–3 existing scripts in `$R/harness/cases/SAL-*.js` and `$R/harness/lib-g1.js` for working examples.
  - Test book: `$R/harness/book.json` → `cases[]` (id, area, module, title, persona, before, steps[{do, expect}]).
- **The app under test is the V51.1 BASELINE copy at `$R/app`.** Never edit anything in `$R/app`, `$R/app_fix`, the repo, `book.json`, `robot.js` or another agent's files.
  - Put shared helpers you write in your own file `$R/harness/lib-<yourcode>.js`.
  - Put case scripts in `$R/harness/cases/<ID>.js`.
- **Your private copies:** `bash $R/qaworker.sh <name> <port>` starts a fresh copy of the app with its own database on your port. It prints the `ROBOT_DB` / `ROBOT_BASELINE` values to use.
  - Use ONLY the ports given to you.
  - Run cases: `cd $R/harness && ROBOT_DB=… ROBOT_BASELINE=… node robot.js <port> <ID> <ID> …`. Results go to `$R/results/<ID>.json`; screenshots to `$R/shots/`.
- **SQL on your copy:** `php $R/harness/tq.php <your db> '<sql>' '<json params>'`. Use it for set-up the "before" needs, and for database checks.
- **Never touch** opstest or the live site, any real e-mail, real payment or real customer data. Everything is made-up data on private copies.
- **No code changes. No git commands that change anything** (no commit, push, checkout, reset or stash).

## The tracker (central, append-only)

Write with:

```
node /home/user/Experiment_Operataion_Exaact-requirement/QA-REPORTS/V51/tools/qa.js add <kind> '<json>'
```

One event per call. Every event needs `"id"` and `"by"` (your agent code). Never edit or delete `data/events.jsonl`. Do not run `qa.js build`; the orchestrator does that.

Status labels — use EXACTLY one of:

> NOT STARTED · IN PROGRESS · COMPLETED – PASS · COMPLETED – FAIL · BLOCKED · NOT IMPLEMENTED · CANNOT TEST · CANNOT DO – AI / TOOL LIMITATION · NOT APPLICABLE – WITH JUSTIFICATION · FIXED – RETEST PENDING · RETEST PASSED · RETEST FAILED · OWNER ACCEPTANCE PENDING · OWNER ACCEPTED · OWNER DECISION REQUIRED

The dash is an en dash `–`. Copy the labels from here.

### `agent`

`{"id":"<your agent code>","by":"<code>","status":"IN PROGRESS","current":"what you are doing","screen":"/route","next":"…"}`

Send one when you start, at each change of task, and when you finish.

### `test`

`{"id":"<test-book ID>","by":"<code>","status":"…","method":"…","actual":"what you saw, step by step","evidence":"robot shots/<file>","defect":"DEF-…","note":"…","executed":true|false}`

- `method` is one of:
  - "Browser (robot, Playwright)"
  - "Browser + database check"
  - "Database check only"
  - "Phone-size emulation (iPhone 13)"
  - "Code reading only (not a live test)"
  - "Automated suite"
- Result mapping:
  - **PASSED** → `COMPLETED – PASS`
  - **FAILED, confirmed real** → `COMPLETED – FAIL`
  - **Robot needs the real world** (real payment page, inbox, print, phone hardware, outside service) → `CANNOT DO – AI / TOOL LIMITATION`
  - **The test has no fixed right answer, or the rule is ambiguous** → `OWNER DECISION REQUIRED`, with the question and your proposed answer in `note`
  - **Screen changed on purpose by a later owner decision** (check `docs/03-object-lifecycles.md` and `git log`) → `BLOCKED`, with `note` = "test wording out of date" + suggested new wording
  - **Your own script failed, not the app** → fix the script and rerun. Never report a script error as an app defect.

### `defect` — only for a failure you CONFIRMED on screen (and in the database where relevant)

`{"id":"DEF-<MOD>-NNN","by":"<code>","severity":"P0|P1|P2|P3","module":"…","screen":"…","description":"…","steps":"…","expected":"…","actual":"…","evidence":"…","root":"cause, with file:line if you can find it","files":"…","proposal":"proposed correction","approval":"…","test":"<test ID>","status":"NOT STARTED"}`

- Severity:
  - P0 — app-wide failure, serious security hole, data loss, severe financial corruption
  - P1 — key workflow unavailable, wrong billing, major permission failure, serious data integrity
  - P2 — partly broken, wrong report, significant usability
  - P3 — cosmetic or minor
- `approval`:
  - "Owner approval needed (money/billing/contract/approval/security/business rule)" if a fix would touch any of those;
  - otherwise "Within owner P2/P3 approval".
- **If the same problem is already fixed in the held v52 or v53 update, say so in `note`.** Compare with `$R/results_prev/<ID>.json`, which ran on v53 code. V52/V53 are held; never apply them.
- Never fix anything yourself.

### `historical`

`{"id":"HF-xxx or HN-x","by":"<code>","v51":"Reproduced in V51 | Fixed in V51 | Not reproduced | Obsolete | Cannot verify | Requires further investigation","evidence":"what you did and saw","status":"COMPLETED – PASS (fixed / not reproduced) | COMPLETED – FAIL (reproduced) | CANNOT TEST | BLOCKED | OWNER DECISION REQUIRED","method":"…"}`

The list is in `QA-REPORTS/V51/data/historical_findings.json` (`open_items` and `named_items`). Do NOT mark an item without actually checking it. If you could only read the code, say so: `method` "Code reading only".

### `evidence`

`{"id":"EV-<…>","by":"<code>","ref":"<test or defect id>","type":"screenshot|database|log","file":"robot shots/<file> or path","what":"what it shows"}`

### `incomplete`

`{"id":"I-<code>-NN","by":"<code>","module":"…","what":"…","why":"…","attempted":"…","type":"…","needed":"…","owner":"…","next":"…","status":"…"}`

Use this for anything you could not finish.

## Honesty rules

1. PASS only when the expectation was actually seen on the screen, or in the database where it is about stored data.
2. Every numbered step of a case gets exactly one result.
3. A business-rule ambiguity stops only that test: mark it OWNER DECISION REQUIRED, then continue with the rest.
4. Make scripts deterministic and re-runnable. At the end, run your whole set once more and record the final results.
5. Your final message to the orchestrator lists:
   - counts per status;
   - every DEF-ID with a one-line description and severity;
   - every OWNER DECISION REQUIRED question;
   - everything BLOCKED or CANNOT TEST, and why.
