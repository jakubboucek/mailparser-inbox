<?php

namespace App\Model;

use App\Aws\S3Storage,
	ZBateson\MailMimeParser\Message as ParserMessage,
	Nette\Database\Context,
	Nette\Utils\Json;

/*
	- parse MIME
	- save main body
	- save attachments
*/

class MailProcessor  {
	const TABLE_MESSAGES = 'message';
	const TABLE_ATTACHMENTS = 'attachment';

	private $s3;

	private $db;

	public function __construct(S3Storage $s3, Context $db) {
		$this->s3 = $s3;
		$this->db = $db;
	}

	public function fetchMessageFromS3( $path ) {
		return (string) $this->s3->getObject( $path )->Body;
	}

	public function importMessage( $mimeString, $mimeOriginalPath = NULL ) {
		$message = ParserMessage::from( $mimeString );

		$messageRow = $this->saveMessage($message, $mimeOriginalPath);

	}

	private function saveMessage($message, $mimeOriginalPath = NULL) {

		$messageInformation = [
			'mime_message_id' => $message->getHeaderValue('message-id'),
			'date' => new \DateTime($message->getHeaderValue('date')),
			'from' => $message->getHeaderValue('from'),
			'subject' => $message->getHeaderValue('subject'),
			'body_text' => $message->getTextPartCount() ? $message->getTextPart()->getContent() : NULL,
			'body_html' => $message->getHtmlPartCount() ? $message->getHtmlPart()->getContent() : NULL,
			'headers' => $this->serializeHeaders( $message->getHeaders() ),
			'mime_source_url' => $this->s3->path2Url( $mimeOriginalPath ),
		];

		$row = $this->db->table( self::TABLE_MESSAGES )->insert($messageInformation);
		return $row;
	}

	private function serializeHeaders( $headers ) {
		$rawHeaders = [];
		foreach($headers as $headerName => $header) {
			$rawHeaders[$headerName] = $header->getRawValue();
		}
		return Json::encode( $rawHeaders, Json::PRETTY );
	}
}
