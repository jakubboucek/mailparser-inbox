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
	private $tempDir;

	private $httpRequest;

	private $processor;

	public function __construct( $tempDir, Nette\Http\IRequest $httpRequest, Model\MailProcessor $processor ) {
		$this->tempDir = $tempDir;
		$this->httpRequest = $httpRequest;
		$this->processor = $processor;
	}


	public function actionNotify() {
		try {
			$notification = $this->getPostNotificationMessage();
			$mimeOriginalPath = $notification['action']['objectKey'];

			$mail = $this->processor->fetchMessageFromS3( $mimeOriginalPath );

			$this->processor->importMessage( $mail, $mimeOriginalPath );

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

	private function getPostNotificationMessage() {
		if($this->httpRequest->getMethod() != Nette\Http\IRequest::POST) {
			$method = $this->httpRequest->getMethod();
			throw new InvalidMessageException("Invalid request method ($method), expected POST");
		}


		$body = $this->httpRequest->getRawBody();
		if(empty($body)) {
			throw new InvalidMessageException("Empty request body");
		}

		$this->saveLastSnsNotification( $body );

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

	private function saveLastSnsNotification( $notification ) {
		file_put_contents($this->tempDir . '/last-sns-notification.txt', $notification);
	}
}

class InvalidMessageException extends \Exception {}
