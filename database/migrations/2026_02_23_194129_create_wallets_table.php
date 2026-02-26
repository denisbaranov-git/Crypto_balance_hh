<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->string('currency', 10);
            //$table->string('network');
            $table->string('network')->default('ethereum'); //tmp ethereum  delete default value //denis
            $table->decimal('balance', 20, 8)->default(0);
            //$table->string('address')->nullable();
            $table->bigInteger('last_scanned_block')->nullable();
            $table->string('last_scanned_block_hash')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'currency', 'network']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
