<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private Collection $transactions, private array $filters = []) {}

    public function collection(): Collection
    {
        return $this->transactions;
    }

    public function headings(): array
    {
        return ['Date', 'Reference', 'Account', 'Customer', 'Type', 'Amount (USD)', 'Balance After', 'Status', 'Description', 'Processed By'];
    }

    public function map($txn): array
    {
        return [
            $txn->created_at?->format('d/m/Y H:i'),
            $txn->reference,
            $txn->account?->account_number,
            $txn->account?->customer?->name,
            ucfirst($txn->type),
            number_format($txn->amount, 2),
            number_format($txn->balance_after, 2),
            ucfirst($txn->status),
            $txn->description,
            $txn->processedBy?->name,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0B2447']], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Transactions';
    }
}
