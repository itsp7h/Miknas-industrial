<?php

namespace Tests\Feature\Api\Purchase;

use App\Events\GrnDeleted;
use App\Events\GrnSaved;
use App\Events\ItemSaved;
use App\Events\StockMovementRecorded;
use App\Models\GoodsReceiptNote;
use App\Models\GrnDocument;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Settings\ProjectSetting;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoodsReceiptNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Item $item;

    private Warehouse $warehouse;

    private PurchaseOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->supplier = Supplier::factory()->create();
        $this->item = Item::create([
            'item_code' => 'RM-1', 'item_name' => 'Steel Plate',
            'category' => 'raw_material', 'unit_of_measure' => 'KG', 'cost_price' => 10,
        ]);
        $this->warehouse = Warehouse::create(['name' => 'Main Store', 'code' => 'WH-1']);
        $this->order = PurchaseOrder::create([
            'po_number' => 'PO-00001',
            'supplier_id' => $this->supplier->id,
            'po_date' => now(),
            'total_amount' => 100,
            'status' => 'sent',
        ]);
        $this->order->items()->create([
            'item_id' => $this->item->id, 'quantity' => 10, 'rate' => 10,
            'total_amount' => 100, 'quantity_received' => 0,
        ]);
    }

    private function user(): User
    {
        // Admin: this file tests the module, not who may reach it.
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'purchase_order_id' => $this->order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [[
                'item_id' => $this->item->id,
                'purchase_order_item_id' => $this->order->items->first()->id,
                'quantity_received' => 4,
                'unit_cost' => 10,
                'type' => 'inventory',
            ]],
            'lpo_document' => UploadedFile::fake()->create('lpo.pdf', 120, 'application/pdf'),
            'grn_document' => UploadedFile::fake()->image('delivery-note.jpg'),
            'tax_invoice_document' => UploadedFile::fake()->create('invoice.pdf', 80, 'application/pdf'),
        ], $overrides);
    }

    /**
     * A saved receipt; with its LPO, GRN and tax invoice unless $documents is
     * false, since confirming needs all three.
     */
    private function makeGrn(string $status = 'draft', bool $documents = true): GoodsReceiptNote
    {
        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-'.random_int(10000, 99999),
            'purchase_order_id' => $this->order->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now(),
            'status' => $status,
        ]);
        $grn->items()->create([
            'purchase_order_item_id' => $this->order->items->first()->id,
            'item_id' => $this->item->id,
            'quantity_received' => 4,
            'unit_cost' => 10,
            'type' => 'inventory',
        ]);

        if ($documents) {
            foreach (array_keys(GrnDocument::KINDS) as $kind) {
                $grn->documents()->create(['kind' => $kind, 'path' => "grn-documents/{$grn->id}/{$kind}.pdf", 'original_name' => "{$kind}.pdf"]);
            }
        }

        return $grn;
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/purchase/grns')->assertUnauthorized();
    }

    public function test_it_lists_grns_with_the_po_and_supplier(): void
    {
        $this->makeGrn();

        $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/grns')
            ->assertOk()
            ->assertJsonPath('data.0.po_number', 'PO-00001')
            ->assertJsonPath('data.0.supplier_name', $this->supplier->name)
            ->assertJsonPath('data.0.warehouse_name', 'Main Store');
    }

    public function test_form_options_offers_receivable_orders_with_their_lines(): void
    {
        $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/grns/form-options')
            ->assertOk()
            ->assertJsonPath('purchase_orders.0.po_number', 'PO-00001')
            ->assertJsonPath('purchase_orders.0.items.0.item_name', 'Steel Plate')
            ->assertJsonPath('purchase_orders.0.items.0.quantity', '10.00')
            ->assertJsonPath('warehouses.0.name', 'Main Store');
    }

    /** Only sent/partial orders are receivable, matching the Blade create page. */
    public function test_form_options_excludes_orders_that_are_not_receivable(): void
    {
        $this->order->update(['status' => 'draft']);

        $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/grns/form-options')
            ->assertOk()
            ->assertJsonPath('purchase_orders', []);
    }

    public function test_it_creates_a_grn_as_draft_and_broadcasts(): void
    {
        Event::fake([GrnSaved::class]);

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.items.0.quantity_received', '4.00');

        Event::assertDispatched(GrnSaved::class);
    }

    /**
     * The Blade create form collected Notes but its controller omitted the field
     * from create(), so whatever the user typed was silently discarded.
     */
    public function test_it_persists_the_notes_the_blade_controller_dropped(): void
    {
        $response = $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload(['notes' => 'Two crates damaged.']))
            ->assertCreated()
            ->assertJsonPath('data.notes', 'Two crates damaged.');

        $this->assertSame('Two crates damaged.', GoodsReceiptNote::find($response->json('data.id'))->notes);
    }

    /**
     * The show page printed $item->quantity_ordered, which is not a column on
     * grn_items, so PO Qty always read 0.00. It comes from the PO line.
     */
    public function test_show_reports_the_ordered_quantity_from_the_purchase_order_line(): void
    {
        $grn = $this->makeGrn();

        $this->actingAs($this->user())
            ->getJson("/api/v1/purchase/grns/{$grn->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity_ordered', '10.00')
            ->assertJsonPath('data.items.0.quantity_received', '4.00');
    }

    public function test_it_rejects_a_grn_with_no_lines(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload(['items' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    // ------------------------------------------------------------------
    // Confirm — the action no Blade page ever linked to, so stock never moved.
    // ------------------------------------------------------------------

    public function test_confirming_raises_stock_and_records_a_movement(): void
    {
        $grn = $this->makeGrn();

        $this->actingAs($this->user())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(
            '4.00',
            (string) StockLevel::where('item_id', $this->item->id)
                ->where('warehouse_id', $this->warehouse->id)->value('quantity')
        );
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $this->item->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in',
            'reference_type' => 'GoodsReceiptNote',
            'reference_id' => $grn->id,
        ]);
    }

    /**
     * An open Raw Materials or Stock Movements page must show the new stock
     * without a reload — only GrnSaved used to go out.
     */
    public function test_confirming_broadcasts_the_stock_it_moved(): void
    {
        Event::fake([GrnSaved::class, StockMovementRecorded::class, ItemSaved::class]);
        $grn = $this->makeGrn();

        $this->actingAs($this->user())->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")->assertOk();

        Event::assertDispatched(StockMovementRecorded::class, fn ($e) => $e->movement->item_id === $this->item->id);
        Event::assertDispatched(ItemSaved::class, fn ($e) => $e->item->id === $this->item->id);
    }

    public function test_confirming_advances_the_purchase_order_line(): void
    {
        $grn = $this->makeGrn();

        $this->actingAs($this->user())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertOk();

        $this->assertSame('4.00', (string) $this->order->items()->first()->quantity_received);
        // Only 4 of 10 received, so the order is not finished.
        $this->assertSame('sent', $this->order->fresh()->status);
    }

    public function test_the_order_becomes_received_once_every_line_is_met(): void
    {
        $grn = $this->makeGrn();
        $grn->items()->first()->update(['quantity_received' => 10]);

        $this->actingAs($this->user())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertOk();

        $this->assertSame('received', $this->order->fresh()->status);
    }

    public function test_confirming_twice_is_refused(): void
    {
        $grn = $this->makeGrn('confirmed');

        $this->actingAs($this->user())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------

    public function test_it_deletes_a_draft_grn_and_its_lines(): void
    {
        Event::fake([GrnDeleted::class]);
        $grn = $this->makeGrn();

        $this->actingAs($this->user())
            ->deleteJson("/api/v1/purchase/grns/{$grn->id}")
            ->assertOk();

        $this->assertDatabaseMissing('goods_receipt_notes', ['id' => $grn->id]);
        $this->assertDatabaseMissing('grn_items', ['goods_receipt_note_id' => $grn->id]);
        Event::assertDispatched(GrnDeleted::class);
    }

    /** A confirmed GRN has already moved stock; deleting it would orphan those movements. */
    public function test_it_refuses_to_delete_a_confirmed_grn(): void
    {
        $grn = $this->makeGrn('confirmed');

        $this->actingAs($this->user())
            ->deleteJson("/api/v1/purchase/grns/{$grn->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('goods_receipt_notes', ['id' => $grn->id]);
    }

    // ------------------------------------------------------------------
    // Documents — the LPO, GRN and tax invoice a receipt is recorded against.
    // ------------------------------------------------------------------

    public function test_it_stores_the_three_documents_with_the_grn(): void
    {
        $response = $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.documents.0.kind', 'lpo')
            ->assertJsonPath('data.documents.0.label', 'LPO')
            ->assertJsonPath('data.documents.0.name', 'lpo.pdf')
            ->assertJsonPath('data.documents.1.name', 'delivery-note.jpg')
            ->assertJsonPath('data.documents.2.label', 'Tax Invoice')
            ->assertJsonPath('data.documents.2.name', 'invoice.pdf');

        $id = $response->json('data.id');
        // Relative, so the link is right on whichever hostname the page is on.
        $response->assertJsonPath('data.documents.2.url', "/purchase/grns/{$id}/documents/tax_invoice");
        foreach (GrnDocument::where('goods_receipt_note_id', $id)->get() as $document) {
            Storage::disk('local')->assertExists($document->path);
            $this->assertStringStartsWith("grn-documents/{$id}/", $document->path);
        }
        $this->assertSame(3, GrnDocument::where('goods_receipt_note_id', $id)->count());
    }

    /**
     * Paperwork can follow the goods: a receipt saves with any of the three
     * missing and says which it still needs.
     */
    public function test_a_grn_saves_without_a_document_and_says_what_it_needs(): void
    {
        $payload = $this->payload();
        unset($payload['tax_invoice_document']);

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $payload)
            ->assertCreated()
            ->assertJsonPath('data.missing_documents', ['Tax Invoice'])
            ->assertJsonPath('data.documents.2.url', null);

        $none = $this->payload();
        unset($none['lpo_document'], $none['grn_document'], $none['tax_invoice_document']);

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $none)
            ->assertCreated()
            ->assertJsonPath('data.missing_documents', ['LPO', 'GRN', 'Tax Invoice']);
    }

    public function test_the_list_says_what_each_grn_needs(): void
    {
        $payload = $this->payload();
        unset($payload['grn_document']);
        $this->actingAs($this->user())->postJson('/api/v1/purchase/grns', $payload)->assertCreated();
        $this->actingAs($this->user())->postJson('/api/v1/purchase/grns', $this->payload())->assertCreated();

        $rows = collect($this->actingAs($this->user())->getJson('/api/v1/purchase/grns')->assertOk()->json('data'));

        $this->assertEqualsCanonicalizing([['GRN'], []], $rows->pluck('missing_documents')->all());
    }

    public function test_a_missing_document_can_be_uploaded_later(): void
    {
        Event::fake([GrnSaved::class]);
        $user = $this->user();
        $payload = $this->payload();
        unset($payload['tax_invoice_document']);
        $id = $this->actingAs($user)->postJson('/api/v1/purchase/grns', $payload)->json('data.id');
        GoodsReceiptNote::find($id)->update(['status' => 'confirmed']);

        // After confirming too: a tax invoice often arrives after the goods.
        $this->actingAs($user)
            ->postJson("/api/v1/purchase/grns/{$id}/documents", [
                'tax_invoice_document' => UploadedFile::fake()->create('invoice-late.pdf', 30, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Documents uploaded.')
            ->assertJsonPath('data.missing_documents', [])
            ->assertJsonPath('data.documents.2.name', 'invoice-late.pdf');

        Event::assertDispatched(GrnSaved::class);
    }

    public function test_uploading_a_named_document_again_replaces_it(): void
    {
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/api/v1/purchase/grns', $this->payload())->json('data.id');
        $old = GrnDocument::where('goods_receipt_note_id', $id)->where('kind', 'lpo')->firstOrFail();

        $this->actingAs($user)
            ->postJson("/api/v1/purchase/grns/{$id}/documents", [
                'lpo_document' => UploadedFile::fake()->create('lpo-signed.pdf', 30, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('data.documents.0.name', 'lpo-signed.pdf');

        $this->assertSame(1, GrnDocument::where('goods_receipt_note_id', $id)->where('kind', 'lpo')->count());
        Storage::disk('local')->assertMissing($old->path);
    }

    public function test_other_files_added_later_stop_at_five(): void
    {
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/api/v1/purchase/grns', $this->payload([
            'other_documents' => array_map(fn ($i) => UploadedFile::fake()->create("x{$i}.pdf", 5, 'application/pdf'), range(1, 4)),
        ]))->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/v1/purchase/grns/{$id}/documents", ['other_documents' => [
                UploadedFile::fake()->create('five.pdf', 5, 'application/pdf'),
                UploadedFile::fake()->create('six.pdf', 5, 'application/pdf'),
            ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['other_documents' => 'A receipt holds at most 5 other files.']);

        $this->actingAs($user)
            ->postJson("/api/v1/purchase/grns/{$id}/documents", ['other_documents' => [
                UploadedFile::fake()->create('five.pdf', 5, 'application/pdf'),
            ]])
            ->assertOk()
            ->assertJsonCount(5, 'data.other_documents');
    }

    public function test_an_upload_with_no_file_is_refused(): void
    {
        $grn = $this->makeGrn();

        $this->actingAs($this->user())
            ->postJson("/api/v1/purchase/grns/{$grn->id}/documents", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['documents' => 'Choose a file to upload.']);
    }

    public function test_uploading_later_needs_permission_to_edit_goods_receipts(): void
    {
        $grn = $this->makeGrn(documents: false);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('goods-receipts.view');

        $this->actingAs($viewer)
            ->postJson("/api/v1/purchase/grns/{$grn->id}/documents", [
                'lpo_document' => UploadedFile::fake()->create('lpo.pdf', 5, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_a_document_must_be_a_pdf_or_an_image(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload([
                'lpo_document' => UploadedFile::fake()->create('lpo.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('lpo_document');
    }

    public function test_a_document_opens_inline_under_its_uploaded_name(): void
    {
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/api/v1/purchase/grns', $this->payload())->json('data.id');

        $response = $this->actingAs($user)->get("/purchase/grns/{$id}/documents/lpo")->assertOk();

        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('lpo.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_a_document_needs_permission_to_view_goods_receipts(): void
    {
        $id = $this->actingAs($this->user())->postJson('/api/v1/purchase/grns', $this->payload())->json('data.id');

        $this->actingAs(User::factory()->create())
            ->get("/purchase/grns/{$id}/documents/lpo")
            ->assertForbidden();
    }

    public function test_an_unknown_or_missing_document_is_not_found(): void
    {
        $grn = $this->makeGrn(documents: false);

        $this->actingAs($this->user())->get("/purchase/grns/{$grn->id}/documents/lpo")->assertNotFound();
        $this->actingAs($this->user())->get("/purchase/grns/{$grn->id}/documents/passport")->assertNotFound();
    }

    /** A receipt recorded before uploads were asked for lists all three as missing. */
    public function test_an_older_grn_lists_its_documents_as_not_uploaded(): void
    {
        $grn = $this->makeGrn(documents: false);

        $this->actingAs($this->user())
            ->getJson("/api/v1/purchase/grns/{$grn->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data.documents')
            ->assertJsonPath('data.documents.0.url', null)
            ->assertJsonPath('data.documents.2.name', null);
    }

    public function test_deleting_a_grn_deletes_its_documents(): void
    {
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/api/v1/purchase/grns', $this->payload())->json('data.id');

        $this->actingAs($user)->deleteJson("/api/v1/purchase/grns/{$id}")->assertOk();

        $this->assertSame(0, GrnDocument::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ------------------------------------------------------------------
    // Other — optional extra files beside the three.
    // ------------------------------------------------------------------

    public function test_other_files_are_optional(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.other_documents', []);
    }

    public function test_it_stores_several_other_files_and_serves_each(): void
    {
        $user = $this->user();
        $response = $this->actingAs($user)
            ->postJson('/api/v1/purchase/grns', $this->payload(['other_documents' => [
                UploadedFile::fake()->create('packing-list.pdf', 40, 'application/pdf'),
                UploadedFile::fake()->image('damaged-crate.jpg'),
            ]]))
            ->assertCreated()
            ->assertJsonCount(3, 'data.documents')
            ->assertJsonCount(2, 'data.other_documents')
            ->assertJsonPath('data.other_documents.0.name', 'packing-list.pdf')
            ->assertJsonPath('data.other_documents.1.name', 'damaged-crate.jpg');

        $this->assertSame(2, GrnDocument::where('kind', 'other')->count());

        $url = $response->json('data.other_documents.1.url');
        $this->assertStringContainsString('/documents/other/', $url);
        $opened = $this->actingAs($user)->get($url)->assertOk();
        $this->assertStringContainsString('damaged-crate.jpg', $opened->headers->get('Content-Disposition'));
    }

    public function test_at_most_five_other_files_are_taken(): void
    {
        $six = array_map(fn ($i) => UploadedFile::fake()->create("extra-{$i}.pdf", 10, 'application/pdf'), range(1, 6));

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload(['other_documents' => $six]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['other_documents' => 'Attach at most 5 other files.']);
    }

    public function test_an_other_file_must_be_a_pdf_or_an_image(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload(['other_documents' => [
                UploadedFile::fake()->create('notes.exe', 10, 'application/octet-stream'),
            ]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('other_documents.0');
    }

    /** An Other file is served only under the receipt it belongs to. */
    public function test_an_other_file_is_not_found_under_another_grn(): void
    {
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/api/v1/purchase/grns', $this->payload(['other_documents' => [
            UploadedFile::fake()->create('extra.pdf', 10, 'application/pdf'),
        ]]))->json('data.id');
        $document = GrnDocument::where('kind', 'other')->firstOrFail();
        $stranger = $this->makeGrn();

        $this->actingAs($user)->get("/purchase/grns/{$stranger->id}/documents/other/{$document->id}")->assertNotFound();
        // Nor is one of the three named documents reachable as an "other".
        $lpo = GrnDocument::where('goods_receipt_note_id', $id)->where('kind', 'lpo')->firstOrFail();
        $this->actingAs($user)->get("/purchase/grns/{$id}/documents/other/{$lpo->id}")->assertNotFound();
    }

    /** Saving without a document is allowed; completing the receipt is not. */
    public function test_a_grn_missing_a_document_cannot_be_confirmed(): void
    {
        $grn = $this->makeGrn(documents: false);
        $grn->documents()->create(['kind' => 'lpo', 'path' => 'x/lpo.pdf', 'original_name' => 'lpo.pdf']);

        $this->actingAs($this->user())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Upload the GRN and Tax Invoice before confirming this GRN.')
            ->assertJsonPath('missing_documents', ['GRN', 'Tax Invoice']);

        $this->assertSame('draft', $grn->fresh()->status);
        $this->assertSame(0, StockLevel::count());
    }

    private function consumableLine(array $overrides = []): array
    {
        return array_merge([
            'item_id' => $this->item->id,
            'purchase_order_item_id' => $this->order->items->first()->id,
            'quantity_received' => 4,
            'unit_cost' => 10,
            'type' => 'consumable',
        ], $overrides);
    }

    public function test_a_consumable_line_records_the_project_it_is_for(): void
    {
        $project = ProjectSetting::create(['name' => 'Hidd Yard', 'is_active' => true]);

        $id = $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload(['items' => [$this->consumableLine(['project_id' => $project->id])]]))
            ->assertCreated()
            ->assertJsonPath('data.items.0.project_id', $project->id)
            ->assertJsonPath('data.items.0.project_name', 'Hidd Yard')
            ->json('data.id');

        $this->assertDatabaseHas('grn_items', ['goods_receipt_note_id' => $id, 'type' => 'consumable', 'project_id' => $project->id]);
    }

    public function test_a_consumable_line_without_a_project_is_refused(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload(['items' => [$this->consumableLine()]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.project_id' => 'Choose the project this consumable is for.']);
    }

    public function test_an_inventory_line_keeps_no_project(): void
    {
        $project = ProjectSetting::create(['name' => 'Hidd Yard', 'is_active' => true]);
        $payload = $this->payload();
        $payload['items'][0]['project_id'] = $project->id;

        $id = $this->actingAs($this->user())->postJson('/api/v1/purchase/grns', $payload)->assertCreated()->json('data.id');

        $this->assertDatabaseHas('grn_items', ['goods_receipt_note_id' => $id, 'type' => 'inventory', 'project_id' => null]);
    }

    public function test_form_options_offers_the_active_projects(): void
    {
        ProjectSetting::create(['name' => 'Hidd Yard', 'is_active' => true]);
        ProjectSetting::create(['name' => 'Closed Job', 'is_active' => false]);

        $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/grns/form-options')
            ->assertOk()
            ->assertJsonCount(1, 'projects')
            ->assertJsonPath('projects.0.name', 'Hidd Yard');
    }
}
