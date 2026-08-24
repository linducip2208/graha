<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** UUID immutable untuk company/project/pile: identifier object storage & QR publik. */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->uuid('uuid')->nullable()->index();
        });
        Schema::table('projects', function (Blueprint $t) {
            $t->uuid('uuid')->nullable()->index();
        });
        Schema::table('bored_piles', function (Blueprint $t) {
            $t->uuid('uuid')->nullable()->index();
            $t->uuid('public_uuid')->nullable()->index();
        });

        foreach (DB::table('companies')->whereNull('uuid')->get(['id']) as $row) {
            DB::table('companies')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        }
        foreach (DB::table('projects')->whereNull('uuid')->get(['id']) as $row) {
            DB::table('projects')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        }
        foreach (DB::table('bored_piles')->whereNull('uuid')->get(['id']) as $row) {
            $uuid = (string) Str::uuid();
            DB::table('bored_piles')->where('id', $row->id)->update(['uuid' => $uuid, 'public_uuid' => $uuid]);
        }

        Schema::table('companies', function (Blueprint $t) {
            $t->unique('uuid');
        });
        Schema::table('projects', function (Blueprint $t) {
            $t->unique('uuid');
        });
        Schema::table('bored_piles', function (Blueprint $t) {
            $t->unique('uuid');
            $t->unique('public_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('bored_piles', function (Blueprint $t) {
            $t->dropColumn(['uuid', 'public_uuid']);
        });
        Schema::table('projects', function (Blueprint $t) {
            $t->dropColumn('uuid');
        });
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn('uuid');
        });
    }
};
