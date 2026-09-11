<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\JournalNotificationOutbox;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class JournalWorkflowMail extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly JournalNotificationOutbox $outbox)
    {
        $this->locale($outbox->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->outbox->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.journal.workflow',
            with: [
                'event' => $this->outbox->event,
                'payload' => $this->outbox->payload,
                'locale' => $this->outbox->locale,
            ],
        );
    }
}
