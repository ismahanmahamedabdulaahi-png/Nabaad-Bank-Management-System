<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComplaintReceived extends Notification
{
    use Queueable;

    public function __construct(private readonly Complaint $complaint) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We received your complaint — ' . $this->complaint->complaint_number)
            ->markdown('mail.alert-message', [
                'name'        => $notifiable->name,
                'level'       => 'info',
                'title'       => 'Complaint received',
                'message'     => "Thanks for letting us know — we've logged your complaint and a member of our team will review it.",
                'rows'        => [
                    ['label' => 'Reference', 'value' => $this->complaint->complaint_number],
                    ['label' => 'Subject',   'value' => $this->complaint->subject],
                ],
                'extraLines'  => [],
                'url'         => route('customer.complaints.show', $this->complaint->id),
                'buttonText'  => 'View Complaint',
                'buttonColor' => 'primary',
                'footerNote'  => 'A member of our team will review this and get back to you.',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon'    => 'bi-headset',
            'level'   => 'info',
            'title'   => 'Complaint received',
            'message' => "Complaint {$this->complaint->complaint_number} ({$this->complaint->subject}) has been logged.",
            'url'     => route('customer.complaints.show', $this->complaint->id),
        ];
    }
}
