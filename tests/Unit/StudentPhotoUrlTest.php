<?php

namespace Tests\Unit;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentPhotoUrlTest extends TestCase
{
    public function test_student_photo_url_is_resolved_from_public_storage_path(): void
    {
        Storage::fake('public');

        $student = new Student();
        $student->forceFill([
            'photo' => 'students/sample.jpg',
            'full_name' => 'Ahmad Fauzan',
        ]);

        $this->assertSame('/storage/students/sample.jpg', $student->photo_url);
    }
}
