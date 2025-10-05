<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('direct'); // direct, group (future)
            $table->string('name')->nullable(); // for groups in the future
            $table->unsignedBigInteger('last_message_id')->nullable();
            //$table->foreignId('last_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamps();
            
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};