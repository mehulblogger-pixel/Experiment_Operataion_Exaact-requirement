# EXAACT Test Book

The interactive test book: https://claude.ai/artifact/BMuHaXZZDyxYAzxdJevTRW
(results, notes and screenshots are kept in the book itself, not here).

| Folder / file | What it is |
|---|---|
| `source/sales_cases.py` | Sales tests, written by hand against the screens and the owner's rules of 20 Oct 2026 |
| `source/setup_guide.py` | "Start here": the company settings, the two branches and the 19 test users (one per role, with what each can and cannot do), and the SET-01 … SET-12 tick-off tests |
| `source/onboarding_cases.py` → `source/cases_onboarding.json` | "New company": a company from zero — applying for a workspace, the platform owner approving or creating it, first sign-in, setup cockpit, buying one / several / all modules (plans, seats, bundles), setting up each module, the marketplace professional and the company posting a requirement, a self-hosted install. Known gaps are written as what should happen and tagged `gap` |
| `source/cases_*.json` | Operations, Recruitment, Marketplace, Quality, Reporting, Money, Insights, Directory, Admin tests, and the cross-module flows |
| `measured/screens_today.json` | Every screen as it rendered on 20 Oct 2026: heading, tabs, every field (label, type, required, choices), buttons, tables |
| `measured/onboarding_screens.json` | The sign-up, sign-in, setup and marketplace-joining screens as they rendered on 22 Oct 2026 |
| `measured/access_today.json` | Which role could open which screen on a test copy that day |
| `extract.py`, `access.py`, `mkusers.php` | How the two measurements were taken (throwaway copy only — never production) |
| `build.py` | Builds `book.json` (the book's data) from the above; one "screen check" per measured screen |
| `verify.py` | Checks every quoted screen word in the tests exists in the screens or the code |
| `page/index.html` | The book's page |

Test ids are permanent (`SAL-…`, `OPS-…`, `SCR-<screen address>` …): results are stored by id, so a
rebuild never moves a saved result onto another test. Add new tests with new ids; do not renumber.
