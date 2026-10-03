<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('kols', function (Blueprint $table) {
            $table->id();
            $table->char('uid', 36)->unique();
            $table->string('referral_code', 8)->unique();
            $table->string('name', 150);
            $table->string('username', 150);
            $table->string('whatsapp', 20);
            $table->string('province_id', 50);
            $table->string('province_name', 100);
            $table->string('city_id', 50);
            $table->string('city_name', 100);
            
            // REVISI: Mengubah dari foreignId angka menjadi string teks campaign
            $table->string('type')->default('Reguler');
            
            $table->date('campaign_start_date');
            $table->date('campaign_end_date');
            $table->tinyInteger('status')->default(1)->comment('1 = Active, 0 = Inactive');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('kols');
    }
};