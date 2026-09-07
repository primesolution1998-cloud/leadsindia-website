# LeadsIndia Property Moderation Backend

## Flow
Owner submits `/list-property` -> server creates `PENDING_VERIFICATION` record -> backend team reviews `/admin/properties.php` -> status changes are audited -> only `LIVE` records appear in `/api/properties-public.php`.

## Admin security
The admin panel is disabled until these server environment variables are configured:

- `LEADSINDIA_ADMIN_USER`
- `LEADSINDIA_ADMIN_PASSWORD_HASH`

Generate a password hash on a PHP-enabled machine/server:

```bash
php -r "echo password_hash('REPLACE_WITH_STRONG_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```

Set the resulting hash as `LEADSINDIA_ADMIN_PASSWORD_HASH`. Do not commit the plaintext password or a local credentials file to Git.

## Admin URLs
- Login: `/admin/login.php`
- Moderation dashboard: `/admin/properties.php`

## Status workflow
- `PENDING_VERIFICATION`: new owner submission
- `UNDER_REVIEW`: backend team is checking it
- `NEED_CORRECTION`: owner/details need correction
- `VERIFIED`: verification passed but not yet public
- `REJECTED`: rejected
- `LIVE`: approved and publishable through public API
- `PAUSED`, `SOLD`, `RENTED`: remove from active public feed without deleting history

## Storage
Private records are stored under `storage/property-submissions/` and protected by `.htaccess`. Public property photos are stored under `uploads/properties/<reference>/`.

## Public API
`GET /api/properties-public.php`

Returns only records whose status is exactly `LIVE`. Owner name, mobile, email, full address, internal notes and audit history are intentionally excluded.

## Important security rule
Do not expose owner mobile/email through the public API. The current legacy marketplace contact-credit UI uses browser localStorage and is not a secure entitlement system. A server-side entitlement/payment check is required before wiring paid contact unlocks to real owner contact data.
