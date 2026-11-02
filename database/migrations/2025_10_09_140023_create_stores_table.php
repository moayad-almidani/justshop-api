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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('domain')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('timezone')->default('UTC');

            // Deferred foreign key: themes are created later and reference stores.
            $table->foreignId('active_theme_id')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_grandfathered')->default(false);
            $table->timestamp('grandfathered_until')->nullable();

            $table->string('status')
                ->default('active')
                ->index()
                ->comment('StoreStatusEnum: pending_setup|active|suspended|archived|deleted_pending');
            $table->timestamp('status_changed_at')->nullable();
            $table->string('status_changed_by_actor_type')->nullable();
            $table->unsignedBigInteger('status_changed_by_actor_id')->nullable();
            $table->timestamp('setup_completed_at')->nullable();

            $table->string('provisioning_status')
                ->default(\App\Enums\Store\ProvisioningStatusEnum::PENDING->value);
            $table->integer('provisioning_progress')->default(0);
            $table->string('provisioning_current_step')->nullable();
            $table->text('provisioning_message')->nullable();
            $table->boolean('provisioning_retryable')->default(false);
            $table->timestamp('provisioning_started_at')->nullable();
            $table->timestamp('provisioning_last_heartbeat_at')->nullable();
            $table->timestamp('provisioning_completed_at')->nullable();
            $table->timestamp('provisioning_failed_at')->nullable();
            $table->unsignedInteger('provisioning_attempts')->default(0);
            $table->text('provisioning_last_error')->nullable();

            $table->json('address_settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index('active_theme_id');
            $table->index(['is_active', 'status'], 'stores_marketplace_visibility_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};