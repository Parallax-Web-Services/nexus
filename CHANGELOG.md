# NEXUS release notes

## Unreleased

This is an inventory of the initial public source snapshot, **not** a tagged release or a production compatibility guarantee.

- Standalone Laravel 13 / Vue 3 account and team application.
- OAuth 2.0 authorization-code integration through Laravel Passport.
- Authenticated `GET /api/me` response used by HELM to synchronize account and team context.
- Scoped v1 identity and token-disconnect endpoints documented in [docs/identity-api.md](docs/identity-api.md).
- Service entitlements and optional Stripe-backed billing for operator-configured services.
- SQLite-first local setup without default credentials or required Parallax domains.
- CI coverage for dependency installation, frontend build, migrations, repeatable seeding, Passport setup and PHPUnit.
- Public contributor guidance, issue templates and release-readiness checklist are proposed in the associated documentation PR.

### Before the initial versioned release

The maintainers need to complete the [release checklist](docs/RELEASE-CHECKLIST.md), choose a version, provide a confirmed private vulnerability-reporting channel and publish the supported/unsupported integration contract. NEXUS does **not** currently advertise OIDC discovery or ID tokens.
