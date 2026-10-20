<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

        /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'identity',
        'phone_1',
        'points',
        'location_url',
        'phone_2',
        'code',
        'password', // You’ll need this if using password-based login
        'address',
        'notes',
        'credit',
    ];

        protected $hidden = [
            'password',
                    'remember_token',
        ];

    /** العملاء الذين سجّلوا بأنفسهم لا يظهرون في النظام حتى توافق الإدارة */
    protected static function booted()
    {
        static::addGlobalScope('approved', fn ($q) => $q->whereNull($q->getModel()->getTable() . '.signup_status'));
    }

    public function orders(){
        return $this->hasMany(Order::class, 'customer_id', 'id');
    }

    public function paids(){
        return $this->hasMany(PaidHistory::class, 'customer_id', 'id');
    }

    public function unpaidOrders()
    {
        return $this->hasMany(Order::class, 'customer_id', 'id')
            ->where(function ($query) {
                $query->where('status', 'unpaid')
                      ->orWhere(function ($query) {
                          $query->where('status', 'paid')
                                ->where('ship_price', '>', 0);
                      });
            });
    }

    public function depts(){
        return $this->hasMany(Dept::class, 'customer_id', 'id');
    }




    public function pointsHistory(){
        return $this->hasMany(PointsHistory::class)->latest();
    }

    public function messages(){
        return $this->hasMany(CustomerMessage::class)->latest();
    }

    public function deliveries(){
        return $this->hasMany(Delivery::class)->latest();
    }
}
