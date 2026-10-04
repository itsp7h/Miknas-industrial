<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * An LPO is signed twice: Prepared By (whoever issues it) and Approved By.
 *
 * The printed LPO always had an Approved By box, and nothing ever filled it.
 * Now an issued LPO waits for someone holding `pipeline.approve-lpo` to sign
 * it, and only that approval sends it to the supplier. The approver's
 * signature is frozen here the way `prepared_signature` is: a document already
 * sent must not change because its approver later replaced theirs.
 *
 * Nullable and additive, so the release before this one runs on it untouched.
 * LPOs issued before this carry no approval and stay that way.
 *
 * The permission is created here rather than waited for (`AccessSeeder` runs
 * after migrations on deploy), and handed to everyone who signs purchase
 * requests today — the GM — so issued LPOs have someone who can approve them
 * the moment this ships. An Admin can move it from there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->longText('approved_signature')->nullable();
        });

        if (! Schema::hasTable('permissions')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'pipeline.approve-lpo']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // A fresh database has no permissions until the seeder runs, and
        // asking for holders of one that does not exist throws.
        if (Permission::where('name', 'pipeline.approve')->exists()) {
            foreach (User::permission('pipeline.approve')->get() as $user) {
                $user->givePermissionTo('pipeline.approve-lpo');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            Permission::where('name', 'pipeline.approve-lpo')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'approved_signature']);
        });
    }
};
