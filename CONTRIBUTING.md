# Contributing to Nexus

NEXUS is publicly available under the [MIT license](LICENSE). Issues, focused pull requests and constructive feedback are welcome. NEXUS is also used inside Parallax, but a contributor should be able to work from a standalone installation without internal company infrastructure.

## Get a working installation

Follow the [README setup](README.md#local-setup) in Linux or WSL with PHP 8.5, Composer 2, and Node.js 24. Install dependencies from `composer.lock` and `package-lock.json`.

Use your own registered account. The default seed creates no user credentials or external application clients. Optional sample catalog data is available through `php artisan db:seed --class=DemoSeeder`.

## Keep changes focused

Describe the problem, resulting behavior, and relevant checks in each change. Add regression coverage when changing authentication, authorization, tenant boundaries, billing, entitlement synchronization, or data preservation.

Keep optional integration configuration separate from the contributor setup. Do not add credentials, private keys, production customer data, or a required company domain to the repository.

Preserve existing data when changing setup and seeds. Use forward migrations for schema changes; do not require contributors or operators to reset a working database. Changes to account ownership, OAuth scope behavior, or subscription semantics should include a migration and compatibility explanation.

For dependency updates, change the manifest and its lockfile together. Distinguish required compatibility updates from unrelated upgrades.

## Validate your change

```sh
composer install
npm ci
npm run build
php vendor/bin/phpunit
```

PHPUnit uses the in-memory SQLite database configured in `phpunit.xml`; it should not need your development or production database. Keep tests independent of real Stripe, mail, and external application credentials.

Use Pint on the PHP files you change:

```sh
php vendor/bin/pint --test path/to/ChangedFile.php
```

A repository-wide formatting cleanup is not required for an unrelated fix.

The GitHub Actions workflow checks a clean dependency install, frontend build, fresh SQLite migration, repeatable core/demo seeding, Passport setup, and the test suite. The workflow needs no repository secrets.

## Application integrations

The current integration surface is Passport OAuth 2.0 plus `GET /api/me`. Do not document OIDC support, an externally stable account schema, or a completed reference application until the corresponding behavior exists and is verified.

[Helm by Parallax](https://github.com/Parallax-Web-Services/helm) is the reference client application.

## Security and release boundaries

For suspected security vulnerabilities, follow [SECURITY.md](SECURITY.md); do not include exploit details in a public issue or pull request. Be constructive and respectful toward other contributors. GitHub reporting tools can be used for abusive interactions.

Source availability under MIT does not imply a published, versioned release or support guarantee. Maintainers choose release versions, security contact details, and compatibility commitments. See the [first-release checklist](docs/RELEASE-CHECKLIST.md) for open verification items.
