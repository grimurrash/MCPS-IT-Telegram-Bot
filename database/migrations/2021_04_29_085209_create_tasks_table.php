<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->string('id');
            $table->string('telegramMessageId')->nullable();
            $table->string('title')->nullable();
            $table->integer('percentComplete')->default(0);
            $table->integer('referenceCount')->default(0);
            $table->integer('checklistItemCount')->default(0);
            $table->integer('activeChecklistItemCount')->default(0);
            $table->dateTime('dueDateTime')->nullable();
            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
}
