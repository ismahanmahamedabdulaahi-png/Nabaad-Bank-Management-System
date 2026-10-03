<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LoansExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private Collection $loans) {}

    public function collection(): Collection { return $this->loans; }

    public function headings(): array
    {
        return ['Loan #', 'Customer', 'Account', 'Amount', 'Rate %', 'Tenure (m)', 'Monthly EMI', 'Outstanding', 'Total Paid', 'Status', 'Disbursed Date'];
    }

    public function map($loan): array
    {
        return [
            $loan->loan_number,
            $loan->customer?->name,
            $loan->account?->account_number,
            number_format($loan->amount, 2),
            $loan->interest_rate,
            $loan->tenure_months,
            number_format($loan->monthly_emi, 2),
            number_format($loan->outstanding_balance, 2),
            number_format($loan->total_paid, 2),
            ucfirst(str_replace('_', ' ', $loan->status)),
            $loan->disbursed_at?->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0B2447']]],
        ];
    }

    public function title(): string { return 'Loans'; }
}
