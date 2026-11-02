<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('alt_text')->nullable();
            $table->boolean('is_main')->nullable();
            $table->timestamps();

            $table->unique('media_id');
            $table->unique(['product_id', 'is_main']);
            $table->index(['product_id', 'sort_order']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE product_media ADD CONSTRAINT chk_product_media_main CHECK (is_main IS NULL OR is_main = 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');
    }
};
