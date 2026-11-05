<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->text('email')->unique();
            $table->text('password');
            $table->boolean('can_view_any')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('signature');
            $table->text('external_number')->nullable();
            $table->text('customer_email')->nullable();
            $table->text('customer_name')->nullable();
            $table->text('state')->default('unshipped');
            $table->timestampTz('purchased_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->index('signature');
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->text('name')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->timestampsTz(6);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->text('sku')->nullable();
            $table->text('ean')->nullable();
            $table->jsonb('name')->nullable();
            $table->timestampsTz(6);
            $table->softDeletesTz('deleted_at', 6);
        });
    }
};
