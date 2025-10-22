<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Schedule extends Model
{
   use HasFactory;

    protected $fillable = [
        'start_time',
        'end_time',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
