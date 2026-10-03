<?php

namespace App\Notifications;

use App\Models\ComplianceCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplianceCaseOpened extends Notification
{
    use Queueable;

    public function __construct(private readonly ComplianceCase $case) {}

    // Database (in-app) only — deliberately never emailed and never sent to
    // the customer. Tipping off a customer under AML review defeats the
    // point of the review; this is strictly an internal signal to staff
    // holding compliance.manage.
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = match ($this->case->type) {
            'structuring' => 'Possible structuring detected',
            'manual_flag' => 'Transaction flagged as suspicious',
            default       => 'Compliance case opened',
        };

        return [
            'icon'    => 'bi-shield-exclamation',
            'level'   => $this->case->severity === 'high' ? 'danger' : 'warning',
            'title'   => $label,
            'message' => "Customer {$this->case->customer->name} — case #{$this->case->id}.",
            'url'     => route('admin.compliance.show', $this->case->id),
        ];
    }
}
