# First public release checklist

This is a maintainers' checklist, **not** a compatibility promise or scheduled release announcement. NEXUS source is currently public and MIT licensed, but its first GitHub release has not yet been tagged.

## Documentation and permissions

- [ ] Confirm README, [CONTRIBUTING.md](../CONTRIBUTING.md) and [LICENSE](../LICENSE) agree that the source is publicly usable under MIT.
- [ ] Enable GitHub private vulnerability reporting or publish a verified private reporting contact; update [SECURITY.md](../SECURITY.md).
- [ ] Review trade-name/logo notices and ensure no non-public assets or credentials are included.
- [ ] Set discoverability topics and provide a clear repository social preview.
- [ ] Publish a short changelog and release notes describing exactly what is supported and what is experimental.

## Reproduce a clean install

- [ ] On a fresh Linux/WSL machine, use PHP 8.5, Composer 2, Node.js 24 and npm.
- [ ] Follow README setup with SQLite, no company domains, no Redis, no Stripe configuration and no pre-existing credentials.
- [ ] Create a new account; verify the personal team is created.
- [ ] Verify core migrations/seeds are repeatable and preserve existing data.
- [ ] Run `npm run build` and `php vendor/bin/phpunit`, and inspect the CI workflow results.
- [ ] Confirm local setup is not accidentally configured for production use.

## Authentication and integration

- [ ] Register an exact callback URL for a confidential Passport authorization-code client.
- [ ] Verify HELM can sign in and retrieve permitted account/team data; repeat with an additional application.
- [ ] Verify invalid callbacks, absent scopes, expired tokens, token revocation, session disconnect and tenant boundaries.
- [ ] Check authorization behavior against the documented [v1 identity contract](identity-api.md).
- [ ] Confirm `GET /api/me` is represented as an integration API, **not** an OIDC user-info endpoint or guaranteed public schema.
- [ ] Review the public routes, middleware, webhook signature validation and test coverage before a production-oriented announcement.

## Optional billing

- [ ] Verify the no-Stripe experience still works.
- [ ] Use test-mode credentials to exercise billing, failure paths and signed webhooks; never commit credentials or webhook secrets.
- [ ] Do not describe the example catalog as real checkout-ready plans.

## Release decision

- [ ] Maintainer selects initial version and compatibility/support statement.
- [ ] Tag an immutable commit only after clean-install and security checks.
- [ ] Link the accompanying HELM example and the release notes from the README.

The [HELM integration guide](https://github.com/Parallax-Web-Services/helm/blob/main/docs/INTEGRATION.md) is intended to provide the hands-on two-app walkthrough after its documentation PR merges.
