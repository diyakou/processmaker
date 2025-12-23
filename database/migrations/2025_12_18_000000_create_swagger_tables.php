<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('swagger_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('swagger_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('swagger_source_id')->constrained('swagger_sources')->onDelete('cascade');
            $table->string('method', 10);
            $table->string('path');
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps();
        });

        Schema::create('swagger_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained('swagger_endpoints')->onDelete('cascade');
            $table->string('name');
            $table->string('type');
            $table->boolean('required')->default(false);
            $table->string('description')->nullable();
            $table->string('example')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('swagger_fields');
        Schema::dropIfExists('swagger_endpoints');
        Schema::dropIfExists('swagger_sources');
    }
};
