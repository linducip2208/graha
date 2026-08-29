<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Memberships created before data_scope existed must not inherit the
        // new all-company default. Preserve explicit organization dimensions;
        // unconfigured legacy memberships require explicit project grants.
        DB::table('company_user')->where('data_scope', 'all_company')->whereNotNull('department_id')->update(['data_scope' => 'department']);
        DB::table('company_user')->where('data_scope', 'all_company')->whereNull('department_id')->whereNotNull('branch_id')->update(['data_scope' => 'branch']);
        DB::table('company_user')->where('data_scope', 'all_company')->whereNull('department_id')->whereNull('branch_id')->update(['data_scope' => 'projects']);
    }

    public function down(): void
    {
        // Least-privilege backfill is intentionally not reversed automatically.
    }
};
