<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Species extends Model
{
    protected $fillable = ['slug', 'name_sr', 'name_latin', 'is_active'];
}
