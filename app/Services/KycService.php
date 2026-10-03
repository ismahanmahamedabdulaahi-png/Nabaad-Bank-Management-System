<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\KycDocument;
use App\Models\KycVerification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KycService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = KycVerification::with(['customer', 'verifiedBy'])
            ->withCount('documents');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            // Default: pending first, then others
            $query->orderByRaw("FIELD(status, 'pending', 'rejected', 'approved')");
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->whereHas('customer', fn ($q) =>
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('customer_number', 'like', "%{$s}%")
            );
        }

        $sort      = in_array($filters['sort'] ?? '', ['created_at', 'verified_at']) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (!empty($filters['status'])) {
            $query->orderBy($sort, $direction);
        }

        return $query->paginate(15)->withQueryString();
    }

    public function initiate(Customer $customer): KycVerification
    {
        if ($customer->kyc) {
            throw ValidationException::withMessages([
                'customer' => 'This customer already has a KYC record.',
            ]);
        }

        $kyc = KycVerification::create([
            'customer_id' => $customer->id,
            'status'      => 'pending',
        ]);

        AuditLog::record('kyc_initiated', 'kyc', "KYC initiated for {$customer->name} ({$customer->customer_number})");

        return $kyc;
    }

    public function uploadDocument(KycVerification $kyc, array $data): KycDocument
    {
        $type = $data['document_type'];
        $base = "kyc/{$kyc->customer_id}";

        // Delete existing document of same type (and its files)
        KycDocument::where('kyc_verification_id', $kyc->id)
            ->where('document_type', $type)
            ->each(function ($old) {
                foreach (['file_path', 'file_path_back', 'file_path_selfie'] as $col) {
                    if ($old->$col) Storage::disk('local')->delete($old->$col);
                }
                $old->delete();
            });

        $pathFront  = $this->storeFile($data['file_front']  ?? null, $base, "{$type}_front_{$kyc->id}");
        $pathBack   = $this->storeFile($data['file_back']   ?? null, $base, "{$type}_back_{$kyc->id}");
        $pathSelfie = $this->storeFile($data['file_selfie'] ?? null, $base, "{$type}_selfie_{$kyc->id}");

        $doc = KycDocument::create([
            'customer_id'         => $kyc->customer_id,
            'kyc_verification_id' => $kyc->id,
            'document_type'       => $type,
            'document_number'     => $data['document_number'] ?? null,
            'file_path'           => $pathFront,
            'file_path_back'      => $pathBack,
            'file_path_selfie'    => $pathSelfie,
            'expiry_date'         => $data['expiry_date'] ?? null,
        ]);

        AuditLog::record('document_uploaded', 'kyc', "Uploaded {$type} for customer ID {$kyc->customer_id}");

        return $doc;
    }

    private function storeFile($file, string $directory, string $name): ?string
    {
        if (!$file) return null;
        $ext  = $file->getClientOriginalExtension();
        $path = "{$directory}/{$name}.{$ext}";
        Storage::disk('local')->put($path, file_get_contents($file));
        return $path;
    }

    public function approve(KycVerification $kyc, ?string $notes): KycVerification
    {
        if ($kyc->status === 'approved') {
            throw ValidationException::withMessages(['kyc' => 'KYC is already approved.']);
        }

        $docs = $kyc->documents;

        if ($docs->isEmpty()) {
            throw ValidationException::withMessages(['kyc' => 'Cannot approve KYC with no documents uploaded.']);
        }

        foreach ($docs as $doc) {
            if (!$doc->isComplete()) {
                throw ValidationException::withMessages([
                    'kyc' => "The {$doc->getTypeLabel()} document is missing required images (front/back/selfie). Upload all required images before approving.",
                ]);
            }
        }

        $kyc->update([
            'status'      => 'approved',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'notes'       => $notes,
        ]);

        // Activate the customer once KYC is approved
        $kyc->customer()->update(['status' => 'active']);

        AuditLog::record('kyc_approved', 'kyc', "KYC approved for customer ID {$kyc->customer_id} — customer activated", ['status' => 'pending'], ['status' => 'approved']);

        return $kyc->fresh('verifiedBy');
    }

    public function reject(KycVerification $kyc, string $reason, ?string $notes): KycVerification
    {
        if ($kyc->status === 'approved') {
            throw ValidationException::withMessages(['kyc' => 'Cannot reject an already approved KYC.']);
        }

        $kyc->update([
            'status'           => 'rejected',
            'verified_by'      => Auth::id(),
            'verified_at'      => now(),
            'rejection_reason' => $reason,
            'notes'            => $notes,
        ]);

        AuditLog::record('kyc_rejected', 'kyc', "KYC rejected for customer ID {$kyc->customer_id}: {$reason}", ['status' => 'pending'], ['status' => 'rejected']);

        return $kyc->fresh('verifiedBy');
    }

    public function reopen(KycVerification $kyc): KycVerification
    {
        $kyc->update([
            'status'           => 'pending',
            'verified_by'      => null,
            'verified_at'      => null,
            'rejection_reason' => null,
            'notes'            => null,
        ]);

        AuditLog::record('kyc_reopened', 'kyc', "KYC reopened for customer ID {$kyc->customer_id}");

        return $kyc->fresh();
    }
}
