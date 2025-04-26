<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create("lightsearch_index", function (Blueprint $table) {
            $table->id();
            $table->string('token')->index();
            $table->unsignedBigInteger('record_id')->index();
            $table->string('model')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("lightsearch_index");
    }
};
