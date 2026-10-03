<?php

namespace App\Notifications;

use App\Models\KycDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycDocumentExpiring extends Notification
{
    use Queueable;

    public function __construct(private readonly KycDocument $document) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->document->kycVerification?->customer?->name ?? 'A customer';
        $days = max(0, now()->diffInDays($this->document->expiry_date, false));

        return (new MailMessage)
            ->subject('KYC Document Expiring Soon')
            ->markdown('mail.alert-message', [
                'name'        => $notifiable->name,
                'level'       => 'warning',
                'title'       => 'KYC document expiring',
                'message'     => "{$customerName}'s {$this->document->document_type} expires in {$days} day" . ($days === 1 ? '' : 's') . '.',
                'rows'        => [],
                'extraLines'  => [],
                'url'         => route('admin.kyc.show', $this->document->kyc_verification_id),
                'buttonText'  => 'Review KYC',
                'buttonColor' => 'primary',
                'footerNote'  => 'Please follow up before the document expires.',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $customerName = $this->document->kycVerification?->customer?->name ?? 'A customer';
        $days = max(0, now()->diffInDays($this->document->expiry_date, false));

        return [
            'icon'         => 'bi-person-vcard',
            'level'        => 'warning',
            'title'        => 'KYC document expiring',
            'message'      => "{$customerName}'s {$this->document->document_type} expires in {$days} day" . ($days === 1 ? '' : 's') . '.',
            'url'          => route('admin.kyc.show', $this->document->kyc_verification_id),
            'document_id'  => $this->document->id,
        ];
    }
}
