<?php

namespace Tests\LaravelSoapServer\Stubs;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\Carbon $created_at
 */
class User extends Authenticatable
{
    protected $guarded = [];
}
