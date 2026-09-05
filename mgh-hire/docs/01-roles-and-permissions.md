# MGH Hire — roles & permissions

This is the product's **own** role model. It is independent of any other
application. The code enforces exactly this matrix (`lib/app.php` → `PERMS()`);
the docs and the code must never disagree.

## Roles

| Role | Who it's for |
|---|---|
| **Administrator** | Owner / HR admin. Everything, including branding, users and the pipeline. |
| **Recruiter** | Runs the day-to-day pipeline — raises requisitions, adds candidates, moves stages, issues offers. |
| **Hiring Manager** | Reviews and decides — approves requisitions and gate stages. No settings/users. |
| **Interviewer** | Records interview outcomes and feedback only. |
| **Viewer** | Read-only. |

## Permission matrix

| Permission | Admin | Recruiter | Hiring Mgr | Interviewer | Viewer |
|---|:--:|:--:|:--:|:--:|:--:|
| View everything (`view`) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Manage branding (`settings`) | ✅ | | | | |
| Manage users (`users`) | ✅ | | | | |
| Edit the pipeline (`pipeline.edit`) | ✅ | | | | |
| Raise / edit requisitions (`req.manage`) | ✅ | ✅ | | | |
| Approve requisitions (`req.approve`) | ✅ | | ✅ | | |
| Add / edit candidates (`cand.manage`) | ✅ | ✅ | | | |
| Move a candidate a stage (`cand.move`) | ✅ | ✅ | ✅ | | |
| Decide a gate / reject (`gate.decide`) | ✅ | ✅ | ✅ | | |
| Record interview outcome (`interview.log`) | ✅ | ✅ | ✅ | ✅ | |
| Issue / accept an offer (`offer.manage`) | ✅ | ✅ | | | |

**Rule:** never grant a role a permission not listed here without updating this
file in the same change as the code.
