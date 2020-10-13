<?php

function adminer_object()
{
    class AdminerSoftware extends Adminer
    {

        public function name()
        {
            return 'Mail parser test';
        }

        public function permanentLogin($g = false)
        {
            return "556b6deac3d71be237f8ce115b7c60ac4bbb7297";
        }

        public function credentials()
        {
            return ['localhost', 'mailparser-ro', 'qfmioKqxq27nfE8DDktH'];
        }

        function login($login, $password) {
            // validate user submitted credentials
            return true;
        }

        public function database()
        {
            return 'mailparser';
        }

    }

    return new AdminerSoftware;
}

require "../app/libs/adminer/adminer-4.7.7-mysql-cs.php";


