<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Backlog gelombang 9: warranty quotation (bid comparison), state ledger stok (damaged/obsolete/in-transit). */
    public function up(): void
    {
        Schema::table('vendor_quotations', function (Blueprint $t) {
            $t->unsignedSmallInteger('warranty_months')->nullable()->after('payment_term');
        });
        Schema::table('stock_balances', function (Blueprint $t) {
            $t->decimal('damaged_quantity', 20, 4)->default('0')->after('reserved_quantity');
            $t->decimal('obsolete_quantity', 20, 4)->default('0')->after('damaged_quantity');
            $t->decimal('in_transit_quantity', 20, 4)->default('0')->after('obsolete_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_quotations', function (Blueprint $t) {
            $t->dropColumn('warranty_months');
        });
        Schema::table('stock_balances', function (Blueprint $t) {
            $t->dropColumn(['damaged_quantity', 'obsolete_quantity', 'in_transit_quantity']);
        });
    }
};
