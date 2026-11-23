<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user app data. `client_id` is the id generated on the phone; (user_id, client_id) is unique,
 * so records can never be read or overwritten by another account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->string('name', 150);
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('year', 4)->nullable();
            $table->string('plate', 40)->nullable();
            $table->string('fuel', 30)->nullable();
            $table->string('type', 30)->nullable();
            $table->string('vin', 40)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_id']);
        });

        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->string('kind', 30);
            $table->string('title', 150);
            $table->decimal('amount', 14, 2);
            $table->string('date', 10);
            $table->decimal('quantity', 12, 3)->nullable();
            $table->string('unit', 10)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_id']);
            $table->index(['user_id', 'vehicle_id']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->string('title', 150);
            $table->string('due', 10);
            $table->boolean('done')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'client_id']);
            $table->index(['user_id', 'vehicle_id']);
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->string('date', 10);
            $table->boolean('tyre')->default(true);
            $table->boolean('brakes')->default(true);
            $table->boolean('lights')->default(true);
            $table->boolean('battery')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_id']);
            $table->index(['user_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('vehicles');
    }
};
