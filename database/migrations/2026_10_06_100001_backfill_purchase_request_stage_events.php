<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives the requests that predate the stage history the times their records
 * already hold: created, signed, suppliers selected, RFQs sent, the last quote
 * in, the first award, the last LPO approved (or sent), the last GRN confirmed. A stage
 * with nothing to go on is left out and shows no time.
 */
return new class extends Migration
{
    private const STAGES = ['draft', 'gm_approval', 'rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'complete'];

    public function up(): void
    {
        $users = DB::table('users')->pluck('name', 'id');
        $suppliers = DB::table('suppliers')->pluck('name', 'id');
        $now = now();

        foreach (DB::table('purchase_requests')->get() as $pr) {
            if (DB::table('purchase_request_stage_events')->where('purchase_request_id', $pr->id)->exists()) {
                continue;
            }

            $reached = array_search($pr->stage, self::STAGES, true);
            if ($reached === false) {
                continue;
            }

            $signature = DB::table('purchase_signatures')->where('purchase_request_id', $pr->id)->first();
            $selected = DB::table('rfq_invitations')->where('purchase_request_id', $pr->id)->orderBy('created_at')->first();
            $sent = DB::table('rfq_invitations')->where('purchase_request_id', $pr->id)->whereNotNull('sent_at')->orderBy('sent_at')->first();
            $quote = DB::table('supplier_quotes')->where('purchase_request_id', $pr->id)->orderBy('created_at')->first();
            $award = DB::table('supplier_quote_items')
                ->join('supplier_quotes', 'supplier_quotes.id', '=', 'supplier_quote_items.supplier_quote_id')
                ->where('supplier_quotes.purchase_request_id', $pr->id)
                ->whereNotNull('supplier_quote_items.awarded_at')
                ->orderBy('supplier_quote_items.awarded_at')
                ->first(['supplier_quote_items.awarded_at', 'supplier_quote_items.awarded_by']);
            // Receiving starts once the last LPO is approved; an LPO from before
            // approval existed went out when it was sent, or failing that issued.
            $orders = DB::table('purchase_orders')->where('purchase_request_id', $pr->id)->get();
            $approved = $orders->whereNotNull('approved_at')->sortByDesc('approved_at')->first();
            $released = $approved?->approved_at
                ?? $orders->max('sent_at')
                ?? $orders->max('created_at');
            $received = DB::table('goods_receipt_notes')
                ->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipt_notes.purchase_order_id')
                ->where('purchase_orders.purchase_request_id', $pr->id)
                ->where('goods_receipt_notes.status', 'confirmed')
                ->orderByDesc('goods_receipt_notes.updated_at')
                ->first(['goods_receipt_notes.updated_at']);

            $known = [
                'draft' => [$pr->created_at, $pr->requested_by, $pr->requested_by_name ?: ($users[$pr->requested_by] ?? null)],
                'gm_approval' => [$signature?->signed_at, $signature?->signed_by, null],
                'rfq' => [$selected?->created_at, $selected?->selected_by, null],
                'quoting' => [$sent?->sent_at, $sent?->sent_by, null],
                'comparison' => [$quote?->created_at, null, $quote ? ($suppliers[$quote->supplier_id] ?? null) : null],
                'lpo' => [$award?->awarded_at, $award?->awarded_by, null],
                'receiving' => [$released, $approved?->approved_by, null],
                'complete' => [$received?->updated_at, null, null],
            ];

            foreach (array_slice(self::STAGES, 0, $reached + 1) as $stage) {
                [$at, $userId, $name] = $known[$stage];
                if (! $at) {
                    continue;
                }

                DB::table('purchase_request_stage_events')->insert([
                    'purchase_request_id' => $pr->id,
                    'stage' => $stage,
                    'user_id' => $userId && isset($users[$userId]) ? $userId : null,
                    'actor_name' => $name ?? ($userId ? ($users[$userId] ?? null) : null),
                    'reached_at' => $at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // The rows are history either way; the create migration's down drops them.
    }
};
