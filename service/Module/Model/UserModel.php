<?php

namespace Module\Model;

use Pet\Model\Model;

class UserModel extends Model
{
    protected string $table = 'users';
    public array $hidden = ['password', 'auth'];
}