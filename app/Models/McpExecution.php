<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpExecution extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'outcome_summary' => 'array',
    ];
}
