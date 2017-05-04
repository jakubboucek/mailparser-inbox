<?php

function adminer_object() {

	class AdminerSoftware extends Adminer {

		function name() {
			return 'Cortis';
		}

		function permanentLogin() {
			return "cd29453b374ab1d52718dac89b685f08";
		}

		function credentials() {
			return array('localhost', 'cortis-test-ro', 'ZWx8bgCqWFHFcet9ky4P');
		}

		function database() {
			return 'cortis-test';
		}

	}

	return new AdminerSoftware;
}
$_GET['username'] = '';
include "../app/libs/adminer/adminer-4.3.1-mysql-cs.php";


