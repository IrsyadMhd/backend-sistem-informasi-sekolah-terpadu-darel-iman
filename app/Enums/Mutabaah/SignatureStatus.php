<?php

namespace App\Enums\Mutabaah;

enum SignatureStatus: string
{
    case Approved = 'approved';
    case ClarificationRequested = 'clarification_requested';
    case UnableToVerify = 'unable_to_verify';
}
