<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $timestamps = false;

    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PENDING = 'pending';

    const CLASS_PEASANT = "0";
    const CLASS_USER = "1";
    const CLASS_POWER_USER = "2";
    const CLASS_ELITE_USER = "3";
    const CLASS_CRAZY_USER = "4";
    const CLASS_INSANE_USER = "5";
    const CLASS_VETERAN_USER = "6";
    const CLASS_EXTREME_USER = "7";
    const CLASS_ULTIMATE_USER = "8";
    const CLASS_NEXUS_MASTER = "9";
    const CLASS_VIP = "10";
    const CLASS_RETIREE = "11";
    const CLASS_UPLOADER = "12";
    const CLASS_MODERATOR = "13";
    const CLASS_ADMINISTRATOR = "14";
    const CLASS_SYSOP = "15";
    const CLASS_STAFFLEADER = "16";

    public static $classes = [
        self::CLASS_PEASANT => ['text' => 'Peasant'],
        self::CLASS_USER => ['text' => 'User'],
        self::CLASS_POWER_USER => ['text' => 'Power User'],
        self::CLASS_ELITE_USER => ['text' => 'Elite User'],
        self::CLASS_CRAZY_USER => ['text' => 'Crazy User'],
        self::CLASS_INSANE_USER => ['text' => 'Insane User'],
        self::CLASS_VETERAN_USER => ['text' => 'Veteran User'],
        self::CLASS_EXTREME_USER => ['text' => 'Extreme User'],
        self::CLASS_ULTIMATE_USER => ['text' => 'Eltimate User'],
        self::CLASS_NEXUS_MASTER => ['text' => 'Nexus Master'],
        self::CLASS_VIP => ['text' => 'Vip'],
        self::CLASS_RETIREE => ['text' => 'Retiree'],
        self::CLASS_UPLOADER => ['text' => 'Uploader'],
        self::CLASS_MODERATOR => ['text' => 'Moderator'],
        self::CLASS_ADMINISTRATOR => ['text' => 'Administrator'],
        self::CLASS_SYSOP => ['text' => 'Sysop'],
        self::CLASS_STAFFLEADER => ['text' => 'Staff Leader'],
    ];

    public function getClassTextAttribute()
    {
        return self::$classes[$this->class]['text'] ?? '';
    }



    /**
     * 为数组 / JSON 序列化准备日期。
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format($this->dateFormat ?: 'Y-m-d H:i:s');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['username', 'email', 'passhash', 'secret', 'editsecret', 'added'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [

    ];
}