<?php

namespace App\Mail;

use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email as SymfonyEmail;

class CustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The email instance.
     */
    public Email $email;

    /**
     * Create a new message instance.
     */
    public function __construct(Email $email)
    {
        $this->email = $email;

        // Set the default configuration for the mailer
        config([
            'app.name' => settings('app_name', 'My Application'),
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $data = $this->email->data ?? [];
        $replyTo = $data['reply_to'] ?? null;

        return new Envelope(
            subject: $this->email->subject,
            replyTo: is_string($replyTo) && $replyTo !== '' ? [$replyTo] : [],
            using: [
                function (SymfonyEmail $message) use ($data) {
                    $headers = $message->getHeaders();

                    if (! empty($data['message_id'])) {
                        $headers->remove('Message-ID');
                        $headers->addIdHeader('Message-ID', $data['message_id']);
                    }

                    if (! empty($data['in_reply_to'])) {
                        $headers->remove('In-Reply-To');
                        $headers->addIdHeader('In-Reply-To', $data['in_reply_to']);
                    }
                },
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $theme = EmailTheme::resolve($this->email->theme);
        $name = $this->email->user ? $this->email->user->username : null;
        $button = [
            'text' => $this->email->button_text ?? null,
            'url' => $this->email->button_url ?? null,
        ];

        if ($theme->usesMarkdown()) {
            return new Content(
                markdown: $theme->htmlView(),
                with: [
                    'name' => $name,
                    'body' => implode("\n", array_map(strval(...), $this->email->lines ?? [])),
                    'markdownTable' => self::markdownTable($this->email->table),
                    'button' => $button,
                ],
            );
        }

        $text = $this->plainText();

        return new Content(
            view: $theme->htmlView(),
            text: $theme->textView(),
            with: [
                'name' => $name,
                'body' => (string) Str::markdown($text),
                'text' => $text,
                'subject' => $this->email->subject,
                'button' => $button,
            ],
        );
    }

    /**
     * Plain-text message, including a markdown table when one was provided.
     */
    private function plainText(): string
    {
        $lines = implode("\n", array_map(strval(...), $this->email->lines ?? []));
        $table = self::markdownTable($this->email->table);

        if ($table === '') {
            return $lines;
        }

        if ($lines === '') {
            return $table;
        }

        return $lines."\n\n".$table;
    }

    /**
     * Use the email theme's stylesheet and mail components when it provides them.
     */
    protected function markdownRenderer(): Markdown
    {
        $emailTheme = EmailTheme::resolve($this->email->theme);

        return tap(app(Markdown::class), function (Markdown $markdown) use ($emailTheme) {
            $paths = [];

            if ($emailTheme->mailComponentsPath() !== null) {
                $paths[] = $emailTheme->mailComponentsPath();
            }

            $markdown->loadComponentsFrom($paths);
            $markdown->theme($emailTheme->cssView() ?? (string) config('mail.markdown.theme', 'default'));
        });
    }

    /**
     * Convert a columns/rows payload into a markdown table.
     *
     * @param  array<string, mixed>|null  $table
     */
    public static function markdownTable(?array $table): string
    {
        $columns = $table['columns'] ?? null;
        $rows = $table['rows'] ?? null;

        if (! is_array($columns) || $columns === [] || ! is_array($rows) || $rows === []) {
            return '';
        }

        $escape = static function (mixed $value): string {
            $value = str_replace(['|', "\r\n", "\n", "\r"], ['\\|', ' ', ' ', ''], (string) $value);

            return $value === '' ? ' ' : $value;
        };

        $columns = array_values(array_map($escape, $columns));
        $header = '| '.implode(' | ', $columns).' |';
        $divider = '| '.implode(' | ', array_fill(0, count($columns), '---')).' |';

        $markdownRows = [];

        foreach ($rows as $row) {
            $cells = array_map($escape, array_values((array) $row));
            $cells = array_slice(array_pad($cells, count($columns), ' '), 0, count($columns));
            $markdownRows[] = '| '.implode(' | ', $cells).' |';
        }

        return $header."\n".$divider."\n".implode("\n", $markdownRows);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
