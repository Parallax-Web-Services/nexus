# Security policy

NEXUS handles account authentication, team data, service entitlements and optional billing. Please report suspected vulnerabilities privately instead of opening public issues or pull requests with exploit details.

## Private reporting

1. Go to the repository **Security** tab and choose **Report a vulnerability**, if private vulnerability reporting is enabled.
2. If that option is unavailable, open a public issue asking maintainers to establish a private reporting channel. **Do not include vulnerability details, access tokens, credentials, affected account data or exploit code in that issue.**

Private reports are most useful when they provide an affected version/commit, reproduction steps, expected and actual behavior, impact and any mitigations already attempted. Redact real customer data.

## Priorities and expectations

Particularly sensitive areas include OAuth authorization and refresh flows, token and session revocation, account/team boundaries, authorization, entitlements, billing, Stripe webhooks, and secrets handling. Avoid testing against other people's accounts or production systems without explicit authorization.

No security response-time guarantee, supported release series, or public security email is currently stated. The maintainers should publish a confirmed private reporting path before tagging the initial release.

Use standard issues for non-security bugs.
