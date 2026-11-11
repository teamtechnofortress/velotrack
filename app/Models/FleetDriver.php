<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FleetDriver extends Model {
    protected $fillable = ['organization_id', 'name', 'email', 'license_label', 'vehicle_reference'];
    protected $hidden = [];
}
