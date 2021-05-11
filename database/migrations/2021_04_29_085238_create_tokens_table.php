<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->text('accessToken')->nullable();
            $table->text('refreshToken')->nullable();
            $table->string('tokenExpires')->nullable();
            $table->string('userName')->nullable();
            $table->string('userEmail')->nullable();
            $table->string('userTimeZone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
}
