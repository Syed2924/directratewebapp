<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Offices, Jawan Markaz centers and hostels all belong to a district.
        foreach (['offices', 'centers', 'hostels'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('district_id')->constrained()->restrictOnDelete();
                $table->string('name', 150);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['district_id', 'name']);
            });
        }

        // scope_type: global | district | office | center | hostel
        // A "global" row (scope_id null) grants access to every organizational unit.
        Schema::create('user_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_scopes');
        Schema::dropIfExists('hostels');
        Schema::dropIfExists('centers');
        Schema::dropIfExists('offices');
        Schema::dropIfExists('districts');
    }
};
