<?php

function adminer_object() {

	class AdminerSoftware extends Adminer {

		function name() {
			return 'Mail parser test';
		}

		function permanentLogin() {
			return "cd29453b374ab1d52718dac89b685f08";
		}

		function credentials() {
			return array('localhost', 'mailparser-ro', 'qfmioKqxq27nfE8DDktH');
		}

		function database() {
			return 'mailparser';
		}

	}

	return new AdminerSoftware;
}
$_GET['username'] = '';
include "../app/libs/adminer/adminer-4.3.1-mysql-cs.php";


