<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Intervention\Image\Facades\Image;
use JWTAuth;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, CrudTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'user',
        'email',
        'password',
        'sucursal_id',
        'es_vendedor'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends  = ['nombre_completo'];

    public function sucursal():BelongsTo
    {
        return $this->belongsTo(Sucursal::class,'sucursal_id','id');
    }

    public function scopeAccountIs($query, $account = null)
    {
        if (!$account) {
            return $query;
        }

        return $query->whereEmail($account)->orWhere('user', $account);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function makeToken(): string
    {
        return JWTAuth::fromUser($this);
    }

    public function apiLogin(string $password): string
    {
        if (!$this->checkPassword($password)) {
            return false;
        }

        return $this->makeToken();
    }

    public function login($password, $remember = false)
    {
        if (!$this->checkPassword($password)) {
            return false;
        }

        Auth::login($this, $remember);

        return true;
    }

    public function checkPassword(string $password): bool
    {
        return Hash::check($password, $this->password);
    }

    public function getNombreCompletoAttribute()
    {
        return $this->attributes['first_name'] . ' ' . $this->attributes['last_name'];
    }
}
