<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Invoices\InvoiceTheme;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;

class PaymentsController extends Controller
{
    public function index()
    {
        return view('admin::payments.index');
    }

    public function create()
    {
        return view('admin::payments.create');
    }

    public function edit(Payment $payment)
    {
        return view('admin::payments.edit', compact('payment'));
    }

    public function downloadInvoicePdf(Payment $payment)
    {
        $theme = InvoiceTheme::default();

        $pdf = Pdf::loadView($theme->view(), $theme->viewData($payment));

        return $pdf->download('invoice-'.($payment->invoice_id ?: $payment->id).'.pdf');
    }
}
