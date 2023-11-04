<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paystack_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('billable_id');
            $table->string('billable_type');

            $table->string('type');
            $table->string('paystack_id')->nullable();
            $table->string('paystack_code')->nullable();
            $table->string('paystack_plan');
            $table->integer('quantity');

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(['billable_id', 'billable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_subscriptions');
    }
};
