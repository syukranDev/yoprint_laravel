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
        Schema::create('file_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_header_id')->constrained('file_headers')->onDelete('cascade');
            $table->string('UNIQUE_KEY')->nullable(false);
            $table->text('PRODUCT_TITLE')->nullable(false);
            $table->text('PRODUCT_DESCRIPTION')->nullable(false);
            $table->string('STYLE#')->nullable();
            $table->string('SANMAR_MAINFRAME_COLOR')->nullable();
            $table->string('SIZE')->nullable();
            $table->string('COLOR_NAME')->nullable();
            $table->decimal('PIECE_PRICE', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_details');
    }
};

