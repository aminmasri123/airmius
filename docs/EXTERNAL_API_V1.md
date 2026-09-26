# Airmius External API v1

The external API is published under `/api/v1/external` and follows the normal API v1 contract headers and error envelope. Clients must authenticate with a Sanctum bearer token.

## Authentication and Tenant Scope

External member reads require the token ability `external.members:read`. Writes require `external.members:write`.

Every request is also scoped to a club tenant in the URL. The authenticated user must have the club-scoped `members.manage` permission for that exact club. A valid token for one club never grants access to another club.

## Endpoints

`GET /api/v1/external/clubs/{club}/members`

Returns a paginated list of external member records for the club. Supports `per_page` up to the platform maximum and optional `status`.

`GET /api/v1/external/clubs/{club}/members/{externalMember}`

Returns one external member record. A record outside the requested club is returned as `404`.

`POST /api/v1/external/clubs/{club}/members`

Creates an external member record and writes a club audit entry with `source=external_api`.

`PUT /api/v1/external/clubs/{club}/members/{externalMember}`

Updates an external member record in the same club and writes an audit entry with changed fields when values changed.

## Response Shape

Collections use:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "tenant": {"type": "club", "id": 1}
  },
  "links": {"first": "...", "last": "...", "prev": null, "next": null}
}
```

Errors use the API v1 error contract with `message`, `errors`, `code`, `error` and `meta`.
