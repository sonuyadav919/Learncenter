<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('password')->nullable();
            $table->DateTime('last_login')->nullable();
            $table->dateTime('online_status')->nullable();
            $table->string('skills')->nullable();
            $table->text('about')->nullable();
            $table->text('education')->nullable();
            $table->longText('address')->nullable();
            $table->string('avatar')->nullable();
            $table->tinyInteger('entry_by')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users');
    }
}
