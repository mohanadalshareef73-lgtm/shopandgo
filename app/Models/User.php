<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type_id',
        'status',
        'doc_id',
        'phone',
        'courier_perms',
        'courier_zone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function type(){
        return $this->hasOne(Type::class, 'id', 'type_id');
    }

    public function safe(){
        return Treasury::all()->sum('total');
    }

    public function safeDollar(){
        return TreasuryDollar::all()->sum('total');
    }

    public function payments(){
        return PaymentMethod::all();
    }

    public function safeEuro(){
        return TreasuryEuro::all()->sum('total');
    }
    public function notifications(){
        return $this->hasMany(Notif_orgs::class, 'user_id', 'id');
    }


    public const COURIER_PERMS = [
        'see_amount' => 'يشوف المبلغ المطلوب',
        'collect_bank' => 'يسجل دفع مصرف',
        'edit_recipient' => 'يعدل رقم وعنوان المستلم',
        'see_customer' => 'يشوف بيانات الزبون كاملة',
    ];

    /** مكتب توصيل خارجي (لا يدخل للنظام، ويُحاسَب على كل الأجرة) */
    public function isOffice(): bool
    {
        return $this->courier_kind === 'office';
    }

    public function isCourier(): bool
    {
        return (int) $this->type_id === 4;
    }

    /** صلاحية مندوب (الافتراضي: الكل مفعل ما عدا تعديل المستلم) */
    public function courierCan(string $perm): bool
    {
        $p = json_decode((string) $this->courier_perms, true) ?: [];

        return array_key_exists($perm, $p) ? (bool) $p[$perm] : $perm !== 'edit_recipient';
    }

    public function courierDeliveries(){
        return $this->hasMany(Delivery::class, 'courier_id');
    }
}
