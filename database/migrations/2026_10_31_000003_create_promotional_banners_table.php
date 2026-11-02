<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotional_banners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('image_media_id')->unique()->constrained('media')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('target_url', 2048)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order', 'id'], 'promo_banners_public_order_idx');
            $table->index(['status', 'starts_at', 'ends_at'], 'promo_banners_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotional_banners');
    }
};
