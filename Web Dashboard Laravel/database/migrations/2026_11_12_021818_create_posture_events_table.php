<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posture_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->decimal('angle', 7, 2);
            $table->string('direction', 20);
            $table->unsignedInteger('duration');
            $table->timestamp('started_at')->index();
            $table->timestamps();

            $table->index(['device_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posture_events');
    }
};
