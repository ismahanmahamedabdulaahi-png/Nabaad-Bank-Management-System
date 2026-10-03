<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\ChequeBook;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChequeController extends Controller
{
    public function index(): Response
    {
        $customer   = Auth::guard('customer')->user();
        $accountIds = $customer->accounts()->pluck('id');

        $books = ChequeBook::with(['account:id,account_number', 'cheques'])
            ->whereIn('account_id', $accountIds)
            ->latest()
            ->get()
            ->map(fn ($book) => [
                'id'             => $book->id,
                'book_number'    => $book->book_number,
                'account_number' => $book->account?->account_number,
                'total_leaves'   => $book->total_leaves,
                'used_leaves'    => $book->used_leaves,
                'status'         => $book->status,
                'issued_at'      => $book->issued_at,
                'cheques'        => $book->cheques->map(fn ($c) => [
                    'cheque_number'  => $c->cheque_number,
                    'status'         => $c->status,
                    'amount'         => $c->amount,
                    'payee_name'     => $c->payee_name,
                    'settlement_type'=> $c->settlement_type,
                    'issue_date'     => $c->issue_date,
                ]),
            ]);

        return Inertia::render('Customer/Cheques/Index', [
            'books' => $books,
        ]);
    }
}
