# KSA PDPL-oriented checklist (customer & loyalty data)

- [ ] Document lawful basis for customer phone/email collection at POS
- [ ] Minimize profile JSON in `customers.profile`
- [ ] Retention policy for `loyalty_transactions` and paid `pos_orders`
- [ ] Export/delete request process documented for tenant admins (Phase 7+ portal)
- [ ] Access logs for platform support via `platform_access_audits` only
- [ ] No cross-tenant customer search without `withoutGlobalScopes` audit
