<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class conversation_member extends Pivot
{
    /** @use HasFactory<\Database\Factories\App\Models\conversationMemberFactory> */
    use HasFactory;
}
