<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'birthday',
        'phone_ext',
        'company_position',
        'departament',
        'profile_image',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends  = ['profile_image', 'nombre_completo'];

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

    public function setProfileImageAttribute($value)
    {
        $filename = 'avatars/' . md5($value . time());
        $attribute_name = "profile_image";
        $disk = 'public';
        $destination_path = "uploads/avatars";

        // if the image was erased
        if ($value == null) {
            Storage::disk($disk)->delete($this->{$attribute_name});
            $this->attributes[$attribute_name] = null;
        }

        // if a base64 was sent, store it in the db
        if (Str::startsWith($value, 'data:image')) {
            $image = \Intervention\Image\ImageManagerStatic::make($value)->encode('jpg', 90);
            $filename = $filename . '.jpg';
            Storage::disk($disk)->put($destination_path . '/' . $filename, $image->stream());
            Storage::disk($disk)->delete($this->{$attribute_name});

            $public_destination_path = Str::replaceFirst('public/', '', $destination_path);
            $this->attributes[$attribute_name] = $public_destination_path . '/' . $filename;
        }
    }

    public function getProfileImageAttribute()
    {
        if (!isset($this->attributes['profile_image'])) {
            return null;
        }
        return Storage::disk('public')->url($this->attributes['profile_image']);
    }

    public function getNombreCompletoAttribute()
    {
        return $this->attributes['first_name'] . ' ' . $this->attributes['last_name'];
    }
}
