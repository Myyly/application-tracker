<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('cv_id')
                ->nullable()
                ->constrained('cvs')
                ->restrictOnDelete();
            $table->string('company_name', 255);
            $table->string('position', 255);
            $table->string('job_url', 2048)->nullable();
            $table->unsignedTinyInteger('status')
                ->default(0)
                ->comment(
                    '0=Applied, 1=Interviewing, 2=Offered, '
                    .'3=Rejected, 4=Withdrawn'
                );
            $table->date('applied_at');
            $table->date('deadline')->nullable();
            $table->date('last_contact_at')->nullable();
            $table->text('contact_note')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
