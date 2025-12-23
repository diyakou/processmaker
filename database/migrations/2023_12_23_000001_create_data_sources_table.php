<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('rest'); // rest, soap, graphql
            $table->string('authtype')->default('NONE'); // NONE, BASIC, OAUTH2_BEARER, OAUTH2_PASSWORD
            $table->json('endpoints')->nullable(); // endpoint configurations
            $table->json('credentials')->nullable(); // auth credentials
            $table->string('status')->default('ACTIVE'); // ACTIVE, INACTIVE
            $table->boolean('verify_certificate')->default(true);
            $table->boolean('debug_mode')->default(false);
            $table->timestamps();
            
            $table->index(['name', 'type']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('data_sources');
    }
};