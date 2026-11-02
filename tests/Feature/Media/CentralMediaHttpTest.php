<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Enums\Entitlement\EntitlementStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\BillingAccount;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreEntitlementSnapshot;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CentralMediaHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(PermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function storeWithMerchant(): array
    {
        $user = User::factory()->merchant()->verified()->create([
            'onboarding_step' => \App\Enums\Auth\OnboardingStepEnum::COMPLETED,
        ]);
        $store = Store::factory()->create(['owner_id' => $user->id]);
        $user->stores()->attach($store->id, ['role' => \App\Enums\Store\StoreRoleEnum::STORE_ADMIN->value]);

        $role = Role::firstOrCreate(['name' => RoleEnum::STORE_ADMIN->value, 'guard_name' => 'web']);
        $role->syncPermissions([
            PermissionEnum::CATEGORY_VIEW, PermissionEnum::CATEGORY_UPDATE,
            PermissionEnum::PRODUCT_VIEW, PermissionEnum::PRODUCT_UPDATE,
            PermissionEnum::STORE_VIEW, PermissionEnum::STORE_UPDATE,
        ]);
        $user->assignRole(RoleEnum::STORE_ADMIN->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $account = BillingAccount::create([
            'owner_user_id' => $user->id,
            'billing_email' => "media+{$user->id}@example.test",
            'status' => \App\Enums\Billing\BillingAccountStatusEnum::ACTIVE->value,
        ]);
        StoreEntitlementSnapshot::create([
            'store_id' => $store->id,
            'billing_account_id' => $account->id,
            'entitlement_status' => EntitlementStatusEnum::ENTITLED,
            'features' => [],
            'products_count' => 0,
        ]);
        return [$user, $store];
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg=='
        ));
    }

    private function url(Store $store, string $entity, int $id): string
    {
        return "/api/v1/merchant/stores/{$store->id}/media/attachments/{$entity}/{$id}";
    }

    public function test_authenticated_owner_can_upload_category_icon_and_list_it(): void
    {
        [$user, $store] = $this->storeWithMerchant();
        $category = Category::query()->create(['store_id' => $store->id, 'slug' => 'icon-category']);
        Sanctum::actingAs($user, ['*'], 'merchant');

        $response = $this->post($this->url($store, 'categories', $category->id), [
            'file' => $this->png(), 'role' => 'icon',
        ]);
        $response->assertCreated()->assertJsonPath('data.role', 'icon');
        $this->assertDatabaseHas('category_media', ['category_id' => $category->id, 'role' => 'icon']);
        $this->getJson($this->url($store, 'categories', $category->id))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_store_owner_can_upload_and_replace_store_logo(): void
    {
        [$user, $store] = $this->storeWithMerchant();
        Sanctum::actingAs($user, ['*'], 'merchant');
        $url = $this->url($store, 'stores', $store->id);
        $this->post($url, ['file' => $this->png(), 'role' => 'logo'])->assertCreated();
        $this->post($url, ['file' => $this->png(), 'role' => 'logo'])->assertCreated();
        $this->assertSame(1, $store->mediaLinks()->where('role', 'logo')->count());
    }

    public function test_cross_tenant_uploads_are_hidden_as_not_found(): void
    {
        [$user, $store] = $this->storeWithMerchant();
        $other = Store::factory()->create();
        $category = Category::query()->create(['store_id' => $other->id, 'slug' => 'other-category']);
        Sanctum::actingAs($user, ['*'], 'merchant');
        $this->post($this->url($store, 'categories', $category->id), [
            'file' => $this->png(), 'role' => 'icon',
        ])->assertNotFound();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_upload_rejects_non_media_files(): void
    {
        [$user, $store] = $this->storeWithMerchant();
        Sanctum::actingAs($user, ['*'], 'merchant');
        $this->post($this->url($store, 'stores', $store->id), [
            'file' => UploadedFile::fake()->create('payload.php', 1, 'text/x-php'),
            'role' => 'logo',
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }
}
