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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
