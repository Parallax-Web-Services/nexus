# Nexus identity integration v1

This contract supports a separate application signing in through Nexus OAuth 2.0. It is not an OpenID Connect user-info endpoint.

Register a separate authorization-code client with an exact callback URL. Request the `identity:read` scope and use a single-use state value; public clients also require PKCE. Exchange the code server-side at `POST /oauth/token`, then send the access token as `Authorization: Bearer <token>`.

## GET /api/v1/identity

Requires an authenticated Passport token with `identity:read`. General `read` and named `full-access` scopes do not grant this scope. Passport's standard wildcard scope retains its existing semantics.

The response has exactly these fields:

```json
{
  "issuer": "https://nexus.example.test",
  "subject": "123",
  "name": "Example Person",
  "email": "person@example.test",
  "email_verified": false
}
```

`issuer` is the configured Nexus application URL without trailing slashes. Applications should trust only their configured issuer and identify a person by issuer plus subject. Subject is always a string. Name and email can change; email verification reflects the stored verification timestamp. No teams, membership records, billing details, or entitlement claims are returned.

This identity response grants no campaign-owner role, organization authority, paid entitlement, or permission to act on another person's data. The consuming application must authorize its own operations.

## DELETE /api/v1/session

Requires the same scope and the current OAuth bearer token. Returns 204 after revoking that access token and all refresh tokens associated with it. Request body token IDs are ignored. Other tokens for the person remain valid.

Cookie-backed transient authentication and web sessions cannot use this operation to revoke OAuth tokens. The endpoint does not sign the person out of Nexus's browser session. A consuming application should revoke its bearer token when disconnecting, then clear its own local session and stored credentials.

A missing or invalid bearer receives 401; an authenticated token without the required scope receives 403. An already-revoked bearer receives 401. OAuth refresh tokens are exchanged through `POST /oauth/token` using `grant_type=refresh_token`; `/oauth/token/refresh` is a separate cookie-based operation.

The existing `GET /api/me` remains unchanged for compatibility.
