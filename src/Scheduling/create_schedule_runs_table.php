<?php

use Maharlika\Database\Schema\Blueprint;
use Maharlika\Database\Schema\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('schedule_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->string('description')->nullable();
            $table->boolean('success')->default(false);
            $table->string('duration')->nullable();
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();
            
            $table->index(['command', 'ran_at']);
            $table->index('success');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('schedule_runs');
    }
};
