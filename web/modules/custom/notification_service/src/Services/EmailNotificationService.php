<?php

namespace Drupal\notification_service\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;

/**
 * Sends and records email notifications for API callers.
 */
final class EmailNotificationService {

  public function __construct(
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
    private readonly Settings $settings,
  ) {}

  /**
   * Validates and sends one email notification request.
   */
  public function send(array $payload): array {
    $to = strtolower(trim((string) ($payload['to'] ?? '')));
    $subject = trim((string) ($payload['subject'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $metadata = is_array($payload['metadata'] ?? NULL) ? $payload['metadata'] : [];

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('The to field must be a valid email address.');
    }
    if ($subject === '') {
      throw new \InvalidArgumentException('The subject field is required.');
    }
    if ($body === '') {
      throw new \InvalidArgumentException('The body field is required.');
    }

    $message_id = $this->createMessageId();
    $from = (string) $this->setting('notification_service_from_email', 'no-reply@example.com');

    $result = $this->mailManager->mail('notification_service', 'generic_email', $to, $this->languageManager->getDefaultLanguage()->getId(), [
      'from' => $from,
      'reply_to' => (string) ($payload['replyTo'] ?? $from),
      'subject' => $subject,
      'body' => $body,
      'metadata' => $metadata,
      'message_id' => $message_id,
    ], $from, TRUE);

    $status = !empty($result['result']) ? 'accepted' : 'failed';
    $record = [
      'id' => $message_id,
      'type' => 'email',
      'to' => $to,
      'subject' => $subject,
      'status' => $status,
      'metadata' => $metadata,
      'createdAt' => $this->time->getRequestTime(),
    ];
    $this->appendLog($record);

    return $record;
  }

  /**
   * Returns recent notification records for debugging local integrations.
   */
  public function recent(int $limit = 20): array {
    return array_slice(array_reverse($this->state->get('notification_service.email_log', [])), 0, max(1, min($limit, 100)));
  }

  private function appendLog(array $record): void {
    $records = $this->state->get('notification_service.email_log', []);
    $records[] = $record;
    $this->state->set('notification_service.email_log', array_slice($records, -200));
  }

  private function createMessageId(): string {
    return 'email_' . bin2hex(random_bytes(12));
  }

  private function setting(string $name, mixed $default): mixed {
    $env_value = getenv(strtoupper($name));
    if ($env_value !== FALSE && $env_value !== '') {
      return $env_value;
    }

    return $this->settings->get($name, $default);
  }

}
