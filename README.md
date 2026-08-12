# Drupal Notification Service

Reusable Drupal 11 notification service intended to live in its own public repository. It exposes a small HTTP API that other applications can call when they need notifications. The first supported channel is email; the structure leaves room for SMS, push, Slack, webhooks, or queue-backed delivery later.

## Architecture

```text
notification-service/
  composer.json
  web/modules/custom/notification_service/
    notification_service.info.yml
    notification_service.routing.yml
    notification_service.services.yml
    notification_service.module
    openapi.yml
    src/Controller/NotificationApiController.php
    src/Controller/DocsController.php
    src/EventSubscriber/CorsSubscriber.php
    src/Services/EmailNotificationService.php
```

## Responsibilities

| Layer | Files | Purpose |
| --- | --- | --- |
| API controller | `NotificationApiController.php` | Accepts JSON requests, validates the API key, and returns API-shaped responses. |
| Email service | `EmailNotificationService.php` | Validates email payloads, sends through Drupal mail, and stores recent delivery records in Drupal state. |
| Mail hook | `notification_service.module` | Converts generic email payloads into Drupal mail messages. |
| CORS subscriber | `CorsSubscriber.php` | Adds optional CORS support for browser-based clients. |
| Docs controller | `DocsController.php` | Serves Swagger UI and the OpenAPI contract. |
| OpenAPI | `openapi.yml` | Documents the public HTTP contract for other teams. |

## API

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/notifications/health` | Public | Returns service status and capabilities. |
| `POST` | `/api/notifications/email` | `X-Notification-Key` | Sends one email notification. |
| `GET` | `/api/docs` | Public | Swagger UI. |
| `GET` | `/api/docs/openapi.yml` | Public | Raw OpenAPI YAML. |

## Swagger

When running locally on port `8090`:

```text
Swagger UI:   http://127.0.0.1:8090/api/docs
OpenAPI YAML: http://127.0.0.1:8090/api/docs/openapi.yml
```

Use the `Authorize` button in Swagger and enter the shared notification API key.

## Configuration

Put environment-specific values in `web/sites/default/settings.local.php` or environment variables.

```php
$settings['notification_service_api_key'] = getenv('NOTIFICATION_SERVICE_API_KEY') ?: 'replace-with-a-shared-api-key';
$settings['notification_service_from_email'] = getenv('NOTIFICATION_SERVICE_FROM_EMAIL') ?: 'no-reply@example.com';
$settings['notification_service_cors_allowed_origins'] = ['http://127.0.0.1:5173'];
```

For local development in this workspace, the auth backend expects:

```text
NOTIFICATION_SERVICE_API_KEY=local-notification-api-key-change-me
```

## Example Request

```powershell
$base = 'http://127.0.0.1:8090'
Invoke-RestMethod "$base/api/notifications/email" -Method Post -ContentType 'application/json' -Headers @{
  'X-Notification-Key' = 'local-notification-api-key-change-me'
} -Body (@{
  to = 'user@example.com'
  subject = 'Welcome'
  body = 'Thanks for signing up.'
  metadata = @{ source = 'example-app'; template = 'welcome' }
} | ConvertTo-Json -Depth 5)
```

## Extension Points

- Replace Drupal mail with an SMTP, SES, SendGrid, Mailgun, or queue-backed transport inside `EmailNotificationService`.
- Add channel-specific services and routes for SMS, push, or Slack without changing existing email consumers.
- Add template rendering by introducing a template ID and variables in the request payload.
- Persist notification history in a custom content entity if audit/reporting requirements grow beyond the current state log.
