<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Test branding halaman login — logo & nama sekolah yang tampil di /login.
 * Fitur ini memungkinkan super admin memilih sekolah mana yang logo & namanya
 * ditampilkan di halaman login, tanpa mengubah status aktif sekolah lain.
 */

beforeEach(function () {
    setupSchoolWorld();

    // Create a second school for multi-school testing
    $this->school2Id = Str::uuid()->toString();
    DB::table('tenants')->insert([
        'id' => $this->school2Id,
        'name' => 'SMP Negeri 2',
        'slug' => 'smp-negeri-2',
        'email' => 'smp2@sekolah.test',
        'status' => 'active',
        'logo' => 'logos/school2.png',
        'is_login_brand' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->superAdmin = makeUser('super.admin.branding', 'super_admin', 'super_admin');
    $this->regularAdmin = makeUser('regular.admin.branding', 'admin', 'admin');
});

// ---------- Halaman Login ----------

test('halaman login menampilkan branding default saat tidak ada sekolah dipilih', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page
            ->component('auth/Login')
            ->where('branding', null)
    );
});

test('halaman login menampilkan branding sekolah saat dipilih', function () {
    // Set school as login brand
    DB::table('tenants')
        ->where('id', $this->tenantId)
        ->update(['is_login_brand' => true, 'logo' => 'logos/school1.png']);
    Cache::forget(Tenant::LOGIN_BRANDING_CACHE_KEY);

    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page
            ->component('auth/Login')
            ->has('branding')
            ->where('branding.name', 'Sekolah Uji')
    );
});

// ---------- API: Super Admin ----------

test('super admin bisa mendapatkan login brand saat ini', function () {
    DB::table('tenants')
        ->where('id', $this->tenantId)
        ->update(['is_login_brand' => true]);
    Cache::forget(Tenant::LOGIN_BRANDING_CACHE_KEY);

    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/admin/login-brand');

    $response->assertOk()
        ->assertJsonPath('data.id', $this->tenantId)
        ->assertJsonPath('data.name', 'Sekolah Uji');
});

test('super admin bisa mengatur login brand', function () {
    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson('/api/v1/admin/login-brand', [
            'school_id' => $this->school2Id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->school2Id)
        ->assertJsonPath('data.name', 'SMP Negeri 2');

    expect(DB::table('tenants')->where('id', $this->school2Id)->value('is_login_brand'))->toBeTrue();
    expect(DB::table('tenants')->where('id', $this->tenantId)->value('is_login_brand'))->toBeFalse();
});

test('super admin bisa menghapus login brand (kembali ke bawaan sistem)', function () {
    DB::table('tenants')
        ->where('id', $this->tenantId)
        ->update(['is_login_brand' => true]);

    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson('/api/v1/admin/login-brand', [
            'school_id' => null,
        ]);

    $response->assertOk()
        ->assertJsonPath('data', null);

    expect(DB::table('tenants')->where('id', $this->tenantId)->value('is_login_brand'))->toBeFalse();
});

test('mengatur brand baru otomatis menghapus brand lama', function () {
    // Set first school as brand
    $tenant1 = Tenant::find($this->tenantId);
    $tenant1->setAsLoginBrand();
    expect($tenant1->fresh()->is_login_brand)->toBeTrue();

    // Set second school as brand
    $tenant2 = Tenant::find($this->school2Id);
    $tenant2->setAsLoginBrand();

    expect(DB::table('tenants')->where('id', $this->tenantId)->value('is_login_brand'))->toBeFalse();
    expect(DB::table('tenants')->where('id', $this->school2Id)->value('is_login_brand'))->toBeTrue();
});

// ---------- Otorisasi ----------

test('admin biasa tidak bisa mengakses endpoint login brand', function () {
    $this->actingAs($this->regularAdmin, 'sanctum')
        ->getJson('/api/v1/admin/login-brand')
        ->assertForbidden();

    $this->actingAs($this->regularAdmin, 'sanctum')
        ->putJson('/api/v1/admin/login-brand', ['school_id' => $this->tenantId])
        ->assertForbidden();
});

test('guest tidak bisa mengakses endpoint login brand', function () {
    $this->getJson('/api/v1/admin/login-brand')
        ->assertUnauthorized();

    $this->putJson('/api/v1/admin/login-brand', ['school_id' => $this->tenantId])
        ->assertUnauthorized();
});

// ---------- Caching ----------

test('branding di-cache dan di-invalidate saat berubah', function () {
    Cache::flush();

    DB::table('tenants')
        ->where('id', $this->tenantId)
        ->update(['is_login_brand' => true]);

    // First call should cache
    $branding1 = Tenant::getLoginBranding();
    expect($branding1['name'])->toBe('Sekolah Uji');

    // Update directly in DB (bypass model)
    DB::table('tenants')->where('id', $this->tenantId)->update(['name' => 'Nama Baru']);

    // Should still return cached value
    $branding2 = Tenant::getLoginBranding();
    expect($branding2['name'])->toBe('Sekolah Uji');

    // After clearing cache, should return new value
    Cache::forget(Tenant::LOGIN_BRANDING_CACHE_KEY);
    $branding3 = Tenant::getLoginBranding();
    expect($branding3['name'])->toBe('Nama Baru');
});

test('cache di-invalidate saat brand berubah via model', function () {
    Cache::flush();

    $tenant1 = Tenant::find($this->tenantId);
    $tenant1->setAsLoginBrand();
    expect(Tenant::getLoginBranding()['name'])->toBe('Sekolah Uji');

    $tenant2 = Tenant::find($this->school2Id);
    $tenant2->setAsLoginBrand();
    expect(Tenant::getLoginBranding()['name'])->toBe('SMP Negeri 2');

    Tenant::clearLoginBrand();
    expect(Tenant::getLoginBranding())->toBeNull();
});

// ---------- Daftar Sekolah ----------

test('daftar sekolah menyertakan is_login_brand', function () {
    DB::table('tenants')
        ->where('id', $this->tenantId)
        ->update(['is_login_brand' => true]);

    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/v1/admin/schools');

    $response->assertOk();

    $schools = collect($response->json('data'));
    $school1Data = $schools->firstWhere('id', $this->tenantId);
    $school2Data = $schools->firstWhere('id', $this->school2Id);

    expect($school1Data['is_login_brand'])->toBeTrue();
    expect($school2Data['is_login_brand'])->toBeFalse();
});

test('menghapus sekolah yang di-brand otomatis menghapus brand', function () {
    DB::table('tenants')
        ->where('id', $this->school2Id)
        ->update(['is_login_brand' => true]);
    Cache::forget(Tenant::LOGIN_BRANDING_CACHE_KEY);

    expect(Tenant::getLoginBranding()['name'])->toBe('SMP Negeri 2');

    // Delete the branded school (acting from the other school)
    $this->actingAs($this->superAdmin, 'sanctum')
        ->withHeader('X-Tenant-ID', $this->tenantId)
        ->deleteJson("/api/v1/admin/schools/{$this->school2Id}");

    expect(Tenant::getLoginBranding())->toBeNull();
});
