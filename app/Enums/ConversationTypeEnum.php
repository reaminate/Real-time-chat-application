<?php

namespace App\Enums;

enum ConversationTypeEnum: string
{
    case DIRECT = 'one to one conversation';
   case GROUP = 'group conversation';
}
