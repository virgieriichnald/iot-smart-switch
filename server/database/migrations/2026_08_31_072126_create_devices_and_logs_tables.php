<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique(); // e.g. ESP32_84CCA8
            $table->string('name');                // e.g. Smart Switch Kamar
            $table->string('type')->default('switch');
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('command_topic');       // e.g. home/ESP32_84CCA8/relay1/set
            $table->string('state_topic');         // e.g. home/ESP32_84CCA8/relay1/state
            $table->string('availability_topic');  // e.g. home/ESP32_84CCA8/status
            $table->string('state')->default('OFF'); // ON / OFF
            $table->boolean('is_online')->default(false);
            $table->unsignedInteger('channels')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // ON, OFF, TOGGLE, ONLINE, OFFLINE, DISCOVERED, RENAMED
            $table->string('source')->default('system'); // web, physical_button, mqtt, system
            $table->text('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_logs');
        Schema::dropIfExists('devices');
    }
};
