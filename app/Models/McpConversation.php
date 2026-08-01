<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McpConversation extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['last_message_at' => 'datetime', 'archived_at' => 'datetime'];

    public function messages(): HasMany
    {
        return $this->hasMany(McpMessage::class, 'conversation_id')->orderBy('created_at')->orderBy('id');
    }
}
