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
            $table->integer('total_rows')->default(0)->after('status');
            $table->integer('processed_rows')->default(0)->after('total_rows');
            $table->integer('successful_rows')->default(0)->after('processed_rows');
            $table->integer('failed_rows')->default(0)->after('successful_rows');
            $table->text('error_message')->nullable()->after('failed_rows');
            $table->timestamp('completed_at')->nullable()->after('error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file_headers', function (Blueprint $table) {
            $table->dropColumn([
                'total_rows',
                'processed_rows',
                'successful_rows',
                'failed_rows',
                'error_message',
                'completed_at',
            ]);
        });
    }
};

