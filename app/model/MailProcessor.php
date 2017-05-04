<?php

namespace App\Model;

use App\Aws\S3Storage,
	App\Aws\S3Object,
	ZBateson\MailMimeParser\Message as ParserMessage,
	Nette\Database\Context,
	Nette\Utils\Random,
	Nette\Utils\Json,
	Nette\Diagnostics\Debugger;

/*
	- parse MIME
	- save main body
	- save attachments
*/

class MailProcessor  {
	const TABLE_MESSAGES = 'message';
	const TABLE_ATTACHMENTS = 'attachment';
	const ATTACHMENTS_STORAGE_PATH = '/attachments';

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
		$messageId = $messageRow->id;

		$this->saveAttachments($message, $messageId);
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

	private function saveAttachments( $message, $messageId ) {
		foreach( $message->getAllAttachmentParts() as $part ) {
			$filename = Random::generate( 32 );
			$path = self::ATTACHMENTS_STORAGE_PATH . '/' . $filename;
			$name = $this->getMimePartName( $part, $filename );
			$content = $part->hasContent() ? $part->getContent() : '';
			$contentType = $this->getMimePartContentType( $part );

			$object = S3Object::createFromString( $content, $contentType );
			$object->setFileName( $name );
			$url = $this->s3->putObject( $object, $path );

			$partInformation = [
				'name' => $name,
				'content_type' => $part->getHeaderValue( 'content-type' ),
				'size' => strlen( $content ),
				'headers' => $this->serializeHeaders( $part->getHeaders() ),
				'content_url' => $url,
			];

			$row = $this->db->table( self::TABLE_ATTACHMENTS )->insert(
				$partInformation + [ 'message_id' => $messageId ]
			);
		}
	}

	private function serializeHeaders( $headers ) {
		$rawHeaders = [];
		foreach($headers as $headerName => $header) {
			$rawHeaders[$headerName] = $header->getRawValue();
		}
		return Json::encode( $rawHeaders, Json::PRETTY );
	}

	private function getMimePartName( $mimePart, $default = NULL ) {
		$header = $mimePart->getHeaderParameter( 'content-disposition', 'filename' );
		Debugger::log($header);
		if( $header ) {
			return $header;
		}

		$header = $mimePart->getHeaderValue( 'content-type', 'name' );
		Debugger::log($header);
		if( $header ) {
			return $header;
		}

		return $default;
	}
}
