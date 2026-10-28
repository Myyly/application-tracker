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
        Schema::create('cvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users') //Tạo FK tham chiếu users.id
                ->restrictOnDelete(); //Ngăn xóa vật lý User nếu vẫn còn CV liên kết
            $table->string('original_name', 255);
            $table->string('storage_path', 512);
            $table->string('disk', 50);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['disk', 'storage_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cvs');
    }
};
