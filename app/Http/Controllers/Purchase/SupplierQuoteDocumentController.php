<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\SupplierQuote;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the quotation a supplier attached in the portal, to the staff who
 * may work quotes (`pipeline.manage-quotes`), at any stage of the request.
 *
 * A web route rather than an API one, for the reason GrnDocumentController
 * gives: the quotes page opens it in a new tab, and a plain navigation carries
 * the session cookie but not what Sanctum needs to treat /api as stateful.
 */
class SupplierQuoteDocumentController extends Controller
{
    public function show(SupplierQuote $supplierQuote)
    {
        // The permission, not the policy's stage window: a supplier's own
        // quotation is still worth opening once the request has moved on to
        // its LPO, the goods and the invoice.
        abort_unless(request()->user()?->can('pipeline.manage-quotes'), 403);

        $disk = Storage::disk(SupplierQuote::DISK);

        abort_unless($supplierQuote->document_path && $disk->exists($supplierQuote->document_path), 404);

        // Inline, so a PDF or a scan opens in the browser's viewer, under the
        // name the supplier uploaded it as.
        return $disk->response($supplierQuote->document_path, $supplierQuote->document_name, [], 'inline');
    }
}
