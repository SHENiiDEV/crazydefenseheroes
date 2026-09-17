<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class InvoiceController
{
    public function show(Request $request, Invoice $invoice): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($invoice->user_id === $user->id, 404);
        abort_unless($invoice->pdf_path && Storage::disk(config('invoice.storage_disk'))->exists($invoice->pdf_path), 404);

        return response(
            Storage::disk(config('invoice.storage_disk'))->get($invoice->pdf_path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.pdf"',
            ],
        );
    }
}
