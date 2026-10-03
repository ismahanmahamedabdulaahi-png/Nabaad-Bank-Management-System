<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComplaintStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(private readonly Complaint $complaint) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = str_replace('_', ' ', $this->complaint->status);
        $isResolved  = in_array($this->complaint->status, ['resolved', 'closed']);

        return (new MailMessage)
            ->subject('Update on your complaint — ' . $this->complaint->complaint_number)
            ->markdown('mail.alert-message', [
                'name'        => $notifiable->name,
                'level'       => $isResolved ? 'success' : 'info',
                'title'       => 'Complaint update',
                'message'     => "Your complaint is now {$statusLabel}.",
                'rows'        => [
                    ['label' => 'Reference', 'value' => $this->complaint->complaint_number],
                    ['label' => 'Subject',   'value' => $this->complaint->subject],
                    ['label' => 'Status',    'value' => ucfirst($statusLabel)],
                ],
                'extraLines'  => $this->complaint->resolution_notes
                    ? ['**Note from our team:** ' . $this->complaint->resolution_notes]
                    : [],
                'url'         => route('customer.complaints.show', $this->complaint->id),
                'buttonText'  => 'View Complaint',
                'buttonColor' => 'primary',
                'footerNote'  => 'Thank you for banking with NABAAD Bank.',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon'    => 'bi-headset',
            'level'   => in_array($this->complaint->status, ['resolved', 'closed']) ? 'success' : 'info',
            'title'   => 'Complaint update',
            'message' => "Complaint {$this->complaint->complaint_number} is now " . str_replace('_', ' ', $this->complaint->status) . '.',
            'url'     => route('customer.complaints.show', $this->complaint->id),
        ];
    }
}
