<?php

namespace App\Enums\Mutabaah;

enum DetailStatus: string
{
    case Good = 'good';
    case Less = 'less';
    case NotDone = 'not_done';
    case NotApplicable = 'na';
}
