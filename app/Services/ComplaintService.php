<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Customer;
use App\Notifications\ComplaintReceived;
use App\Notifications\ComplaintStatusUpdated;
use Illuminate\Support\Facades\DB;

class ComplaintService
{
    public function file(Customer $customer, array $data): Complaint
    {
        $complaint = Complaint::create([
            'complaint_number'        => $this->generateNumber(),
            'customer_id'             => $customer->id,
            'category'                => $data['category'],
            'subject'                 => $data['subject'],
            'description'             => $data['description'],
            'related_transaction_id'  => $data['related_transaction_id'] ?? null,
            'related_account_id'      => $data['related_account_id'] ?? null,
            'status'                  => 'open',
            'priority'                => $data['priority'] ?? 'medium',
        ]);

        AuditLog::record('complaint.filed', 'complaints',
            "Customer {$customer->name} filed complaint {$complaint->complaint_number}: {$complaint->subject}");

        $customer->notify(new ComplaintReceived($complaint));

        return $complaint;
    }

    public function assign(Complaint $complaint, ?int $userId): Complaint
    {
        $complaint->update([
            'assigned_to' => $userId,
            'status'      => ($userId && $complaint->status === 'open') ? 'in_progress' : $complaint->status,
        ]);

        AuditLog::record('complaint.assigned', 'complaints',
            $userId ? "Complaint {$complaint->complaint_number} assigned." : "Complaint {$complaint->complaint_number} unassigned.");

        return $complaint->fresh();
    }

    public function updateStatus(Complaint $complaint, string $status, ?string $resolutionNotes, int $staffId): Complaint
    {
        $complaint->update([
            'status'           => $status,
            'resolution_notes' => $resolutionNotes ?? $complaint->resolution_notes,
            'resolved_at'      => in_array($status, ['resolved', 'closed']) ? now() : null,
        ]);

        AuditLog::record('complaint.status_updated', 'complaints',
            "Complaint {$complaint->complaint_number} marked {$status}.");

        $complaint->customer->notify(new ComplaintStatusUpdated($complaint->fresh()));

        return $complaint->fresh();
    }

    private function generateNumber(): string
    {
        $date = now()->format('Ymd');
        $key  = "complaint_seq_{$date}";

        return DB::transaction(function () use ($date, $key) {
            $current = DB::table('settings')->where('key', $key)->lockForUpdate()->value('value') ?? '0';
            $next    = (int) $current + 1;

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $next, 'group' => 'system', 'label' => "Complaint Sequence {$date}",
                 'type' => 'integer', 'updated_at' => now(), 'created_at' => now()]
            );

            return 'CMP-' . $date . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        });
    }
}
