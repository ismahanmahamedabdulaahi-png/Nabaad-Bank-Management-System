<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class KycDocument extends Model
{
    protected $fillable = [
        'customer_id', 'kyc_verification_id', 'document_type',
        'document_number', 'file_path', 'file_path_back', 'file_path_selfie', 'expiry_date',
    ];

    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function kycVerification(): BelongsTo
    {
        return $this->belongsTo(KycVerification::class);
    }

    // ── Document type helpers ─────────────────────────────────────────────────

    public function getTypeLabel(): string
    {
        return match ($this->document_type) {
            'national_id'     => 'National ID',
            'passport'        => 'Passport',
            'driving_license' => 'Driving License',
            'state_id'        => 'State ID',
            default           => ucwords(str_replace('_', ' ', $this->document_type)),
        };
    }

    /**
     * Returns which image sides are required for each document type.
     * ['front'] | ['front','back'] | ['front','back','selfie']
     */
    public static function requiredSides(string $type): array
    {
        return match ($type) {
            'national_id'     => ['front', 'back', 'selfie'],
            'passport'        => ['front', 'selfie'],
            'driving_license' => ['front', 'back', 'selfie'],
            'state_id'        => ['front', 'back', 'selfie'],
            default           => ['front', 'selfie'],
        };
    }

    public function requiresBack(): bool
    {
        return in_array('back', static::requiredSides($this->document_type));
    }

    public function requiresSelfie(): bool
    {
        return in_array('selfie', static::requiredSides($this->document_type));
    }

    public function isComplete(): bool
    {
        if (!$this->file_path) return false;
        if ($this->requiresBack()   && !$this->file_path_back)   return false;
        if ($this->requiresSelfie() && !$this->file_path_selfie) return false;
        return true;
    }

    // ── File helpers ──────────────────────────────────────────────────────────

    public function isImage(?string $path = null): bool
    {
        $p = $path ?? $this->file_path;
        return in_array(strtolower(pathinfo($p ?? '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    public function exists(?string $path = null): bool
    {
        $p = $path ?? $this->file_path;
        return $p && Storage::disk('local')->exists($p);
    }
}
