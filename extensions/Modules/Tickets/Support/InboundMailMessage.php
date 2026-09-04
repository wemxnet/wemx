<?php

namespace Extensions\Modules\Tickets\Support;

final readonly class InboundMailMessage
{
    /**
     * @param  array<int, string>  $recipients
     * @param  array<int, string>  $inReplyTo
     * @param  array<int, string>  $references
     */
    public function __construct(
        public ?string $fromEmail,
        public ?string $fromName,
        public string $subject,
        public string $body,
        public ?string $messageId,
        public array $recipients,
        public array $inReplyTo,
        public array $references,
        public bool $automatic,
    ) {}
}
