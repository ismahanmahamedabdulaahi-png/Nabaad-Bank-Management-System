<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewKycRequest;
use App\Http\Requests\Admin\UploadKycDocumentRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Services\KycService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class KycController extends Controller
{
    public function __construct(private KycService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', KycVerification::class);

        $filters = $request->only(['search', 'status', 'sort', 'direction']);

        return Inertia::render('Admin/Kyc/Index', [
            'kycs'    => $this->service->list($filters),
            'filters' => $filters,
            'stats'   => [
                'pending'  => KycVerification::where('status', 'pending')->count(),
                'approved' => KycVerification::where('status', 'approved')->count(),
                'rejected' => KycVerification::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function show(KycVerification $kyc): Response
    {
        $this->authorize('view', $kyc);

        $kyc->load(['customer', 'verifiedBy', 'documents']);

        return Inertia::render('Admin/Kyc/Show', [
            'kyc' => array_merge($kyc->toArray(), [
                'documents' => $kyc->documents->map(fn ($d) => array_merge($d->toArray(), [
                    'type_label'      => $d->getTypeLabel(),
                    'required_sides'  => \App\Models\KycDocument::requiredSides($d->document_type),
                    'is_complete'     => $d->isComplete(),
                    'view_url_front'  => $d->file_path        ? route('admin.kyc.document', [$d->id, 'side' => 'front'])  : null,
                    'view_url_back'   => $d->file_path_back   ? route('admin.kyc.document', [$d->id, 'side' => 'back'])   : null,
                    'view_url_selfie' => $d->file_path_selfie ? route('admin.kyc.document', [$d->id, 'side' => 'selfie']) : null,
                ])),
            ]),
        ]);
    }

    public function initiate(Customer $customer): RedirectResponse
    {
        $this->authorize('viewAny', KycVerification::class);

        $kyc = $this->service->initiate($customer);

        return redirect()->route('admin.kyc.show', $kyc)
            ->with('success', "KYC record created for {$customer->name}.");
    }

    public function uploadDocument(UploadKycDocumentRequest $request, KycVerification $kyc): RedirectResponse
    {
        $this->authorize('upload', $kyc);

        $this->service->uploadDocument($kyc, $request->validated() + [
            'file_front'  => $request->file('file_front'),
            'file_back'   => $request->file('file_back'),
            'file_selfie' => $request->file('file_selfie'),
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function review(ReviewKycRequest $request, KycVerification $kyc): RedirectResponse
    {
        $data = $request->validated();

        if ($data['action'] === 'approve') {
            $this->authorize('approve', $kyc);
            $this->service->approve($kyc, $data['notes'] ?? null);
            AuditLog::record('kyc_approved', 'kyc_verifications',
                "KYC approved for customer {$kyc->customer->name} ({$kyc->customer->customer_number}).");
            return back()->with('success', 'KYC approved successfully.');
        }

        $this->authorize('reject', $kyc);
        $this->service->reject($kyc, $data['rejection_reason'], $data['notes'] ?? null);
        AuditLog::record('kyc_rejected', 'kyc_verifications',
            "KYC rejected for customer {$kyc->customer->name}: {$data['rejection_reason']}.");
        return back()->with('success', 'KYC rejected.');
    }

    public function reopen(KycVerification $kyc): RedirectResponse
    {
        $this->authorize('approve', $kyc);

        $this->service->reopen($kyc);

        return back()->with('success', 'KYC reopened for re-submission.');
    }

    public function serveDocument(Request $request, KycDocument $document): HttpResponse
    {
        $this->authorize('view', $document->kycVerification);

        $side = $request->query('side', 'front');
        $path = match ($side) {
            'back'   => $document->file_path_back,
            'selfie' => $document->file_path_selfie,
            default  => $document->file_path,
        };

        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'Document image not found.');
        }

        $content = Storage::disk('local')->get($path);
        $ext     = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        $mime    = $mimeMap[$ext] ?? 'application/octet-stream';

        return response($content, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }
}
