<?php

namespace App\Enums;

enum AttachmentFileTypeEnum: string
{
    case PDF = 'application/pdf';
    case DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
}
