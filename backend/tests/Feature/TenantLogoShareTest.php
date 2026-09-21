<?php

use App\Models\Tenant;

/**
 * Logo sekolah di navbar.
 *
 * Akar masalahnya: prop Inertia `tenant` diturunkan dari `$user->tenant`,
 * sedangkan akun super admin sengaja TIDAK terikat satu sekolah (tenant_id
 * NULL). Sekolah aktifnya hanya diketahui browser — disimpan di localStorage
 * dan dikirim sebagai header `X-Tenant-ID` pada panggilan API — sehingga
 * server tidak tahu apa-apa soal itu saat merender halaman.
 *
 * Akibatnya `tenant` selalu null untuk super admin, dan logo yang baru saja ia
 * unggah tidak pernah muncul; yang tampil selalu ikon topi wisuda bawaan.
 * Nama sekolah sudah lama punya penanganannya (jatuh ke daftar `tenants`),
 * logonya belum — dan daftar itu mengirim kolom mentah, bukan URL.
 */
beforeEach(function () {
    setupSchoolWorld();

    DB::table('tenants')->where('id', test()->tenantId)->update([
        'logo' => 'logos/sekolah-uji.png',
    ]);
});

test('logo sekolah dikirim sebagai URL siap pakai, bukan path mentah', function () {
    $admin = makeUser('admin.logo', 'admin');

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tenant.logo', '/storage/logos/sekolah-uji.png'));
});

test('daftar sekolah untuk super admin memakai bentuk URL yang sama', function () {
    $super = makeUser('super.logo', 'super_admin', 'super_admin');

    // tenant_id NULL — inilah kondisi nyata akun super admin.
    DB::table('users')->where('id', $super->id)->update(['tenant_id' => null]);

    $this->actingAs($super->fresh())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(function ($page) {
            // Prop `tenant` memang null di sini; itu bukan bug yang diperbaiki,
            // melainkan alasan kenapa fallback ke `tenants` harus ada.
            $page->where('tenant', null);

            $daftar = collect($page->toArray()['props']['tenants']);
            $sekolah = $daftar->firstWhere('id', test()->tenantId);

            expect($sekolah)->not->toBeNull();
            expect($sekolah['logo'])->toBe('/storage/logos/sekolah-uji.png');
        });
});

test('sekolah tanpa logo mengirim null, bukan string kosong', function () {
    DB::table('tenants')->where('id', test()->tenantId)->update(['logo' => null]);

    $admin = makeUser('admin.tanpalogo', 'admin');

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tenant.logo', null));
});
