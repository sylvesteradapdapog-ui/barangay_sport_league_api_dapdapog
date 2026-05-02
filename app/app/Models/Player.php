<?php

namespace App\Models;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'birthdate',
        'position',
    ];

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withPivot('jersey_number');
    }
}
