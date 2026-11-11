<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('fleet_organizations', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 150); $table->timestamps();
        });
        Schema::create('fleet_drivers', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained('fleet_organizations')->cascadeOnDelete();
            $table->string('name', 120); $table->string('email')->nullable();
            $table->string('license_label', 100)->nullable(); $table->string('vehicle_reference', 120)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('fleet_drivers'); Schema::dropIfExists('fleet_organizations'); }
};
