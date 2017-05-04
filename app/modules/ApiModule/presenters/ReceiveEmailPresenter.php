<?php

namespace App\ApiModule\Presenters;

use Nette,
	Nette\Diagnostics\Debugger,
	Nette\Utils\Json,
	Nette\Utils\JsonException,
	App\Model,
	Nette\Application\Responses\JsonResponse;

class ReceiveEmailPresenter extends Nette\Application\UI\Presenter
{
	private $httpRequest;

	public function __construct( Nette\Http\IRequest $httpRequest ) {
		$this->httpRequest = $httpRequest;
	}


	public function actionNotify() {
		try {
			$message = $this->getPostMessage();
			Debugger::log($message);

			$this->getPresenter()->sendResponse( new JsonResponse( [
				'status'=>'OK'
			] ) );
		}
		catch (InvalidMessageException $e) {
			Debugger::log($e->getMessage());
			$this->getPresenter()->sendResponse( new JsonResponse( [
				'status'=>'error',
				'message'=>$e->getMessage(),
			] ) );
		}
	}

	private function getPostMessage() {
		if($this->httpRequest->getMethod() != Nette\Http\IRequest::POST) {
			$method = $this->httpRequest->getMethod();
			throw new InvalidMessageException("Invalid request method ($method), expected POST");
		}


		$body = $this->httpRequest->getRawBody();
		if(empty($body)) {
			throw new InvalidMessageException("Empty request body");
		}


		try {
			$notification = Json::decode($body, Json::FORCE_ARRAY);
		}
		catch(JsonException $e) {
			throw new InvalidMessageException("Invalid request body fotmat (JSON)", 1, $e);
		}

		if(!isset($notification['Message'])) {
			throw new InvalidMessageException("Invalid request, missing Message field");
		}
		$message = $notification['Message'];

		try {
			return Json::decode($message, Json::FORCE_ARRAY);
		}
		catch(JsonException $e) {
			throw new InvalidMessageException("Invalid request, Message has invalid format (JSON)", 1, $e);
		}
	}
}

class InvalidMessageException extends \Exception {}
