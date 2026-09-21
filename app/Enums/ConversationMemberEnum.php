<?php

namespace App\Enums;

enum ConversationMemberEnum: string
{
    case ADMIN = 'admin';
    case OWNER = 'owner';
    case MEMBER = 'member';
}
