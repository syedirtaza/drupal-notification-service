<?php

namespace Drupal\notification_service\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves Swagger UI and the OpenAPI contract.
 */
final class DocsController extends ControllerBase {

  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly string $appRoot,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('extension.list.module'),
      $container->getParameter('app.root'),
    );
  }

  public function swagger(): Response {
    $openapi_url = Url::fromRoute('notification_service.openapi')->toString();
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Notification API Docs</title><link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css"></head><body><div id="swagger-ui"></div><script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script><script>window.onload=function(){SwaggerUIBundle({url:' . json_encode($openapi_url) . ',dom_id:"#swagger-ui"});};</script></body></html>';
    return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
  }

  public function openApi(): Response {
    $path = $this->appRoot . '/' . $this->moduleExtensionList->getPath('notification_service') . '/openapi.yml';
    return new Response((string) file_get_contents($path), 200, ['Content-Type' => 'application/yaml; charset=UTF-8']);
  }

}
