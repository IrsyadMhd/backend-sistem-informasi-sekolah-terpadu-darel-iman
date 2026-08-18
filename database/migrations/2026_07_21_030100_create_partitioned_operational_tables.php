<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
            CREATE TABLE attendances (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                academic_year_id UUID NOT NULL,
                semester_id UUID NOT NULL,
                month SMALLINT NOT NULL,
                attendance_date DATE NOT NULL,
                student_id UUID NOT NULL,
                class_id UUID NOT NULL,
                check_in_time TIMESTAMPTZ NULL,
                check_out_time TIMESTAMPTZ NULL,
                status VARCHAR(20) NOT NULL,
                attendance_method VARCHAR(20) NOT NULL,
                location VARCHAR(255) NULL,
                metadata JSONB NULL,
                created_at TIMESTAMPTZ NULL,
                updated_at TIMESTAMPTZ NULL,
                deleted_at TIMESTAMPTZ NULL,
                PRIMARY KEY (id, academic_year_id, semester_id, month),
                CONSTRAINT attendances_month_check CHECK (month >= 1 AND month <= 12),
                CONSTRAINT fk_attendances_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
                CONSTRAINT fk_attendances_semester FOREIGN KEY (semester_id) REFERENCES semesters(id),
                CONSTRAINT fk_attendances_student FOREIGN KEY (student_id) REFERENCES students(id),
                CONSTRAINT fk_attendances_class FOREIGN KEY (class_id) REFERENCES classes(id)
            ) PARTITION BY LIST (month)
        ');

            DB::statement('
            CREATE TABLE attendance_logs (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                academic_year_id UUID NOT NULL,
                semester_id UUID NOT NULL,
                month SMALLINT NOT NULL,
                attendance_id UUID NOT NULL,
                student_id UUID NOT NULL,
                action VARCHAR(50) NOT NULL,
                logged_at TIMESTAMPTZ NOT NULL,
                metadata JSONB NULL,
                created_at TIMESTAMPTZ NULL,
                updated_at TIMESTAMPTZ NULL,
                deleted_at TIMESTAMPTZ NULL,
                PRIMARY KEY (id, academic_year_id, semester_id, month),
                CONSTRAINT attendance_logs_month_check CHECK (month >= 1 AND month <= 12),
                CONSTRAINT fk_attendance_logs_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
                CONSTRAINT fk_attendance_logs_semester FOREIGN KEY (semester_id) REFERENCES semesters(id),
                CONSTRAINT fk_attendance_logs_student FOREIGN KEY (student_id) REFERENCES students(id)
            ) PARTITION BY LIST (month)
        ');

            DB::statement('
            CREATE TABLE tahfizh_records (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                academic_year_id UUID NOT NULL,
                semester_id UUID NOT NULL,
                month SMALLINT NOT NULL,
                record_date DATE NOT NULL,
                student_id UUID NOT NULL,
                class_id UUID NOT NULL,
                teacher_id UUID NOT NULL,
                surah_name VARCHAR(120) NOT NULL,
                ayah_start INTEGER NOT NULL,
                ayah_end INTEGER NOT NULL,
                line_count INTEGER NOT NULL DEFAULT 0,
                notes TEXT NULL,
                status VARCHAR(20) NOT NULL,
                metadata JSONB NULL,
                created_at TIMESTAMPTZ NULL,
                updated_at TIMESTAMPTZ NULL,
                deleted_at TIMESTAMPTZ NULL,
                PRIMARY KEY (id, academic_year_id, semester_id, month),
                CONSTRAINT tahfizh_records_month_check CHECK (month >= 1 AND month <= 12),
                CONSTRAINT fk_tahfizh_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
                CONSTRAINT fk_tahfizh_semester FOREIGN KEY (semester_id) REFERENCES semesters(id),
                CONSTRAINT fk_tahfizh_student FOREIGN KEY (student_id) REFERENCES students(id),
                CONSTRAINT fk_tahfizh_class FOREIGN KEY (class_id) REFERENCES classes(id),
                CONSTRAINT fk_tahfizh_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id)
            ) PARTITION BY LIST (month)
        ');

            DB::statement('
            CREATE TABLE mutabaah_records (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                academic_year_id UUID NOT NULL,
                semester_id UUID NOT NULL,
                month SMALLINT NOT NULL,
                record_date DATE NOT NULL,
                student_id UUID NOT NULL,
                class_id UUID NOT NULL,
                shalat_checklist JSONB NULL,
                sunnah_fasting BOOLEAN DEFAULT FALSE,
                tilawah_lines INTEGER DEFAULT 0,
                notes TEXT NULL,
                metadata JSONB NULL,
                created_at TIMESTAMPTZ NULL,
                updated_at TIMESTAMPTZ NULL,
                deleted_at TIMESTAMPTZ NULL,
                PRIMARY KEY (id, academic_year_id, semester_id, month),
                CONSTRAINT mutabaah_records_month_check CHECK (month >= 1 AND month <= 12),
                CONSTRAINT fk_mutabaah_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
                CONSTRAINT fk_mutabaah_semester FOREIGN KEY (semester_id) REFERENCES semesters(id),
                CONSTRAINT fk_mutabaah_student FOREIGN KEY (student_id) REFERENCES students(id),
                CONSTRAINT fk_mutabaah_class FOREIGN KEY (class_id) REFERENCES classes(id)
            ) PARTITION BY LIST (month)
        ');

            DB::statement('
            CREATE TABLE notifications (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                academic_year_id UUID NOT NULL,
                semester_id UUID NOT NULL,
                month SMALLINT NOT NULL,
                notifiable_id UUID NOT NULL,
                notifiable_type VARCHAR(150) NOT NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                channel VARCHAR(30) NOT NULL,
                sent_at TIMESTAMPTZ NULL,
                read_at TIMESTAMPTZ NULL,
                metadata JSONB NULL,
                created_at TIMESTAMPTZ NULL,
                updated_at TIMESTAMPTZ NULL,
                deleted_at TIMESTAMPTZ NULL,
                PRIMARY KEY (id, academic_year_id, semester_id, month),
                CONSTRAINT notifications_month_check CHECK (month >= 1 AND month <= 12),
                CONSTRAINT fk_notifications_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
                CONSTRAINT fk_notifications_semester FOREIGN KEY (semester_id) REFERENCES semesters(id)
            ) PARTITION BY LIST (month)
        ');

            for ($month = 1; $month <= 12; $month++) {
                $suffix = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

                DB::statement("CREATE TABLE attendances_m{$suffix} PARTITION OF attendances FOR VALUES IN ({$month})");
                DB::statement("CREATE TABLE attendance_logs_m{$suffix} PARTITION OF attendance_logs FOR VALUES IN ({$month})");
                DB::statement("CREATE TABLE tahfizh_records_m{$suffix} PARTITION OF tahfizh_records FOR VALUES IN ({$month})");
                DB::statement("CREATE TABLE mutabaah_records_m{$suffix} PARTITION OF mutabaah_records FOR VALUES IN ({$month})");
                DB::statement("CREATE TABLE notifications_m{$suffix} PARTITION OF notifications FOR VALUES IN ({$month})");
            }

            DB::statement('CREATE INDEX attendances_lookup_idx ON attendances (academic_year_id, semester_id, attendance_date, class_id, student_id)');
            DB::statement('CREATE INDEX attendance_logs_lookup_idx ON attendance_logs (academic_year_id, semester_id, month, student_id, logged_at)');
            DB::statement('CREATE INDEX tahfizh_records_lookup_idx ON tahfizh_records (academic_year_id, semester_id, record_date, class_id, student_id)');
            DB::statement('CREATE INDEX mutabaah_records_lookup_idx ON mutabaah_records (academic_year_id, semester_id, record_date, class_id, student_id)');
            DB::statement('CREATE INDEX notifications_lookup_idx ON notifications (academic_year_id, semester_id, month, notifiable_id, notifiable_type)');

            DB::statement("CREATE INDEX notifications_fts_idx ON notifications USING GIN (to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(body,'')))");
        } else {
            Schema::create('attendances', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('academic_year_id')->nullable();
                $table->uuid('semester_id')->nullable();
                $table->smallInteger('month')->nullable();
                $table->date('attendance_date');
                $table->uuid('student_id')->nullable();
                $table->uuid('class_id')->nullable();
                $table->timestamp('check_in_time')->nullable();
                $table->timestamp('check_out_time')->nullable();
                $table->string('status', 20)->default('HADIR');
                $table->string('attendance_method', 20)->default('MANUAL');
                $table->string('location')->nullable();
                $table->json('metadata')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });

            Schema::create('attendance_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('academic_year_id');
                $table->uuid('semester_id');
                $table->smallInteger('month');
                $table->uuid('attendance_id');
                $table->uuid('student_id');
                $table->string('action', 50);
                $table->timestamp('logged_at');
                $table->json('metadata')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });

            Schema::create('tahfizh_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('academic_year_id');
                $table->uuid('semester_id');
                $table->smallInteger('month');
                $table->date('record_date');
                $table->uuid('student_id');
                $table->uuid('class_id');
                $table->uuid('teacher_id');
                $table->string('surah_name', 120);
                $table->integer('ayah_start');
                $table->integer('ayah_end');
                $table->integer('line_count')->default(0);
                $table->text('notes')->nullable();
                $table->string('status', 20);
                $table->json('metadata')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });

            Schema::create('mutabaah_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('academic_year_id');
                $table->uuid('semester_id');
                $table->smallInteger('month');
                $table->date('record_date');
                $table->uuid('student_id');
                $table->uuid('class_id');
                $table->json('shalat_checklist')->nullable();
                $table->boolean('sunnah_fasting')->default(false);
                $table->integer('tilawah_lines')->default(0);
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });

            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('academic_year_id');
                $table->uuid('semester_id');
                $table->smallInteger('month');
                $table->uuid('notifiable_id');
                $table->string('notifiable_type', 150);
                $table->string('title');
                $table->text('body');
                $table->string('channel', 30);
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->json('metadata')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notifications_fts_idx');
        DB::statement('DROP INDEX IF EXISTS notifications_lookup_idx');
        DB::statement('DROP INDEX IF EXISTS mutabaah_records_lookup_idx');
        DB::statement('DROP INDEX IF EXISTS tahfizh_records_lookup_idx');
        DB::statement('DROP INDEX IF EXISTS attendance_logs_lookup_idx');
        DB::statement('DROP INDEX IF EXISTS attendances_lookup_idx');

        for ($month = 1; $month <= 12; $month++) {
            $suffix = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

            DB::statement("DROP TABLE IF EXISTS notifications_m{$suffix}");
            DB::statement("DROP TABLE IF EXISTS mutabaah_records_m{$suffix}");
            DB::statement("DROP TABLE IF EXISTS tahfizh_records_m{$suffix}");
            DB::statement("DROP TABLE IF EXISTS attendance_logs_m{$suffix}");
            DB::statement("DROP TABLE IF EXISTS attendances_m{$suffix}");
        }

        DB::statement('DROP TABLE IF EXISTS notifications');
        DB::statement('DROP TABLE IF EXISTS mutabaah_records');
        DB::statement('DROP TABLE IF EXISTS tahfizh_records');
        DB::statement('DROP TABLE IF EXISTS attendance_logs');
        DB::statement('DROP TABLE IF EXISTS attendances');
    }
};
