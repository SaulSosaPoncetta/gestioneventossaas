<?php

namespace App\Mail;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organizer $organizer,
        public User $user,
        public string $roleLabel,
        public string $url,
        public bool $isNew,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Te sumaron al equipo de ' . $this->organizer->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.team-invite-email');
    }
}
