<?php

namespace Drupal\notification_service\EventSubscriber;

use Drupal\Core\Site\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds optional CORS headers for browser-based notification clients.
 */
final class CorsSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly Settings $settings) {}

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
      KernelEvents::RESPONSE => ['onResponse', -100],
    ];
  }

  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if (!$event->isMainRequest() || !$this->isNotificationPath($request->getPathInfo()) || $request->getMethod() !== 'OPTIONS') {
      return;
    }

    $response = new Response('', 204);
    $this->applyCorsHeaders($response, $request->headers->get('Origin'));
    $event->setResponse($response);
  }

  public function onResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    if (!$event->isMainRequest() || !$this->isNotificationPath($request->getPathInfo())) {
      return;
    }

    $this->applyCorsHeaders($event->getResponse(), $request->headers->get('Origin'));
  }

  private function isNotificationPath(string $path): bool {
    return preg_match('#^/api/(notifications|docs)(/|$)#', $path) === 1;
  }

  private function applyCorsHeaders(Response $response, ?string $origin): void {
    $allowed_origins = $this->settings->get('notification_service_cors_allowed_origins', []);
    $allowed_origins = is_array($allowed_origins) ? $allowed_origins : [];

    if (in_array('*', $allowed_origins, TRUE)) {
      $response->headers->set('Access-Control-Allow-Origin', '*');
    }
    elseif ($origin !== NULL && in_array($origin, $allowed_origins, TRUE)) {
      $response->headers->set('Access-Control-Allow-Origin', $origin);
      $response->headers->set('Vary', 'Origin');
    }

    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Notification-Key');
    $response->headers->set('Access-Control-Max-Age', '86400');
  }

}
