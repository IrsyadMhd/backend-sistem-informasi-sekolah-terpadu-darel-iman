<?php

namespace App\Providers;

use App\Models\MutabaahAgendaItem;
use App\Models\MutabaahCategory;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateAssignment;
use App\Models\PersonalAccessToken;
use App\Policies\MutabaahPolicy;
use App\Repositories\Contracts\CapaianPembelajaranRepositoryInterface;
use App\Repositories\Contracts\ClassRepositoryInterface;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use App\Repositories\Contracts\JenisUnitPendidikanRepositoryInterface;
use App\Repositories\Contracts\KelasRepositoryInterface;
use App\Repositories\Contracts\LmsAktivitasBelajarRepositoryInterface;
use App\Repositories\Contracts\LmsBankSoalRepositoryInterface;
use App\Repositories\Contracts\LmsDiskusiRepositoryInterface;
use App\Repositories\Contracts\LmsKisiKisiRepositoryInterface;
use App\Repositories\Contracts\LmsMateriRepositoryInterface;
use App\Repositories\Contracts\LmsMediaRepositoryInterface;
use App\Repositories\Contracts\LmsModulAjarRepositoryInterface;
use App\Repositories\Contracts\LmsPengumpulanTugasRepositoryInterface;
use App\Repositories\Contracts\LmsPenilaianRepositoryInterface;
use App\Repositories\Contracts\LmsPenugasanRepositoryInterface;
use App\Repositories\Contracts\LmsPresensiRepositoryInterface;
use App\Repositories\Contracts\LmsRaporRepositoryInterface;
use App\Repositories\Contracts\LmsReferensiRepositoryInterface;
use App\Repositories\Contracts\LmsUjianRepositoryInterface;
use App\Repositories\Contracts\MasterKurikulumRepositoryInterface;
use App\Repositories\Contracts\ModulSemesterRepositoryInterface;
use App\Repositories\Contracts\MutabaahRepositoryInterface;
use App\Repositories\Contracts\StudentRepositoryInterface;
use App\Repositories\Contracts\SubjectRepositoryInterface;
use App\Repositories\Contracts\TahunAjaranRepositoryInterface;
use App\Repositories\Contracts\TeacherRepositoryInterface;
use App\Repositories\Contracts\TujuanPembelajaranRepositoryInterface;
use App\Repositories\Eloquent\CapaianPembelajaranRepository;
use App\Repositories\Eloquent\ClassRepository;
use App\Repositories\Eloquent\EmployeeRepository;
use App\Repositories\Eloquent\JenisUnitPendidikanRepository;
use App\Repositories\Eloquent\KelasRepository;
use App\Repositories\Eloquent\LmsAktivitasBelajarRepository;
use App\Repositories\Eloquent\LmsBankSoalRepository;
use App\Repositories\Eloquent\LmsDiskusiRepository;
use App\Repositories\Eloquent\LmsKisiKisiRepository;
use App\Repositories\Eloquent\LmsMateriRepository;
use App\Repositories\Eloquent\LmsMediaRepository;
use App\Repositories\Eloquent\LmsModulAjarRepository;
use App\Repositories\Eloquent\LmsPengumpulanTugasRepository;
use App\Repositories\Eloquent\LmsPenilaianRepository;
use App\Repositories\Eloquent\LmsPenugasanRepository;
use App\Repositories\Eloquent\LmsPresensiRepository;
use App\Repositories\Eloquent\LmsRaporRepository;
use App\Repositories\Eloquent\LmsReferensiRepository;
use App\Repositories\Eloquent\LmsUjianRepository;
use App\Repositories\Eloquent\MasterKurikulumRepository;
use App\Repositories\Eloquent\ModulSemesterRepository;
use App\Repositories\Eloquent\MutabaahRepository;
use App\Repositories\Eloquent\StudentRepository;
use App\Repositories\Eloquent\SubjectRepository;
use App\Repositories\Eloquent\TahunAjaranRepository;
use App\Repositories\Eloquent\TeacherRepository;
use App\Repositories\Eloquent\TujuanPembelajaranRepository;
use App\Support\RoleName;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StudentRepositoryInterface::class, StudentRepository::class);
        $this->app->bind(TeacherRepositoryInterface::class, TeacherRepository::class);
        $this->app->bind(ClassRepositoryInterface::class, ClassRepository::class);
        $this->app->bind(EmployeeRepositoryInterface::class, EmployeeRepository::class);
        $this->app->bind(KelasRepositoryInterface::class, KelasRepository::class);
        $this->app->bind(JenisUnitPendidikanRepositoryInterface::class, JenisUnitPendidikanRepository::class);
        $this->app->bind(SubjectRepositoryInterface::class, SubjectRepository::class);
        $this->app->bind(TahunAjaranRepositoryInterface::class, TahunAjaranRepository::class);
        $this->app->bind(ModulSemesterRepositoryInterface::class, ModulSemesterRepository::class);
        $this->app->bind(MasterKurikulumRepositoryInterface::class, MasterKurikulumRepository::class);
        $this->app->bind(LmsModulAjarRepositoryInterface::class, LmsModulAjarRepository::class);
        $this->app->bind(TujuanPembelajaranRepositoryInterface::class, TujuanPembelajaranRepository::class);
        $this->app->bind(LmsMateriRepositoryInterface::class, LmsMateriRepository::class);
        $this->app->bind(LmsMediaRepositoryInterface::class, LmsMediaRepository::class);
        $this->app->bind(LmsReferensiRepositoryInterface::class, LmsReferensiRepository::class);
        $this->app->bind(LmsAktivitasBelajarRepositoryInterface::class, LmsAktivitasBelajarRepository::class);
        $this->app->bind(LmsDiskusiRepositoryInterface::class, LmsDiskusiRepository::class);
        $this->app->bind(LmsPenugasanRepositoryInterface::class, LmsPenugasanRepository::class);
        $this->app->bind(LmsPengumpulanTugasRepositoryInterface::class, LmsPengumpulanTugasRepository::class);
        $this->app->bind(LmsPresensiRepositoryInterface::class, LmsPresensiRepository::class);
        $this->app->bind(LmsKisiKisiRepositoryInterface::class, LmsKisiKisiRepository::class);
        $this->app->bind(LmsBankSoalRepositoryInterface::class, LmsBankSoalRepository::class);
        $this->app->bind(LmsUjianRepositoryInterface::class, LmsUjianRepository::class);
        $this->app->bind(LmsPenilaianRepositoryInterface::class, LmsPenilaianRepository::class);
        $this->app->bind(CapaianPembelajaranRepositoryInterface::class, CapaianPembelajaranRepository::class);
        $this->app->bind(LmsRaporRepositoryInterface::class, LmsRaporRepository::class);
        $this->app->bind(MutabaahRepositoryInterface::class, MutabaahRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Global authorization bypass untuk Super Admin
        Gate::before(function (\App\Models\User $user, string $ability) {
            return RoleName::userHasAny($user, ['Super Admin']) ? true : null;
        });

        foreach ([
            MutabaahCategory::class,
            MutabaahAgendaItem::class,
            MutabaahTemplate::class,
            MutabaahTemplateAssignment::class,
            MutabaahSupervisorAssignment::class,
        ] as $model) {
            Gate::policy($model, MutabaahPolicy::class);
        }
    }
}
