# NEXUS

NEXUS is the shared core inside PARALLAX. It is a Laravel and Vue application for shared accounts, teams, OAuth 2.0 access, service entitlements, and optional Stripe billing.

The stack is Laravel 13, Vue 3, Inertia, Jetstream, and Passport.

## Why we're opening this up

NEXUS grew out of shared account and application infrastructure developed at Parallax alongside Slipstream and Continuum. We're sharing it for teams building their own connected applications. You can self-host NEXUS; no hosted Parallax account, paid plan or company domain is required for the standalone setup.

NEXUS and [HELM by Parallax](https://github.com/Parallax-Web-Services/helm) are complementary, not inseparable: NEXUS owns accounts, teams and application access; HELM provides the Laravel/React command deck and acts as an example OAuth client. HELM also offers local demo sign-in without NEXUS.

**Current protocol boundary:** OAuth 2.0 / Passport is available. NEXUS is not currently an OpenID Connect provider and does not advertise OIDC discovery or ID tokens.

## Local setup

These commands are for a new checkout in Linux or WSL. Run all commands inside the same Linux environment.

Requirements:

- PHP 8.5 and Composer 2, with the required PHP extensions, including PDO SQLite, mbstring, XML/DOM, cURL, and OpenSSL.
- Node.js 24 and npm.
- Git and a writable project directory.

Clone the repository you have access to, enter its directory, and run:

```sh
cp .env.example .env
composer install
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan passport:keys
php artisan passport:client --personal --name="Nexus Personal Access" --provider=users
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open [http://localhost:8000/register](http://localhost:8000/register) and create your own account. Registration creates a personal team. No default user or shared password is installed.

The example configuration uses SQLite, local file caching, a synchronous queue, and log mail. It does not require a company domain, mail service, Redis, or Stripe credentials. Email messages, including verification and password-reset links, are written to `storage/logs/laravel.log`.

Generate application and Passport keys once for each new installation. The personal-access client is required for the API token screen; create it once as well. Keep the same keys and client when restarting or updating an existing installation.

The setup above uses a production frontend build. For frontend development, run `npm run dev` in a second terminal while the PHP server is running.

## Database and optional examples

`php artisan migrate --seed` installs the schema and Nexus's core service, permissions, and roles. The default seed is repeatable and preserves existing records, credentials, permissions, and service associations. It creates no users, OAuth clients, paid plans, or subscriptions.

Optional sample catalog data:

```sh
php artisan db:seed --class=DemoSeeder
```

This creates an Example App service and an unpriced Example Plan. It is repeatable and does not grant access, create an OAuth client, or enable billing. It is catalog data, not a running example application.

Existing deployment-specific service domains and billing accounts remain supported through configuration. They are not needed for the standalone setup.

## Integrating an application

Nexus currently exposes Passport OAuth 2.0 authorization and token endpoints and an authenticated `GET /api/me` endpoint. Applications use the authorization-code flow and send the resulting access token as an `Authorization: Bearer ...` header when calling `/api/me`.

Register each application's exact callback URL using Passport. For a server-side application:

```sh
php artisan passport:client --name="Example App" --redirect_uri="http://localhost:9000/oauth/callback"
```

Store the generated client secret in that application's private configuration. Public clients use `--public` and PKCE instead. Use separate clients and callback URLs for separate applications and environments.

The personal-access client created during local setup serves Nexus's API token screen; it is separate from application authorization-code clients.

Nexus does not currently advertise OpenID Connect discovery, ID tokens, or an OIDC user-info contract. The existing `/api/me` response is an application API and should not be treated as a finalized public identity schema.

[Helm by Parallax](https://github.com/Parallax-Web-Services/helm) is an open-source Laravel starter app that signs in through NEXUS. Use it as a reference client, or as the base for a suite of apps that share one NEXUS sign-in. The narrow identity and disconnect endpoints are documented in [the v1 identity contract](docs/identity-api.md).

## Optional billing

The account and team flows work without Stripe credentials. Billing becomes usable only after an operator configures their own Stripe account, prices, and webhook secrets. The default and demo seeds do not create checkout-ready plans.

The primary Stripe account is handled by Laravel Cashier. Additional Stripe accounts are declared in `BILLING_ACCOUNTS` as `key:Name` pairs; each reads `<KEY>_STRIPE_SECRET` and `<KEY>_STRIPE_WEBHOOK_SECRET`. See `.env.example`.

## Deployment builds

`public/build` is generated by Vite and is ignored by Git. Run `npm ci --include=dev` and `npm run build` after updating source code and before serving a release. Do not commit server-generated manifests or bundles.

## Checks

```sh
npm run build
php vendor/bin/phpunit
```

PHPUnit forces an isolated, in-memory SQLite database. CI installs the locked dependencies, builds the frontend, verifies migration and repeatable seeding on a disposable SQLite database, and runs the test suite.

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution scope and validation guidance, [SECURITY.md](SECURITY.md) for private vulnerability reporting, and the [first public release checklist](docs/RELEASE-CHECKLIST.md) for remaining verification work.

## Releases and maintenance

Current work is tracked in the [unreleased change notes](CHANGELOG.md) and [first public release checklist](docs/RELEASE-CHECKLIST.md). The public source repository is available under MIT; this does not imply an initial version tag, production support promise, or OpenID Connect certification.

## License

NEXUS is open-source software licensed under the [MIT license](LICENSE).

The names and logos for NEXUS, Parallax and the Parallax products, including the brand assets under `public/assets`, are not covered by the MIT license.
