<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FleetOrganization extends Model {
    protected $fillable = ['user_id', 'name'];
    public function drivers() { return $this->hasMany(FleetDriver::class, 'organization_id'); }
}
