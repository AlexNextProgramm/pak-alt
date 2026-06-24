<?php

namespace APP\Model;

use Pet\Model\Model;

class UsersModel extends Model
{
    protected string $table = 'users';
    public array $hidden = ['password', 'auth'];

    public function getNameOrigin() {
        return $this->get('name') . " " . $this->get('surname');
    }
}
