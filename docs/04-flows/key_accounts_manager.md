# Flow — KEY_ACCOUNTS_MANAGER

Sales owner for named key accounts. **Identical permissions to BUSINESS_DEV_MANAGER**
(same `role_defaults_base` and `module_defaults` cases — `access.php:456-457`, `:348-350`),
focused on retention and upsell.

- **Landing / sees / can / cannot / handoff / common task:** exactly as
  `business_dev_manager.md`. Leads → quote (create) → send → follow-up → accepted quote
  hands off to Finance to register the contract.
- **Boundary:** no quote-approve, no contract register, no operations, no user admin.

> This role differs from BDM only in intent (named accounts), not in code-level access.
