# Milestone 9 — Marketplace / Connect Matrix

Built from the repository: routes extracted from the dispatcher, gate functions
from the Connect libraries, data ownership from what each surface actually reads.

**Product module:** `connect` — "Marketplace & Connect", sellable, not core,
claims no access modules (entitlement resolves through the product key).

Legend — **Ent.** entitlement required · **RBAC** the permission check that still
applies after entitlement · **Cov.** covered by the M9 test suite.

---

## 1. Staff routes

All 21 are absent from the M5 route-gate map; each is governed by its handler's
own `connect_*_can()` gate, every one of which now carries entitlement.

| Route | Action | Access module | Product module | Ent. | RBAC | Data ownership | Cov. |
|---|---|---|---|---|---|---|---|
| `connect-requirements` | Manpower requirement board (post + list) | — (product key) | **connect** | required | `connect_market_can()` | `cx_requirements` | ✅ |
| `connect-requirement` | One requirement, applications, lifecycle | — | **connect** | required | `connect_market_can()` | `cx_requirements`, applications | ✅ |
| `connect-concierge` | Guided requirement builder | — | **connect** | required | `connect_concierge_can()` → market | `cx_requirements` | ✅ |
| `connect-talent` | Talent search over the shared pool | — | **connect** | required | `connect_market_can()` | `cx_professionals` | ✅ |
| `connect-bench` | Agency bench workspace | — | **connect** | required | `connect_bench_can()` | bench tables | ✅ |
| `connect-source` | Inspection request → manpower sourcing | — | **connect** | required | `connect_source_can()` | sourcing/ranking | ✅ |
| `connect-match-weights` | Matching weights admin | — | **connect** | required | `connect_match_weights_can()` | match config | ✅ |
| `connect-identity` | Link inspector ↔ marketplace professional | — | **connect** | required | `connect_identity_admin_can()` | `cx_identity_link` | ✅ |
| `connect-verify` | Verification & moderation desk | — | **connect** | required | `connect_verify_can()` | verification | ✅ |
| `connect-credentials` *(via verify desk)* | Credential records | — | **connect** | required | `connect_verify_can()` | credentials | ✅ |
| `connect-messages` | In-app messaging (staff desk) | — | **connect** | required | `connect_msg_staff_can()` | messages | ✅ |
| `connect-channels` | WhatsApp / SMS / e-mail channel desk | — | **connect** | required | `connect_channels_can()` | channel config | ✅ |
| `connect-analytics` | Labour-market analytics (read-only) | — | **connect** | required | `connect_analytics_can()` | marketplace analytics | ✅ |
| `connect-taxonomy` | Industry taxonomy (read-only) | — | **connect** | required | `connect_taxonomy_can()` | taxonomy | ✅ |
| `connect-taxonomy-admin` | Taxonomy graph CRUD | — | **connect** | required | `connect_taxonomy_admin_can()` | taxonomy | ✅ |
| `connect-qualifications` | Qualification & role taxonomy | — | **connect** | required | `connect_qualtax_can()` | qual taxonomy | ✅ |
| `connect-orgs` | Marketplace organisations | — | **connect** | required | `connect_market_can()` | `cx_organisations` | ✅ |
| `connect-voucher-file` | Serve an engagement voucher document | — | **connect** | required | `connect_market_can()` | voucher files | ✅ |
| `marketplace-plans` | Subscription plans & limits (Super-Admin) | — | **connect** | required | super-admin | plans | ✅ |
| `marketplace-escrow` | Escrow holds → release / refund | — | **connect** | required | desk gate | escrow | ✅ |
| `marketplace-toggle` | One-tap marketplace on/off | — | **connect** | the switch itself | owner | setting | ✅ |
| `passport-share` | Share a professional passport | — | **connect** | required | `connect_passport_can()` | `cx_professionals` | ✅ |

Every gate above is asserted **both ways** — none opens without the module, and
all sixteen reopen for a master once it is subscribed.

---

## 2. Public marketplace surfaces

| Entry point | Operation | Product module | Ent. | Public config | Cov. |
|---|---|---|---|---|---|
| `/connect` | Public marketplace front door (sign in / create account) | **connect** | required | — | ✅ |
| `/pro`, `/pro/login`, `/pro/register`, `/pro/forgot`, `/pro/reset`, `/pro/logout` | Freelancer portal | **connect** | required — via `connect_pro_portal_on()`, which *is* a call to `connect_enabled()` | — | ✅ |
| `/join` | Public organisation onboarding (lands PENDING) | **connect** | required | — | ✅ |
| `/p/<token>` | Professional's public passport (unguessable token) | **connect** | required | — | ✅ |

M9 did **not** make anything public that was not already public, and did not
invent a public marketplace (§19).

---

## 3. Marketplace data reached through another front door

| Surface | Permission | Was | Now | Cov. |
|---|---|---|---|---|
| Client portal — hiring tiles, post a requirement | `market.post` | unowned | **connect** | ✅ |
| Client portal — review vouchers on own posted jobs | `market.vouchers` | unowned | **connect** | ✅ |
| Vendor portal — browse and apply to requirements | `market.apply` | unowned | **connect** | ✅ |
| Client portal — work orders, reports, invoices | `calls`, `reports`, `invoices` | Operations / Reporting / Money | **unchanged** | ✅ |

The last row is the control: turning the marketplace off must not disturb what a
client sees of their own inspection work.

---

## 4. Background and scheduled work

| Path | Finding |
|---|---|
| `cron.php` | **No marketplace step exists.** All 35 steps were classified in M8; none is Connect-owned |
| `cron_ads.php` | Advertising **lead** sync — Sales & CRM (`leads`), gated in M8. Not marketplace |
| `lib/webhookq.php` | Dormant outbound queue, no callers (M8 limitation L3). Should it ever carry marketplace events, each channel needs its module set at enqueue time |
| Marketplace matching / notifications / settlement | Run **in-request** from the marketplace screens, so they are behind the same gates. No scheduled marketplace job exists to gate |

---

## 5. Marketplace exports and reports

| Export | Route | Ent. | Note |
|---|---|---|---|
| `connect-voucher-file` | staff desk | **connect** | Behind `connect_market_can()` |
| Client-portal voucher files | `market.vouchers` | **connect** | Behind `portal_need('market.vouchers')` → `pcan()` |
| `connect-analytics` | staff desk | **connect** | Behind `connect_analytics_can()` |
| TAPI analytics export | `analytics-export` | — | The 20 registered metrics are Operations, Reporting and core (M7). **No marketplace metric exists**, so no marketplace data leaves through the core analytics export |

---

## 6. Entitlement states

| State | Marketplace |
|---|---|
| `ENTITLED` | open, subject to the existing RBAC |
| `NOT_ENTITLED` | closed |
| `TENANT_DISABLED` (company switched it off) | closed |
| `LICENCE_BLOCKED` (unverifiable signed licence) | closed |
| `UNKNOWN` (blank entitlement record) | closed |
| Invalid / rubbish entitlement record | closed |
| Control install / self-hosted | open — never limited |

---

## 7. Inputs that cannot buy the marketplace

| Supplied by the caller | Effect |
|---|---|
| `module`, `product` | none |
| `connect_enabled` in a request | none — read from the workspace's settings, and the ceiling outranks it |
| `saas_entitled_modules` in a request | none — written only by provisioning / super-admin |
| `tenant` | none — the workspace comes from the host name |
