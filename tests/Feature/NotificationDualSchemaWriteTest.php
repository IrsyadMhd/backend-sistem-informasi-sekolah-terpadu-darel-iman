<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Notification;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SESI 10 CLOSURE — Audit skema ganda `notifications`.
 *
 * Dua migrasi mendefinisikan kolom `notifications` berbeda:
 *  - legacy  : user_id / type / message / is_read  (tidak pernah terwujud)
 *  - partitioned (kanonik): notifiable_id / notifiable_type / title / body /
 *    channel; di PostgreSQL academic_year_id/semester_id/month adalah bagian
 *    PRIMARY KEY + FOREIGN KEY sehingga wajib terisi.
 *
 * Setelah penyatuan, semua penulisan melewati Notification::deliver() yang
 * me-resolve konteks akademik aktif (partition key) dan memakai skema kanonik.
 * Test ini membuktikan penulisan bertahan dan tidak lagi gagal senyap.
 */
class NotificationDualSchemaWriteTest extends TestCase
{
    use RefreshDatabase;

    private function activateAcademicContext(): array
    {
        $ay = AcademicYear::create(['name' => '2026/2027', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'sequence' => 1, 'is_active' => true]);

        return [$ay, $sem];
    }

    public function test_delivery_requires_active_academic_context_for_partition_key(): void
    {
        $user = User::factory()->create();

        // Tanpa tahun ajaran aktif, partition key (academic_year/semester/month)
        // tidak dapat dipenuhi → notifikasi dilewati dengan aman (bukan gagal senyap).
        $this->assertNull(Notification::deliver(
            userId: $user->id,
            title: 'Tanpa Konteks',
            body: 'Tidak dapat disimpan tanpa tahun ajaran aktif.',
            channel: 'general',
        ));
        $this->assertDatabaseCount('notifications', 0);

        // Dengan konteks akademik aktif, notifikasi tersimpan di skema kanonik.
        [$ay, $sem] = $this->activateAcademicContext();
        $notification = Notification::deliver(
            userId: $user->id,
            title: 'Dengan Konteks',
            body: 'Tersimpan memakai partition key akademik.',
            channel: 'general',
            metadata: ['k' => 'v'],
        );

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'month' => now()->month,
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'title' => 'Dengan Konteks',
            'body' => 'Tersimpan memakai partition key akademik.',
            'channel' => 'general',
        ]);

        // Accessor menjembatani body (kanonik) ke atribut message (legacy).
        $this->assertSame('Tersimpan memakai partition key akademik.', $notification->fresh()->message);
    }

    public function test_parent_chat_message_persists_notification_for_teacher(): void
    {
        [$ay, $sem] = $this->activateAcademicContext();

        Role::firstOrCreate(['name' => 'Orang Tua', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Orang Tua');
        $parent = ParentModel::create(['user_id' => $user->id, 'full_name' => 'Wali Chat']);

        $child = Student::create(['full_name' => 'Anak Chat', 'nis' => 'C001', 'gender' => 'male', 'is_active' => true]);
        StudentParent::create(['student_id' => $child->id, 'parent_id' => $parent->id, 'relationship_type' => 'guardian', 'is_primary' => true]);

        $teacher = User::factory()->create();
        $unit = EducationUnit::create(['name' => 'Unit Chat', 'code' => 'U-CHAT', 'is_active' => true]);
        $teacherEmployee = Employee::create([
            'user_id' => $teacher->id,
            'unit_id' => $unit->id,
            'education_unit_id' => $unit->id,
            'nama_lengkap' => 'Guru Wali Chat',
            'niy' => 'EMP-CHAT-1',
            'status' => 'Aktif',
            'is_active' => true,
        ]);
        $kelas = Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $ay->id,
            'semester_id' => $sem->id,
            'jenjang' => 'SMP',
            'tingkat' => 7,
            'kode_kelas' => 'CHAT-7A',
            'nama_kelas' => '7A Chat',
            'wali_kelas_id' => $teacherEmployee->id,
            'status' => 'Aktif',
        ]);
        $child->update(['kelas_id' => $kelas->id]);

        $this->actingAs($user)->postJson('/api/portal/chat/'.$teacher->id.'?child_id='.$child->id, [
            'message' => 'Terima kasih atas bimbingannya.',
        ])->assertOk();

        $this->assertDatabaseHas('portal_messages', [
            'recipient_user_id' => $teacher->id,
            'message' => 'Terima kasih atas bimbingannya.',
        ]);

        // Notifikasi berhasil tersimpan di skema kanonik (bukan gagal senyap).
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $teacher->id,
            'notifiable_type' => User::class,
            'channel' => 'chat',
        ]);

        $notification = Notification::query()
            ->where('notifiable_id', $teacher->id)
            ->where('channel', 'chat')
            ->first();

        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);
        $this->assertSame('Terima kasih atas bimbingannya.', $notification->body);
    }

    public function test_unread_count_and_mark_as_read_work_on_partitioned_schema(): void
    {
        $this->activateAcademicContext();
        $user = User::factory()->create();

        $notification = Notification::deliver(
            userId: $user->id,
            title: 'Ujian Dimulai',
            body: 'Jadwal ujian matematika telah dibuka.',
            channel: 'web',
        );

        $this->actingAs($user)->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($user)->postJson('/api/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($user)->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }
}
