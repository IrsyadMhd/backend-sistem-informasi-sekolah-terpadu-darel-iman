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

            $policy = $user instanceof \App\Models\User
                ? \App\Support\RolePolicyDefaults::resolveUserPolicy($user)
                : null;

            $isReadOnlyRole = $policy !== null
                ? (bool) ($policy['is_read_only'] ?? false)
                : RoleName::userHasAny($user, $foundationRoles);

            if ($isReadOnlyRole) {
                $method = strtoupper($request->method());

                if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    // Izinkan mutation hanya pada profil pribadi dan notifikasi pribadi
                    $isAllowedProfile = $request->is('api/foundation/profile', 'api/foundation/profile/*', 'api/profile', 'api/profile/*');
                    $isAllowedNotification = $request->is('api/foundation/notifications', 'api/foundation/notifications/*', 'api/notifications');

                    $isFoundationManager = ($policy !== null && ($policy['portal_group'] ?? '') === 'foundation' && ($policy['can_manage_global_access'] ?? false))
                        || RoleName::userHasAny($user, ['Pengurus Yayasan', 'pengurus_yayasan']);

                    $isAllowedGlobalPersonnel = $isFoundationManager
                        && $request->is(
                            'api/hak-akses',
                            'api/hak-akses/*',
                            'api/employees',
                            'api/employees/*',
                            'api/jabatan',
                            'api/jabatan/*'
                        );

                    $isAllowedAssessmentFormula = $isFoundationManager
                        && $request->is('api/assessment-formulas', 'api/assessment-formulas/*')
                        && $user->hasAnyPermission([
                            'assessment_formula.create', 'assessment_formula.update', 'assessment_formula.submit',
                            'assessment_formula.approve', 'assessment_formula.activate', 'assessment_formula.archive',
                        ]);

                    $isAllowedWorshipAssessment = $isFoundationManager
                        && $request->is('api/worship-assessment-settings', 'api/worship-assessment-settings/*')
                        && $user->can('worship_assessment.setting.manage');

                    if (! $isAllowedProfile && ! $isAllowedNotification && ! $isAllowedGlobalPersonnel && ! $isAllowedAssessmentFormula && ! $isAllowedWorshipAssessment) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Akses ditolak. Role dengan mode baca saja (read-only) tidak memiliki akses mutasi data operasional.',
                        ], 403);
                    }
                }
            }
        }

        return $next($request);
    }
}
