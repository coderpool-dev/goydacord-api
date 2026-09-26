<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class DailyStat extends Model
{
    protected $table = 'stats_daily';

    protected $primaryKey = 'day';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'day',
        'online_peak',
        'signups',
        'calls_started',
    ];

    protected $casts = [
        'day' => 'date',
        'online_peak' => 'integer',
        'signups' => 'integer',
        'calls_started' => 'integer',
    ];
}
