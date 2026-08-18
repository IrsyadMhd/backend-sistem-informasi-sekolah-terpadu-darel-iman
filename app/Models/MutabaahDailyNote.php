<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class MutabaahDailyNote extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['student_id', 'entry_date', 'mentor_id', 'note'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function mentor()
    {
        return $this->belongsTo(Employee::class, 'mentor_id');
    }
}
