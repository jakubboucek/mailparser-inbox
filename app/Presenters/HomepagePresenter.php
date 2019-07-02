<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model;
use Nette;


class HomepagePresenter extends Nette\Application\UI\Presenter
{
    private $processor;


    public function __construct(Model\MailProcessor $processor)
    {
        $this->processor = $processor;
    }


    public function renderDefault(): void
    {
        $this->template->emails = $this->processor->getEmails();
    }


    public function renderDetail(int $id): void
    {
        $this->template->email = $this->processor->getEmail($id);
        if (!$this->template->email) {
            throw new Nette\Application\BadRequestException('Message not found', '404');
        }

        $this->template->attachments = $this->processor->getAttachmentsFor($id);
    }


    public function actionDownloadAttachment(int $id): void
    {
        $attachment = $this->processor->getAttachment($id);
        if (!$attachment) {
            throw new Nette\Application\BadRequestException('Attachment not found', '404');
        }

        $url = $this->processor->signUrl(
            (string)$attachment->content_url
        );

        $this->redirectUrl($url);
    }


    public function actionDownloadMessageSource(int $id): void
    {
        $message = $this->processor->getEmail($id);
        if (!$message) {
            throw new Nette\Application\BadRequestException('Message not found', '404');
        }

        $url = $this->processor->signUrl(
            (string)$message->mime_source_url,
            'text/plain'
        );

        $this->redirectUrl($url);
    }
}
