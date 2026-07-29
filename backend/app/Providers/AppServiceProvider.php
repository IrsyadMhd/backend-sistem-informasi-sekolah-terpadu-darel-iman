<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Repositories\Contracts\ClassRepositoryInterface;
use App\Repositories\Contracts\StudentRepositoryInterface;
use App\Repositories\Contracts\TeacherRepositoryInterface;
use App\Repositories\Eloquent\ClassRepository;
use App\Repositories\Eloquent\StudentRepository;
use App\Repositories\Eloquent\TeacherRepository;
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
        $this->app->bind(\App\Repositories\Contracts\EmployeeRepositoryInterface::class, \App\Repositories\Eloquent\EmployeeRepository::class);
        $this->app->bind(\App\Repositories\Contracts\KelasRepositoryInterface::class, \App\Repositories\Eloquent\KelasRepository::class);
        $this->app->bind(\App\Repositories\Contracts\JenisUnitPendidikanRepositoryInterface::class, \App\Repositories\Eloquent\JenisUnitPendidikanRepository::class);
        $this->app->bind(\App\Repositories\Contracts\SubjectRepositoryInterface::class, \App\Repositories\Eloquent\SubjectRepository::class);
        $this->app->bind(\App\Repositories\Contracts\TahunAjaranRepositoryInterface::class, \App\Repositories\Eloquent\TahunAjaranRepository::class);
        $this->app->bind(\App\Repositories\Contracts\ModulSemesterRepositoryInterface::class, \App\Repositories\Eloquent\ModulSemesterRepository::class);
        $this->app->bind(\App\Repositories\Contracts\MasterKurikulumRepositoryInterface::class, \App\Repositories\Eloquent\MasterKurikulumRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsModulAjarRepositoryInterface::class, \App\Repositories\Eloquent\LmsModulAjarRepository::class);
        $this->app->bind(\App\Repositories\Contracts\TujuanPembelajaranRepositoryInterface::class, \App\Repositories\Eloquent\TujuanPembelajaranRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsMateriRepositoryInterface::class, \App\Repositories\Eloquent\LmsMateriRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsMediaRepositoryInterface::class, \App\Repositories\Eloquent\LmsMediaRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsReferensiRepositoryInterface::class, \App\Repositories\Eloquent\LmsReferensiRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsAktivitasBelajarRepositoryInterface::class, \App\Repositories\Eloquent\LmsAktivitasBelajarRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsDiskusiRepositoryInterface::class, \App\Repositories\Eloquent\LmsDiskusiRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsPenugasanRepositoryInterface::class, \App\Repositories\Eloquent\LmsPenugasanRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsPengumpulanTugasRepositoryInterface::class, \App\Repositories\Eloquent\LmsPengumpulanTugasRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsPresensiRepositoryInterface::class, \App\Repositories\Eloquent\LmsPresensiRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsKisiKisiRepositoryInterface::class, \App\Repositories\Eloquent\LmsKisiKisiRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsBankSoalRepositoryInterface::class, \App\Repositories\Eloquent\LmsBankSoalRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsUjianRepositoryInterface::class, \App\Repositories\Eloquent\LmsUjianRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsPenilaianRepositoryInterface::class, \App\Repositories\Eloquent\LmsPenilaianRepository::class);
        $this->app->bind(\App\Repositories\Contracts\CapaianPembelajaranRepositoryInterface::class, \App\Repositories\Eloquent\CapaianPembelajaranRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LmsRaporRepositoryInterface::class, \App\Repositories\Eloquent\LmsRaporRepository::class);
    }


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
