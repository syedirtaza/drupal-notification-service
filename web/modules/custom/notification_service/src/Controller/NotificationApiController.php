<?php

namespace Drupal\notification_service\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\notification_service\Services\EmailNotificationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP API controller for notification operations.
 */
final class NotificationApiController extends ControllerBase {

  public function __construct(
    private readonly EmailNotificationService $emailNotificationService,
    private readonly Settings $settings,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('notification_service.email'),
      $container->get('settings'),
    );
  }

  public function health(): JsonResponse {
    return new JsonResponse([
      'service' => 'notification-service',
      'status' => 'ok',
      'capabilities' => ['email'],
    ]);
  }

  public function sendEmail(Request $request): JsonResponse {
    if (!$this->isAuthorized($request)) {
      return $this->error('Missing or invalid notification API key.', 401);
    }

    try {
      return new JsonResponse($this->emailNotificationService->send($this->payload($request)), 202);
    }
    catch (\InvalidArgumentException $exception) {
      return $this->error($exception->getMessage(), 422);
    }
  }

  private function payload(Request $request): array {
    $payload = Json::decode($request->getContent() ?: '{}');
    return is_array($payload) ? $payload : [];
  }

  private function isAuthorized(Request $request): bool {
    $configured_key = (string) $this->setting('notification_service_api_key', 'local-notification-api-key-change-me');
    $provided_key = (string) $request->headers->get('X-Notification-Key', '');

    return $configured_key !== '' && hash_equals($configured_key, $provided_key);
  }

  private function setting(string $name, mixed $default): mixed {
    $env_value = getenv(strtoupper($name));
    if ($env_value !== FALSE && $env_value !== '') {
      return $env_value;
    }

    return $this->settings->get($name, $default);
  }

  private function error(string $message, int $status): JsonResponse {
    return new JsonResponse(['error' => ['message' => $message]], $status);
  }

}
