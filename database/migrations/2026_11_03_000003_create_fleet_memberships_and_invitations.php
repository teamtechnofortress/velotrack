<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('fleet_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('fleet_organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['manager', 'viewer'])->default('viewer');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            // One company membership per user for the Phase 7 client.
            $table->unique('user_id');
        });
        Schema::create('fleet_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('fleet_organizations')->cascadeOnDelete();
            $table->string('email', 255);
            $table->enum('role', ['manager', 'viewer']);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'email']);
        });
    }
    public function down(): void { Schema::dropIfExists('fleet_invitations'); Schema::dropIfExists('fleet_memberships'); }
};
