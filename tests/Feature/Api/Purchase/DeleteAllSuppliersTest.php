<?php

namespace Tests\Feature\Api\Purchase;

use App\Events\SupplierDeleted;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Emptying the supplier directory in one action.
 *
 * Its own square, because it is not the same capability as deleting the one
 * supplier you are looking at — and the paperwork still wins: a supplier named
 * by an order, invoice, GRN, payment, RFQ or quote stays where it is.
 */
class DeleteAllSuppliersTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_it_empties_the_directory(): void
    {
        Supplier::factory()->count(3)->create();

        $this->actingAs($this->userWith(['suppliers.view', 'suppliers.delete-all']))
            ->deleteJson('/api/v1/purchase/suppliers')
            ->assertOk()
            ->assertJsonPath('deleted', 3)
            ->assertJsonPath('kept', 0)
            ->assertJsonPath('message', '3 supplier(s) deleted.');

        $this->assertSame(0, Supplier::count());
    }

    /** `purchase_orders.supplier_id` is `restrict`: the order keeps its supplier. */
    public function test_a_supplier_the_paperwork_names_is_kept(): void
    {
        $free = Supplier::factory()->create();
        $used = Supplier::factory()->create();

        PurchaseOrder::create([
            'po_number' => 'LPO-1', 'supplier_id' => $used->id,
            'po_date' => '2026-05-04', 'total_amount' => 0, 'status' => 'draft',
        ]);

        $this->actingAs($this->userWith(['suppliers.delete-all']))
            ->deleteJson('/api/v1/purchase/suppliers')
            ->assertOk()
            ->assertJsonPath('deleted', 1)
            ->assertJsonPath('kept', 1)
            ->assertJsonPath(
                'message',
                '1 supplier(s) deleted. 1 kept, because they have purchase orders, invoices or other records.'
            );

        $this->assertNull(Supplier::find($free->id));
        $this->assertNotNull(Supplier::find($used->id));
    }

    public function test_it_says_so_when_nothing_can_go(): void
    {
        $used = Supplier::factory()->create();
        PurchaseOrder::create([
            'po_number' => 'LPO-1', 'supplier_id' => $used->id,
            'po_date' => '2026-05-04', 'total_amount' => 0, 'status' => 'draft',
        ]);

        $this->actingAs($this->userWith(['suppliers.delete-all']))
            ->deleteJson('/api/v1/purchase/suppliers')
            ->assertOk()
            ->assertJsonPath('deleted', 0)
            ->assertJsonPath('kept', 1)
            ->assertJsonPath('message', 'Nothing was deleted: every supplier has purchase orders, invoices or other records.');

        $this->assertSame(1, Supplier::count());
    }

    public function test_an_empty_directory_is_not_an_error(): void
    {
        $this->actingAs($this->userWith(['suppliers.delete-all']))
            ->deleteJson('/api/v1/purchase/suppliers')
            ->assertOk()
            ->assertJsonPath('deleted', 0)
            ->assertJsonPath('message', 'There are no suppliers to delete.');
    }

    /** Deleting one supplier is a different square from emptying the list. */
    public function test_plain_delete_does_not_carry_it(): void
    {
        Supplier::factory()->count(2)->create();

        $this->actingAs($this->userWith(['suppliers.view', 'suppliers.delete']))
            ->deleteJson('/api/v1/purchase/suppliers')
            ->assertForbidden();

        $this->assertSame(2, Supplier::count());
    }

    public function test_it_is_closed_to_everyone_else(): void
    {
        Supplier::factory()->create();

        $this->deleteJson('/api/v1/purchase/suppliers')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->deleteJson('/api/v1/purchase/suppliers')
            ->assertForbidden();

        $this->assertSame(1, Supplier::count());
    }

    /** Open boards drop exactly the rows that went, rather than reloading. */
    public function test_it_broadcasts_each_deleted_supplier(): void
    {
        Event::fake([SupplierDeleted::class]);
        $suppliers = Supplier::factory()->count(2)->create();

        $this->actingAs($this->userWith(['suppliers.delete-all']))
            ->deleteJson('/api/v1/purchase/suppliers')
            ->assertOk();

        Event::assertDispatchedTimes(SupplierDeleted::class, 2);

        foreach ($suppliers as $supplier) {
            Event::assertDispatched(
                SupplierDeleted::class,
                fn (SupplierDeleted $event) => $event->supplierId === $supplier->id
            );
        }
    }

    public function test_the_square_is_on_the_access_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $grid = collect($this->actingAs($admin)->getJson('/api/v1/settings/users')->assertOk()->json('grid'))
            ->keyBy('tab');

        $this->assertContains(
            'suppliers.delete-all',
            collect($grid['suppliers']['extra'])->pluck('name')->all()
        );
    }
}
