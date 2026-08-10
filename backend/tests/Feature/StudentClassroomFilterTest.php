<?php

use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use Illuminate\Support\Facades\DB;

/**
 * Filter kelas di halaman Data Siswa.
 *
 * Aturan pentingnya: guru hanya boleh melihat kelas yang ia ampu atau ia
 * walikan — baik di isi dropdown maupun di hasil filternya. Daftar kelasnya
 * memakai sumber yang sama dengan `Student::scopeVisibleTo()`
 * (`User::teachingClassroomIds()`) supaya keduanya tidak bisa berbeda.
 */
beforeEach(function () {
    setupSchoolWorld();
    $this->admin = makeUser('admin.filterkelas', 'admin', 'admin');
});

/** Jadikan $user wali kelas dari $classroomId. */
function jadikanWaliKelas(string $classroomId, string $userId): void
{
    DB::table('classrooms')->where('id', $classroomId)->update(['homeroom_teacher_id' => $userId]);
}

test('admin mendapat semua kelas tahun ajaran aktif', function () {
    $this->actingAs($this->admin)
        ->get('/students')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('students/Index')
            ->has('classrooms', 2));
});

test('guru hanya mendapat kelas yang ia walikan', function () {
    $guru = makeUser('guru.filterkelas', 'guru', 'teacher');
    jadikanWaliKelas($this->classroomA, $guru->id);

    $namaKelasA = Classroom::find($this->classroomA)->name;

    $this->actingAs($guru)
        ->get('/students')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('classrooms', 1)
            ->where('classrooms.0.name', $namaKelasA));
});

test('guru tanpa penugasan mendapat daftar kelas kosong', function () {
    $guru = makeUser('guru.tanpakelas', 'guru', 'teacher');

    $this->actingAs($guru)
        ->get('/students')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('classrooms', 0));
});

test('filter kelas menyaring daftar siswa', function () {
    $this->actingAs($this->admin)
        ->get('/students?classroom_id=' . $this->classroomA)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.id', $this->amir->id)
            ->where('filters.classroom_id', $this->classroomA));
});

test('tanpa filter kelas semua siswa tampil', function () {
    $this->actingAs($this->admin)
        ->get('/students')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('students.data', 2));
});

test('guru mengarang classroom_id kelas lain tidak mendapat siswa', function () {
    $guru = makeUser('guru.curang', 'guru', 'teacher');
    jadikanWaliKelas($this->classroomA, $guru->id);

    // classroomB bukan kelasnya; filter di-AND dengan visibleTo() sehingga
    // hasilnya kosong, bukan membocorkan siswa kelas lain.
    $this->actingAs($guru)
        ->get('/students?classroom_id=' . $this->classroomB)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('students.data', 0));
});

test('filter kelas tidak menghapus kata kunci pencarian', function () {
    $this->actingAs($this->admin)
        ->get('/students?classroom_id=' . $this->classroomA . '&search=zzz-tidak-ada')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 0)
            ->where('filters.search', 'zzz-tidak-ada')
            ->where('filters.classroom_id', $this->classroomA));
});

test('siswa dan orang tua tidak mendapat pilihan kelas', function () {
    $siswa = $this->amirUser;

    $this->actingAs($siswa)
        ->get('/students')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('classrooms', 0));
});
