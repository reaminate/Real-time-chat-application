<?php

namespace App\Enums;

enum ConversationRoleEnum: string
{
    case ADMIN = 'admin';
    case OWNER = 'owner';
    case MEMBER = 'member';
}
