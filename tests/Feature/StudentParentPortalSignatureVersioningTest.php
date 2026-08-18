<?php

namespace Tests\Feature;

use App\Models\ParentModel;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\StudentParent;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SESI 10 CLOSURE — Versioning tanda tangan catatan guru.
 *
 * Hash canonical hanya dibangun dari isi dokumen (content):
 *   signature_content_hash = sha256(trim(content))
 * Field identitas (note id, student id) dilindungi oleh FK + child-scope,
 * sehingga TIDAK dimasukkan ke hash. Field volatile (updated_at, priority,
 * follow_up, metadata UI) TIDAK dimasukkan — perubahan di sana tidak
 * menodai tanda tangan.
 */
class StudentParentPortalSignatureVersioningTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Role::firstOrCreate(['name' => 'Orang Tua', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Orang Tua');
        $parent = ParentModel::create(['user_id' => $user->id, 'full_name' => 'Wali Uji']);

        $child = Student::create(['full_name' => 'Anak Uji', 'nis' => 'SV001', 'gender' => 'male', 'is_active' => true]);
        StudentParent::create(['student_id' => $child->id, 'parent_id' => $parent->id, 'relationship_type' => 'guardian', 'is_primary' => true]);

        $teacher = Teacher::create(['full_name' => 'Guru Catatan', 'employee_number' => 'T-SIG-01']);

        $note = StudentNote::create([
            'student_id' => $child->id,
            'teacher_id' => $teacher->id,
            'title' => 'Catatan Awal',
            'content' => 'Perilaku baik di kelas.',
            'visible_to_parent' => true,
            'visible_to_student' => true,
            'category' => 'Akademik',
            'priority' => 'medium',
            'date' => now()->toDateString(),
        ]);

        return [$user, $child, $note];
    }

    public function test_signature_remains_valid_when_unrelated_metadata_changes(): void
    {
        [$user, $child, $note] = $this->fixture();

        $this->actingAs($user)->postJson("/api/portal/student-notes/{$note->id}/sign?child_id={$child->id}")
            ->assertOk()
            ->assertJsonPath('data.signature_status', 'signed');

        // Metadata yang TIDAK mengubah isi dokumen (priority) berubah.
        $note->update(['priority' => 'high']);

        $this->actingAs($user)->getJson("/api/portal/student-notes?child_id={$child->id}")
            ->assertOk()
            ->assertJsonPath('data.data.0.signature_status', 'signed')
            ->assertJsonPath('data.data.0.signature_stale', false);
    }

    public function test_signature_becomes_outdated_when_note_content_changes(): void
    {
        [$user, $child, $note] = $this->fixture();

        $this->actingAs($user)->postJson("/api/portal/student-notes/{$note->id}/sign?child_id={$child->id}")
            ->assertOk()
            ->assertJsonPath('data.signature_status', 'signed');

        $note->update(['content' => 'Perilaku baik di kelas. Mengalami peningkatan.']);

        $this->actingAs($user)->getJson("/api/portal/student-notes?child_id={$child->id}")
            ->assertOk()
            ->assertJsonPath('data.data.0.signature_status', 'signed_updated')
            ->assertJsonPath('data.data.0.signature_stale', true);
    }

    public function test_parent_cannot_sign_outdated_document_version(): void
    {
        [$user, $child, $note] = $this->fixture();

        // Tanda tangan versi v1.
        $this->actingAs($user)->postJson("/api/portal/student-notes/{$note->id}/sign?child_id={$child->id}")
            ->assertOk();

        // Guru mengubah isi → versi v2. Tanda tangan v1 otomatis stale.
        $note->update(['content' => 'Perilaku baik di kelas. Mengalami peningkatan.']);

        $this->actingAs($user)->getJson("/api/portal/student-notes?child_id={$child->id}")
            ->assertJsonPath('data.data.0.signature_status', 'signed_updated');

        // Tanda tangan ulang SELALU menandatangani versi terkini (v2).
        // Respons menandai bahwa tanda tangan sebelumnya sudah basi.
        $resign = $this->actingAs($user)->postJson("/api/portal/student-notes/{$note->id}/sign?child_id={$child->id}")
            ->assertOk()
            ->assertJsonPath('data.signature_status', 'signed')
            ->assertJsonPath('data.signature_was_stale', true);

        // Hash tersimpan harus identik dengan digest versi v2.
        $this->assertSame(StudentNote::contentHash($note->fresh()->content), $resign->json('data.signature_content_hash'));
    }

    public function test_parent_signature_is_idempotent(): void
    {
        [$user, $child, $note] = $this->fixture();

        $first = $this->actingAs($user)->postJson("/api/portal/student-notes/{$note->id}/sign?child_id={$child->id}")
            ->assertOk()
            ->assertJsonPath('data.signature_status', 'signed');

        // Menandatangani ulang konten yang sama → status tetap signed, bukan stale.
        $second = $this->actingAs($user)->postJson("/api/portal/student-notes/{$note->id}/sign?child_id={$child->id}")
            ->assertOk()
            ->assertJsonPath('data.signature_status', 'signed')
            ->assertJsonPath('data.signature_was_stale', false);

        $this->assertSame($first->json('data.signature_content_hash'), $second->json('data.signature_content_hash'));
        $this->assertSame($second->json('data.signature_content_hash'), StudentNote::contentHash($note->content));
    }
}
