<?php

namespace App\Http\Middleware;

use App\Support\RoleName;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFoundationReadOnly
{
    /**
     * Middleware untuk memproteksi endpoint operasional dari aksi penulisan (POST/PUT/PATCH/DELETE)
     * khusus untuk role yayasan monitoring; Pengurus Yayasan mendapat
     * pengecualian terbatas pada modul personel dan hak akses global.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! RoleName::userHasAny($user, ['Super Admin'])) {
            $foundationRoles = [
                'Yayasan',
                'Ketua Yayasan',
                'Pengurus Yayasan',
                'Sekretaris Yayasan',
                'Bendahara Yayasan',
            ];

            if (RoleName::userHasAny($user, $foundationRoles)) {
                $method = strtoupper($request->method());

                if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    // Izinkan mutation hanya pada profil pribadi dan notifikasi pribadi yayasan
                    $isAllowedProfile = $request->is('api/foundation/profile', 'api/foundation/profile/*', 'api/profile', 'api/profile/*');
                    $isAllowedNotification = $request->is('api/foundation/notifications', 'api/foundation/notifications/*', 'api/notifications');

                    $isAllowedGlobalPersonnel = RoleName::userHasAny($user, ['Pengurus Yayasan', 'pengurus_yayasan'])
                        && $request->is(
                            'api/hak-akses',
                            'api/hak-akses/*',
                            'api/employees',
                            'api/employees/*',
                            'api/jabatan',
                            'api/jabatan/*'
                        );

                    if (! $isAllowedProfile && ! $isAllowedNotification && ! $isAllowedGlobalPersonnel) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Akses ditolak. Role yayasan monitoring tidak memiliki akses mutasi data operasional.',
                        ], 403);
                    }
                }
            }
        }

        return $next($request);
    }
}
