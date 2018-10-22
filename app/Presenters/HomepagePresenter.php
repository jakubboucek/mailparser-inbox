<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette,
	App\Model;



class HomepagePresenter extends Nette\Application\UI\Presenter
{
	private $processor;

	public function __construct( Model\MailProcessor $processor ) {
		$this->processor = $processor;
	}


	public function renderDefault() {
		$this->template->emails = $this->processor->getEmails();
	}


	public function renderDetail( $id ) {
		$this->template->email = $this->processor->getEmail( $id );
		if(!$this->template->email) {
			throw new Nette\Application\BadRequestException( 'Message not found', '404');
		}

		$this->template->attachments = $this->processor->getAttachmentsFor( $id );
	}

	public function actionDownloadAttachment( $id ) {
		$attachment = $this->processor->getAttachment( $id );
		if(!$attachment) {
			throw new Nette\Application\BadRequestException( 'Attachment not found', '404');
		}

		$url = $this->processor->signUrl(
			$attachment->content_url
		);

		$this->redirectUrl( $url );
	}


	public function actionDownloadMessageSource( $id ) {
		$message = $this->processor->getEmail( $id );
		if(!$message) {
			throw new Nette\Application\BadRequestException( 'Message not found', '404');
		}

		$url = $this->processor->signUrl(
			$message->mime_source_url,
			'text/plain'
		);

		$this->redirectUrl( $url );
	}
}
