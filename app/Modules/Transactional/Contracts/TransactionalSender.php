<?php

namespace App\Modules\Transactional\Contracts;

use App\Modules\Tracking\Models\Message;

/**
 * docs/45-brevo-api-contracts.md §45.9 / §45.7.6 — the single transactional
 * send path (WP-100). Called by `POST /transactional/send` and by WP-91
 * automation `send_email` steps / WP-90 double opt-in mails.
 *
 * `$input` is the snake_case TransactionalSendInput of §45.7.6:
 *   to[] ({email,name?}, 1–50), template_id | html_content (exactly one),
 *   subject?, text_content?, sender_id? | sender{email,name?}, reply_to?,
 *   params?, tags?, cc?, bcc?, headers?, attachments?, scheduled_at?.
 *
 * `$context` keys (all optional):
 *   source             'api' (default) | 'automation' | 'form'
 *   actor              ?App\Modules\Identity\Models\User (audit actor)
 *   api_key_id         ?int  personal_access_tokens.id of the calling key
 *   automation_step_id ?int  stored on messages.automation_step_id when the column exists
 *
 * Throws Illuminate\Validation\ValidationException on invalid input.
 */
interface TransactionalSender
{
    /**
     * Creates the recipients (source `api`, only when absent), renders each
     * message through the RenderingPipeline (`campaign: null`, `is_test=false`)
     * and queues one SendEmailJob per `to` recipient.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context
     * @return list<Message> one queued message per distinct `to` address, in order
     */
    public function send(array $input, array $context = []): array;
}
