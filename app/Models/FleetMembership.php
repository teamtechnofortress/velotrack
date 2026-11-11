<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FleetMembership extends Model {
    protected $fillable = ['organization_id','user_id','role'];
    public function user() { return $this->belongsTo(User::class); }
    public function organization() { return $this->belongsTo(FleetOrganization::class, 'organization_id'); }
}
