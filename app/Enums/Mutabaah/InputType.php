<?php

namespace App\Enums\Mutabaah;

enum InputType: string
{
    case Status = 'status';
    case YesNo = 'yes_no';
    case Checklist = 'checklist';
    case Number = 'number';
    case Duration = 'duration';
    case Pages = 'pages';
    case Verses = 'verses';
    case Text = 'text';
}
