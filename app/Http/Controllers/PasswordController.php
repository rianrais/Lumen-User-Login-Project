<?php

namespace App\Http\Controllers;

use App\ResetsPasswords as ResetsPasswords;
use Notification;

class PasswordController extends AuthController
{
    use ResetsPasswords;

    public function __construct()
    {
        $this->broker = 'users';
    }
}

?>