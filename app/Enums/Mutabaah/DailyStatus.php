<?php

namespace App\Enums\Mutabaah;

enum DailyStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case ParentReviewed = 'parent_reviewed';
    case ParentSigned = 'parent_signed';
    case FollowUp = 'follow_up';
}
