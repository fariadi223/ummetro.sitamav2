<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log sinkron unit jurnal SIMAKU (id_uk + kodeunit) rincian tagihan SPP.
 * Header = satu proses sinkron (tombol web / command / satu rincian),
 * detail = hasil per bukti jurnal.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('bak_sinkron_jurnal_log', function (Blueprint $t) {
      $t->id();
      $t->string('sumber', 10)->comment('web | command | manual');
      $t->string('user_id', 64)->nullable()->comment('Auth::id() operator; null bila dari command');
      $t->string('user_nama')->nullable()->comment('nama operator, atau user OS bila dari command');
      $t->string('id_smt', 5)->nullable();
      $t->uuid('id_sms')->nullable();
      $t->string('prodi')->nullable();
      $t->jsonb('filter')->nullable()->comment('filter laporan saat sinkron (angkatan, ma, npm, dari, sampai)');
      $t->integer('id_uk')->comment('unit SIMAKU tujuan');
      $t->string('unit_kode', 30)->nullable();
      $t->string('unit_nama')->nullable();
      $t->boolean('dry_run')->default(false);
      $t->boolean('force')->default(false);
      $t->integer('total')->default(0)->comment('jumlah rincian yang direncanakan');
      $t->integer('diproses')->default(0);
      $t->integer('diubah')->default(0);
      $t->integer('sudah')->default(0);
      $t->integer('belum_jurnal')->default(0);
      $t->integer('gagal')->default(0);
      $t->timestamp('mulai_at')->useCurrent();
      $t->timestamp('selesai_at')->nullable()->comment('null = masih berjalan / dihentikan');

      $t->index('mulai_at');
      $t->index(['id_smt', 'id_sms']);
    });

    Schema::create('bak_sinkron_jurnal_log_dtl', function (Blueprint $t) {
      $t->id();
      $t->foreignId('log_id')->constrained('bak_sinkron_jurnal_log')->cascadeOnDelete();
      $t->string('npm', 20)->nullable();
      $t->string('key_bank', 100);
      $t->string('bukti', 50);
      $t->string('hasil', 15)->comment('diubah | sudah | belum_jurnal | gagal');
      $t->string('id_uk_lama', 50)->nullable()->comment('id_uk baris jurnal sebelum sinkron (dipisah koma bila beragam)');
      $t->integer('baris')->nullable()->comment('jumlah baris jurnal yang diubah SIMAKU');
      $t->text('pesan')->nullable();
      $t->timestamp('created_at')->useCurrent();

      $t->index('bukti');
      $t->index('npm');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('bak_sinkron_jurnal_log_dtl');
    Schema::dropIfExists('bak_sinkron_jurnal_log');
  }
};
