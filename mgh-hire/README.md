# MGH Hire

A **standalone, white-label Recruitment & Selection product**. It runs the full
hiring journey — from a Staff Requisition (SRF) through sourcing, screening,
interviews, documents, salary structure, offer and onboarding — with a complete
audit trail on every candidate.

It is a **separate product**: its own login, its own database, its own branding.
It shares none of its data with any other system. Copy the `mgh-hire/` folder and
it runs on its own.

## Why it's easy to run and sell

- **No build step, no external services.** Plain PHP 8 that builds its own
  database the first time a page is opened.
- **Runs anywhere.** A laptop (double-click `start`), a cheap cPanel host, or a
  client's own server. Same files.
- **Two databases supported.** Built-in file database (SQLite) for a laptop or a
  small team; MySQL for a busy multi-user site — a one-line switch.
- **Fully white-label.** Product name, colours and logo are changed on screen —
  no code, no developer (Branding screen).
- **Its own roles.** Administrator, Recruiter, Hiring Manager, Interviewer,
  Viewer — nothing borrowed from any other application.

## The pipeline it ships with

The default pipeline is a real, approved Recruitment & Selection flow, shipped as
an **editable template** (Pipeline screen — rename, reorder or switch steps off):

> Requisition Approved → Organogram Verified → Sourcing → CV Screening →
> HOD Shortlisting → L1 Interview → L2 Interview → Document Collection →
> Salary Structure → HR Discussion → Candidate Approval → Medical Examination →
> Reference Verification → Medical Fitness Clearance → One-Pager Approval →
> Offer Released → Offer Accepted → Onboarding

## Where things are

| Folder / file | What it holds |
|---|---|
| `index.php` | The single entry point (router). |
| `config.php` / `config.local.sample.php` | Settings template; real settings go in `config.local.php`. |
| `lib/` | `db.php` (database + self-build), `app.php` (auth, roles, helpers), `layout.php` (branded page chrome). |
| `pages/` | One file per screen (dashboard, requisitions, candidate, pipeline, users, settings…). |
| `start.command` / `start.bat` | Double-click launchers for Mac/Linux and Windows. |
| `docs/` | Product governance — roles, permissions, pipeline lifecycle. |
| `INSTALL.md` | The install guide (laptop and server). |

## First run

Open the app → sign in with the administrator from `config.local.php`
(default `admin` / `admin12345` — **change it immediately**) → set your branding →
add users → raise your first requisition.

> Data is private to this workspace. On a cloud install we operate, data is
> encrypted and not readable by us; a client can request an export or restore at
> any time. On a client's own server, data never leaves their premises.
