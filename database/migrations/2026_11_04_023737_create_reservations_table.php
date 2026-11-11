<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('id_order')->unique();
            $table->string('kode_booking');
            $table->string('nama_pembeli'); // Nama pembeli tiket
            $table->string('email_pembeli'); // Email pembeli tiket
            $table->foreignId('kol_id')->nullable()->constrained('kols')->nullOnDelete(); // Relasi ke tabel kols
            $table->decimal('total_bayar', 12, 2);
            $table->integer('qty');
            $table->date('kedatangan');
            $table->string('status')->default('Aktif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
