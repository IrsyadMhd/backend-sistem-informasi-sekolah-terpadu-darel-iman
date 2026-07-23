<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = \App\Models\User::where('email', 'superadmin@school-erp.local')->first();

if (! $u) {
    $u = \App\Models\User::create([
        'name' => 'Super Admin',
        'email' => 'superadmin@school-erp.local',
        'password' => 'Password123!',
        'is_active' => true,
    ]);
    $u->assignRole('Super Admin');
}

echo $u->createToken('uji-api')->plainTextToken . PHP_EOL;
