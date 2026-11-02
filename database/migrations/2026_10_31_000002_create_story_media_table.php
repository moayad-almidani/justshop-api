<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('story_id');
            $table->unique('media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_media');
    }
};
