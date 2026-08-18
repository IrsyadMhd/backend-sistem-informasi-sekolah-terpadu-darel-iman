<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class MutabaahEntry extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['student_id', 'agenda_id', 'entry_date', 'status', 'mentor_id', 'note'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function agenda()
    {
        return $this->belongsTo(MutabaahAgenda::class, 'agenda_id');
    }

    public function mentor()
    {
        return $this->belongsTo(Employee::class, 'mentor_id');
    }
}
