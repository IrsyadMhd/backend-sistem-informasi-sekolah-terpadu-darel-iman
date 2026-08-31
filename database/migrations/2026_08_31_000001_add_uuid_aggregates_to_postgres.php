<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                CREATE OR REPLACE FUNCTION max_uuid(uuid, uuid)
                RETURNS uuid AS $$
                BEGIN
                    RETURN GREATEST($1, $2);
                END;
                $$ LANGUAGE plpgsql IMMUTABLE;
            ");

            DB::statement("
                CREATE OR REPLACE AGGREGATE max(uuid) (
                    SFUNC = max_uuid,
                    STYPE = uuid
                );
            ");

            DB::statement("
                CREATE OR REPLACE FUNCTION min_uuid(uuid, uuid)
                RETURNS uuid AS $$
                BEGIN
                    RETURN LEAST($1, $2);
                END;
                $$ LANGUAGE plpgsql IMMUTABLE;
            ");

            DB::statement("
                CREATE OR REPLACE AGGREGATE min(uuid) (
                    SFUNC = min_uuid,
                    STYPE = uuid
                );
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP AGGREGATE IF EXISTS min(uuid);");
            DB::statement("DROP FUNCTION IF EXISTS min_uuid(uuid, uuid);");
            DB::statement("DROP AGGREGATE IF EXISTS max(uuid);");
            DB::statement("DROP FUNCTION IF EXISTS max_uuid(uuid, uuid);");
        }
    }
};
