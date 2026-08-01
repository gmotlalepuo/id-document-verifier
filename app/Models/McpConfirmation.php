<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpConfirmation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'preview' => 'array',
        'payload' => 'encrypted:array',
        'expires_at' => 'immutable_datetime',
        'approved_at' => 'immutable_datetime',
        'consumed_at' => 'immutable_datetime',
    ];
}
