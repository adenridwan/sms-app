<?php

use App\Infrastructure\Persistence\Eloquent\Attendance\CardTemplate;
use Illuminate\Support\Str;

/**
 * Regression coverage for ATTENDANCE-PLAN.md Fase 5 (Template Editor Kartu ID):
 * GET tanpa template tersimpan mengembalikan default, PUT menyimpan layout
 * kustom, DELETE mereset ke default, validasi menolak payload tak valid,
 * dan template satu tenant tidak bocor ke tenant lain.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.card', 'admin', 'admin');
});

test('get tanpa template tersimpan mengembalikan layout default', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/card-templates/student')
        ->assertOk();

    expect($response->json('data.is_custom'))->toBeFalse();
    expect($response->json('data.layout'))->toBe(CardTemplate::defaultLayout());
});

test('put menyimpan layout kustom lalu get mengembalikannya', function () {
    $customLayout = CardTemplate::defaultLayout();
    $customLayout['cardBackground'] = '#000000';
    $customLayout['elements']['name']['x'] = 50;

    $this->actingAs($this->admin, 'sanctum')
        ->putJson('/api/v1/attendance/card-templates/student', [
            'layout_json' => $customLayout,
        ])
        ->assertOk()
        ->assertJsonPath('data.is_custom', true)
        ->assertJsonPath('data.layout.cardBackground', '#000000');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/card-templates/student')
        ->assertOk();

    expect($response->json('data.is_custom'))->toBeTrue();
    expect($response->json('data.layout.elements.name.x'))->toBe(50);
});

test('delete mengembalikan template ke default', function () {
    CardTemplate::create([
        'tenant_id' => $this->tenantId,
        'type' => 'student',
        'layout_json' => array_merge(CardTemplate::defaultLayout(), ['cardBackground' => '#123456']),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson('/api/v1/attendance/card-templates/student')
        ->assertOk()
        ->assertJsonPath('data.is_custom', false);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/card-templates/student')
        ->assertOk();

    expect($response->json('data.is_custom'))->toBeFalse();
    expect($response->json('data.layout'))->toBe(CardTemplate::defaultLayout());
});

test('layout_json bukan array ditolak validasi', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson('/api/v1/attendance/card-templates/student', [
            'layout_json' => 'not-an-array',
        ])
        ->assertStatus(422);
});

test('layout_json tanpa elements ditolak validasi', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson('/api/v1/attendance/card-templates/student', [
            'layout_json' => ['cardBackground' => '#ffffff'],
        ])
        ->assertStatus(422);
});

test('type selain student/teacher ditolak 404', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/card-templates/invalid')
        ->assertStatus(404);
});

test('template tenant lain tidak terlihat oleh tenant ini', function () {
    $otherTenantId = Str::uuid()->toString();
    \Illuminate\Support\Facades\DB::table('tenants')->insert([
        'id' => $otherTenantId,
        'name' => 'Sekolah Lain',
        'slug' => 'sekolah-lain',
        'email' => 'lain@sekolah.test',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    CardTemplate::create([
        'tenant_id' => $otherTenantId,
        'type' => 'student',
        'layout_json' => array_merge(CardTemplate::defaultLayout(), ['cardBackground' => '#abcdef']),
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/attendance/card-templates/student')
        ->assertOk();

    expect($response->json('data.is_custom'))->toBeFalse();
    expect($response->json('data.layout.cardBackground'))->toBe('#ffffff');
});
