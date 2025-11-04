<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('file_headers', function (Blueprint $table) {
            $table->string('file_hash')->nullable()->after('file_name');
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file_headers', function (Blueprint $table) {
            $table->dropIndex(['file_hash']);
            $table->dropColumn('file_hash');
        });
    }
};

