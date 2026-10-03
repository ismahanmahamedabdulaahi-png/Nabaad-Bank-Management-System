<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChequesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private Collection $cheques) {}

    public function collection(): Collection { return $this->cheques; }

    public function headings(): array
    {
        return ['Cheque #', 'Book #', 'Account', 'Customer', 'Payee', 'Amount', 'Status', 'Settlement', 'Issue Date', 'Processed Date', 'Processed By'];
    }

    public function map($chq): array
    {
        return [
            $chq->cheque_number,
            $chq->chequeBook?->book_number,
            $chq->account?->account_number,
            $chq->account?->customer?->name,
            $chq->payee_name ?? '',
            $chq->amount ? number_format($chq->amount, 2) : '',
            ucfirst(str_replace('_', ' ', $chq->status)),
            $chq->settlement_type ? ucfirst(str_replace('_', ' ', $chq->settlement_type)) : '',
            $chq->issue_date?->format('d/m/Y'),
            $chq->cleared_at?->format('d/m/Y H:i'),
            $chq->processedBy?->name ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0B2447']]],
        ];
    }

    public function title(): string { return 'Cheques'; }
}
