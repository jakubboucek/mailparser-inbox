<?php

namespace App\Aws;

use Aws\Sdk as Sdk,
	Nette\SmartObject;

class Aws {

    use SmartObject;

	private $awsInstance;
	private $appConfig;

	public function __construct( $appConfig ) {
		$this->appConfig = $appConfig;
	}

	public function getS3() {
		return $this->getAwsInstance()->createS3();
	}

	public function getSns() {
		return $this->getAwsInstance()->createSns();
	}

	public function getAwsInstance() {
		if( ! $this->awsInstance) {
			$this->awsInstance = new Sdk( $this->appConfig );
		}
		return $this->awsInstance;
	}
}
