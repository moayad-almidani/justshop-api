<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotional_banner_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotional_banner_id')
                ->constrained('promotional_banners')
                ->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->string('button_text')->nullable();
            $table->timestamps();

            $table->unique(
                ['promotional_banner_id', 'locale'],
                'promo_banner_translations_banner_locale_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotional_banner_translations');
    }
};
