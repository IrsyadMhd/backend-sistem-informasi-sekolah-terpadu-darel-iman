<?php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebugNotifTest extends TestCase
{
    use RefreshDatabase;
    public function test_debug(): void
    {
        try {
            $r = DB::table('notifications')->where('user_id', 'x')->count();
            fwrite(STDERR, "where user_id OK: $r".PHP_EOL);
        } catch (\Throwable $e) {
            fwrite(STDERR, "where user_id ERR: ".$e->getMessage().PHP_EOL);
        }
        try {
            $r = DB::table('notifications')->where('message', 'x')->count();
            fwrite(STDERR, "where message OK: $r".PHP_EOL);
        } catch (\Throwable $e) {
            fwrite(STDERR, "where message ERR: ".$e->getMessage().PHP_EOL);
        }
        $this->assertTrue(true);
    }
}
