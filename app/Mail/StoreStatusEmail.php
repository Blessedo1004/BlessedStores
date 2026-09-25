<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StoreStatusEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    public $owner_name;

    public $store_name;

    public $status;

    public $suspension_reason;
    /**
     * Create a new message instance.
     */
    public function __construct($owner_name, $store_name, $status, $suspension_reason = null)
    {
        $this->owner_name = $owner_name;
        $this->store_name = $store_name; 
        $this->status = $status;
        if($suspension_reason){
            $this->suspension_reason = $suspension_reason;
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Store Status Email',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.store-status',
        );
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
