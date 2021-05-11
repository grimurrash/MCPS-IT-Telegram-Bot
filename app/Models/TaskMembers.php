<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskMembers extends Model
{
    use HasFactory;
    protected $guarded = [];
    public $timestamps = false;
    public $incrementing = false;
    public $keyType = 'string';
    public $primaryKey = ['task_id', 'member_id'];
}
