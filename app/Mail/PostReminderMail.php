<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PostReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Post $post,
    ) {}

    public function envelope(): Envelope
    {
        $time = $this->post->scheduled_at?->format('H:i') . ' WIB';

        return new Envelope(
            subject: "⏰ Reminder: Post dijadwalkan pukul {$time}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.post-reminder',
            with: [
                'post' => $this->post,
                'hookPreview' => $this->post->hook ?? substr($this->post->body, 0, 80) . '...',
                'scheduledTime' => $this->post->scheduled_at?->translatedFormat('l, d M Y \p\u\k\u\l H:i') . ' WIB',
                'editUrl' => route('posts.edit', $this->post),
                'pillarName' => $this->post->contentPillar?->name ?? '-',
            ],
        );
    }
}
