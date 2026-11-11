<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VeloTrackBackup extends Model {
    protected $fillable = ['user_id', 'snapshot'];
    protected $casts = ['snapshot' => 'array'];
}
