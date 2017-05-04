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
}
