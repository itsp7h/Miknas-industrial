<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One of the three documents a goods receipt is recorded against. The file
 * is on the private `local` disk at `path`; `original_name` is what the user
 * uploaded it as, and what it downloads as.
 */
class GrnDocument extends Model
{
    /** kind => label, in the order the form and the detail page show them. */
    public const KINDS = [
        'lpo' => 'LPO',
        'grn' => 'GRN',
        'tax_invoice' => 'Tax Invoice',
    ];

    /** Optional extra files, any number up to MAX_OTHER; not in KINDS. */
    public const OTHER = 'other';

    public const MAX_OTHER = 5;

    public const DISK = 'local';

    protected $fillable = ['goods_receipt_note_id', 'kind', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    public function goodsReceiptNote()
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }
}
