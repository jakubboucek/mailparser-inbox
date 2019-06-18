<?php

namespace App\ApiModule\Presenters;

use App\Model;
use Nette;
use Nette\Application\Responses\JsonResponse;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tracy\Debugger;
use Tracy\ILogger;

class ReceiveEmailPresenter extends Nette\Application\UI\Presenter
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var Model\MailProcessor
     */
    private $processor;


    /**
     * @param string $tempDir
     * @param Model\MailProcessor $processor
     */
    public function __construct(string $tempDir, Model\MailProcessor $processor)
    {
        parent::__construct();
        $this->tempDir = $tempDir;
        $this->processor = $processor;
    }


    public function actionNotify(): void
    {
        try {
            $notification = $this->getPostNotificationMessage();

            $mimeOriginalPath = $this->getMimeOriginalPath($notification);

            $mail = $this->processor->fetchMessageFromS3($mimeOriginalPath);

            $this->processor->importMessage($mail, $mimeOriginalPath);

            $this->getPresenter()->sendResponse(new JsonResponse([
                'status' => 'OK'
            ]));
        } catch (InvalidMessageException $e) {
            Debugger::log($e->getMessage(), ILogger::EXCEPTION);
            $this->getHttpResponse()->setCode(Nette\Http\IResponse::S400_BAD_REQUEST);
            $this->getPresenter()->sendResponse(new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]));
        }
    }


    /**
     * @return array
     * @throws InvalidMessageException
     */
    private function getPostNotificationMessage(): array
    {
        $method = $this->getHttpRequest()->getMethod();
        if ($method !== Nette\Http\IRequest::POST) {
            throw new InvalidMessageException("Invalid request method ($method), expected POST");
        }


        $body = $this->getHttpRequest()->getRawBody();
        if ($body === null) {
            throw new InvalidMessageException('Empty request body');
        }

        $this->saveLastSnsNotification($body);

        try {
            $notification = Json::decode($body, Json::FORCE_ARRAY);
        } catch (JsonException $e) {
            throw new InvalidMessageException('Invalid request body fotmat (JSON)', 1, $e);
        }

        if (!isset($notification['Message'])) {
            throw new InvalidMessageException('Invalid request, missing Message field');
        }
        $message = $notification['Message'];

        try {
            $data = Json::decode($message, Json::FORCE_ARRAY);

            $type = \gettype($data);
            if ($type !== 'array') {
                throw new InvalidMessageException("Invalid request, Message field excepted Array, $type instead");
            }

            return $data;
        } catch (JsonException $e) {
            throw new InvalidMessageException('Invalid request, Message has invalid format (JSON)', 1, $e);
        }
    }


    /**
     * @param string $notification
     */
    private function saveLastSnsNotification(string $notification): void
    {
        file_put_contents($this->tempDir . '/last-sns-notification.txt', $notification);
    }


    /**
     * @param array $notification
     * @return string
     * @throws InvalidMessageException
     */
    private function getMimeOriginalPath(array $notification): string
    {
        if (!isset($notification['receipt']['action']['objectKey'])) {
            throw new InvalidMessageException("Message field missing 'receipt.action.objectKey' key");
        }

        return (string)$notification['receipt']['action']['objectKey'];
    }
}


