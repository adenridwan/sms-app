<?php

use App\Infrastructure\Persistence\Eloquent\Notification\Announcement;

/**
 * Umpan ikon lonceng di navbar (`announcements/feed`).
 *
 * Dibuat terpisah dari `index()` dengan sengaja: `index()` melonggarkan filter
 * untuk pemegang `announcements.manage`, sehingga draft dan pengumuman
 * kedaluwarsa ikut terbawa. Itu wajar untuk halaman kelola, tapi salah untuk
 * lonceng notifikasi — pengurus akan melihat angka "belum dibaca" dari
 * pengumuman yang belum ia terbitkan sendiri.
 */
beforeEach(function () {
    setupSchoolWorld();

    $this->admin = makeUser('admin.pengumuman', 'admin');
    $this->guru = makeUser('guru.pengumuman', 'guru', 'teacher');
});

function makeAnnouncement(array $overrides = []): Announcement
{
    return Announcement::create(array_merge([
        'tenant_id' => test()->tenantId,
        'created_by' => test()->admin->id,
        'title' => 'Pengumuman ' . fake()->unique()->numerify('###'),
        'content' => 'Isi pengumuman.',
        'priority' => 'normal',
        'is_pinned' => false,
        'is_published' => true,
        'send_notification' => false,
        'publish_at' => now()->subHour(),
    ], $overrides));
}

test('umpan hanya memuat pengumuman yang sudah tayang', function () {
    $tayang = makeAnnouncement(['title' => 'Sudah tayang']);
    makeAnnouncement(['title' => 'Masih draft', 'is_published' => false]);
    makeAnnouncement(['title' => 'Belum waktunya', 'publish_at' => now()->addDay()]);
    makeAnnouncement(['title' => 'Sudah lewat', 'expires_at' => now()->subMinute()]);

    $response = $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed')
        ->assertOk();

    $judul = collect($response->json('data.items'))->pluck('title');

    expect($judul)->toHaveCount(1);
    expect($judul->first())->toBe($tayang->title);
});

test('draft tetap tersembunyi dari umpan walau yang membuka adalah admin', function () {
    // Inti perbedaan feed vs index — index() memperlihatkan draft ke pengurus.
    makeAnnouncement(['title' => 'Draft admin', 'is_published' => false]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 0)
        ->assertJsonCount(0, 'data.items');
});

test('jumlah belum dibaca dihitung per pengguna dan berkurang setelah ditandai', function () {
    $a = makeAnnouncement();
    makeAnnouncement();

    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 2);

    $this->actingAs($this->guru, 'sanctum')
        ->postJson("/api/v1/notifications/announcements/{$a->id}/read")
        ->assertOk();

    $response = $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);

    // is_read harus boolean sungguhan, bukan 0/1 dari withExists().
    $row = collect($response->json('data.items'))->firstWhere('id', $a->id);
    expect($row['is_read'])->toBeTrue();

    // Dibaca satu orang tidak membuatnya terbaca untuk orang lain.
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 2);
});

test('pengumuman tersemat muncul lebih dulu', function () {
    makeAnnouncement(['title' => 'Biasa', 'publish_at' => now()->subMinute()]);
    makeAnnouncement(['title' => 'Tersemat', 'is_pinned' => true, 'publish_at' => now()->subDay()]);

    $response = $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed')
        ->assertOk();

    expect($response->json('data.items.0.title'))->toBe('Tersemat');
});

test('umpan terbuka untuk pengguna login tanpa izin kelola', function () {
    makeAnnouncement();

    // Guru tidak punya announcements.manage, tapi lonceng ada di setiap halaman.
    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed')
        ->assertOk();
});

test('tamu ditolak', function () {
    $this->getJson('/api/v1/notifications/announcements/feed')
        ->assertUnauthorized();
});

test('parameter limit dibatasi dan divalidasi', function () {
    foreach (range(1, 7) as $i) {
        makeAnnouncement();
    }

    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed?limit=3')
        ->assertOk()
        ->assertJsonCount(3, 'data.items')
        // unread_count menghitung SEMUA yang belum dibaca, bukan hanya yang
        // ikut terkirim — kalau tidak, badge akan berhenti di angka limit.
        ->assertJsonPath('data.unread_count', 7);

    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed?limit=99')
        ->assertStatus(422);
});

/**
 * Tiga bug berikut sudah ada sebelum lonceng dibuat dan baru ketahuan saat
 * endpoint umpan diuji dengan data sungguhan. Ketiganya membuat daftar
 * pengumuman balas 500 begitu tabelnya tidak kosong, jadi dijaga di sini:
 *
 * 1. eager-load `author:id,email` padahal resource membaca `full_name`, yang
 *    jatuh ke kolom `username` saat profil kosong (pola yang sama sudah
 *    tercatat di CLAUDE.md untuk LoginLogService).
 * 2. isReadBy()/markAsReadBy() men-type-hint App\Models\User, sedangkan guard
 *    menghasilkan Auth\User — kelas induknya.
 * 3. readers() memakai withTimestamps() padahal pivotnya hanya punya read_at.
 */
test('daftar pengumuman tetap jalan saat ada isinya', function () {
    makeAnnouncement();
    makeAnnouncement(['is_published' => false]);

    $super = makeUser('super.pengumuman', 'super_admin', 'super_admin');

    // Pengurus melihat draft juga.
    $this->actingAs($super, 'sanctum')
        ->getJson('/api/v1/notifications/announcements')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // Selain pengurus hanya melihat yang sudah tayang. Catatan: peran `admin`
    // pun masuk kelompok ini — izin `announcements.manage` yang dipakai
    // AnnouncementController::canManage() tidak pernah didaftarkan di
    // PermissionSeeder, sehingga praktis hanya super_admin yang bisa mengelola
    // pengumuman. Perilaku ini dikunci di sini supaya perubahannya disengaja.
    foreach ([$this->admin, $this->guru] as $user) {
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
});

test('membuka satu pengumuman menandainya sudah dibaca', function () {
    $a = makeAnnouncement();

    $this->actingAs($this->guru, 'sanctum')
        ->getJson("/api/v1/notifications/announcements/{$a->id}")
        ->assertOk()
        ->assertJsonPath('data.is_read', true);

    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 0);
});

test('rute feed tidak tertangkap wildcard announcements/{announcement}', function () {
    makeAnnouncement();

    // Kalau urutan pendaftaran rute salah, "feed" dianggap id dan balasannya
    // 404/500, bukan struktur umpan.
    $this->actingAs($this->guru, 'sanctum')
        ->getJson('/api/v1/notifications/announcements/feed')
        ->assertOk()
        ->assertJsonStructure(['data' => ['unread_count', 'items']]);
});
