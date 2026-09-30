<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptNote;
use App\Models\GrnDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Serves a goods receipt's uploaded LPO, GRN or tax invoice.
 *
 * A web route rather than an API one: the detail page opens the file in a new
 * tab, and a plain navigation carries the session cookie but not what Sanctum
 * needs to treat /api as stateful.
 */
class GrnDocumentController extends Controller
{
    public function show(GoodsReceiptNote $grn, string $kind)
    {
        abort_unless(array_key_exists($kind, GrnDocument::KINDS), 404);

        $document = $grn->documents()->where('kind', $kind)->firstOrFail();
        $disk = Storage::disk(GrnDocument::DISK);

        abort_unless($disk->exists($document->path), 404);

        // Inline, so a PDF or a scan opens in the browser's viewer; the name
        // is the one it was uploaded as, for when it is saved from there.
        return $disk->response($document->path, $document->original_name, [], 'inline');
    }

    /** One of the receipt's optional "Other" files, by id. */
    public function showOther(GoodsReceiptNote $grn, GrnDocument $document)
    {
        abort_unless($document->goods_receipt_note_id === $grn->id && $document->kind === GrnDocument::OTHER, 404);

        $disk = Storage::disk(GrnDocument::DISK);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response($document->path, $document->original_name, [], 'inline');
    }
}
