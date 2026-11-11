<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FleetInvitation extends Model {
    protected $fillable = ['organization_id','email','role','token_hash','expires_at','accepted_at'];
    protected $hidden = ['token_hash'];
    protected $casts = ['expires_at'=>'datetime','accepted_at'=>'datetime'];
}
