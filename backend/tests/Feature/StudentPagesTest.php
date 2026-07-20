<?php

/**
 * Laporan pengguna: "detail siswa dan edit di menu Siswa belum ada" —
 * routes/web.php sebelumnya mengarahkan /students/{id} dan /students/{id}/edit
 * ke method PageController::students() yang sama dengan index (cuma
 * me-render ulang daftar). Test ini memastikan kedua halaman benar-benar
 * merender komponen & data yang tepat.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.halamansiswa', 'admin', 'admin');
});

test('halaman detail siswa merender dengan data lengkap termasuk unique_code', function () {
    $this->actingAs($this->admin)
        ->get("/students/{$this->amir->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('students/Show')
            ->where('student.id', $this->amir->id)
            ->where('student.nis', $this->amir->nis)
            ->has('student.unique_code'));
});

test('halaman edit siswa merender dengan data untuk prefill form', function () {
    $this->actingAs($this->admin)
        ->get("/students/{$this->amir->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('students/Edit')
            ->where('student.id', $this->amir->id)
            ->has('student.full_name'));
});

test('halaman detail siswa dengan wali tidak error meski income_range kosong — regresi bug atribut income', function () {
    // ParentResource sebelumnya membaca $this->income (tidak ada di kolom
    // student_guardians, kolom aslinya income_range) — di bawah mode strict
    // (preventAccessingMissingAttributes) ini melempar AttributeNotFoundException
    // setiap halaman detail siswa yang punya wali dibuka.
    \App\Infrastructure\Persistence\Eloquent\Student\StudentGuardian::create([
        'tenant_id' => $this->tenantId,
        'student_id' => $this->amir->id,
        'relationship' => 'father',
        'name' => 'Bapak Amir',
        'phone' => '081234567890',
        'occupation' => 'Wiraswasta',
        'income_range' => '3-5 juta',
        'is_primary_contact' => true,
    ]);

    $this->actingAs($this->admin)
        ->get("/students/{$this->amir->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('students/Show')
            ->where('student.parents.0.income_range', '3-5 juta')
            ->where('student.parents.0.relationship_label', 'Ayah'));
});

test('guru di luar kelas yang diampu tetap ditolak 403 saat akses detail siswa', function () {
    // Regresi R7 (ROLE-ACCESS-PLAN.md): halaman ini sendiri tidak authorize(),
    // tapi endpoint API di baliknya (dipakai fetch data lain di halaman yang
    // sama) tetap harus menegakkan StudentPolicy — dites di sini lewat API
    // langsung supaya tidak bergantung pada implementasi halaman.
    $guru = makeUser('guru.tanpakelas.detail', 'guru', 'teacher');

    $this->actingAs($guru, 'sanctum')
        ->getJson("/api/v1/students/{$this->amir->id}")
        ->assertForbidden();
});

test('update siswa menyimpan data profil (gender/tanggal lahir/dll) — regresi bug field hilang', function () {
    // Ditemukan saat membangun students/Edit.tsx: gender/birth_place/dll
    // bukan kolom `students`, melainkan `user_profiles` — sebelumnya
    // ter-drop diam-diam (mass assignment ke kolom yang tak fillable).
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/students/{$this->amir->id}", [
            'first_name' => 'Amir',
            'gender' => 'male',
            'birth_place' => 'Jakarta',
            'birth_date' => '2010-05-17',
            'religion' => 'islam',
            'address' => 'Jl. Merdeka No. 1',
            'nik' => '3171020101100001',
        ])
        ->assertOk();

    $profile = $this->amirUser->fresh()->profile;
    expect($profile->gender)->toBe('male')
        ->and($profile->birth_place)->toBe('Jakarta')
        ->and($profile->birth_date->toDateString())->toBe('2010-05-17')
        ->and($profile->religion)->toBe('islam')
        ->and($profile->address)->toBe('Jl. Merdeka No. 1')
        ->and($profile->id_number)->toBe('3171020101100001');
});
