<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasPersonPhoto;
use App\Traits\HasUuidPrimaryKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPersonPhoto, HasRoles, HasUuidPrimaryKey, Notifiable, SoftDeletes;

    protected $appends = ['photo_url', 'avatar_url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function qrCredentials()
    {
        return $this->hasMany(QrCredential::class, 'user_id');
    }

    public function loginEvents()
    {
        return $this->hasMany(LoginEvent::class, 'user_id');
    }

    public function latestLoginEvent()
    {
        return $this->hasOne(LoginEvent::class, 'user_id')->latestOfMany('created_at');
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    public function latestDevice()
    {
        return $this->hasOne(UserDevice::class, 'user_id')->latestOfMany('last_active_at');
    }

    public function userDevices()
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    public function deleteRequests()
    {
        return $this->hasMany(DeleteRequest::class, 'requested_by');
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'user_id');
    }
}

