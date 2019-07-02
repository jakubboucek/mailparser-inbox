<?php

namespace App\Model;

use App\Aws\S3Object;
use App\Aws\S3Storage;
use DateTime;
use DateTimeZone;
use Nette\Database\Context;
use Nette\Database\ResultSet;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\InvalidArgumentException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Utils\Random;
use ZBateson\MailMimeParser\Header\AbstractHeader;
use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\Message\Part\MessagePart;
use ZBateson\MailMimeParser\Message\Part\MimePart;

class MailProcessor
{
    private const TABLE_MESSAGES = 'message';
    private const TABLE_ATTACHMENTS = 'attachment';
    private const ATTACHMENTS_STORAGE_PATH = '/attachments';

    /**
     * @var S3Storage
     */
    private $s3;

    /**
     * @var Context
     */
    private $db;


    /**
     * @param S3Storage $s3
     * @param Context $db
     */
    public function __construct(S3Storage $s3, Context $db)
    {
        $this->s3 = $s3;
        $this->db = $db;
    }


    /**
     * @param string $path
     * @return string
     */
    public function fetchMessageFromS3(string $path): string
    {
        return (string)$this->s3->getObject($path)->Body;
    }


    /**
     * @param string $mimeString
     * @param null|string $mimeOriginalPath
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function importMessage(string $mimeString, ?string $mimeOriginalPath = null): void
    {
        $message = Message::from($mimeString);

        $messageRow = $this->saveMessage($message, $mimeOriginalPath);
        $messageId = $messageRow->id;

        $this->saveAttachments($message, $messageId);
    }


    /**
     * @param Message $message
     * @param null|string $mimeOriginalPath
     * @return bool|int|ActiveRow
     * @throws JsonException
     */
    private function saveMessage(Message $message, ?string $mimeOriginalPath = null)
    {
        $date = new DateTime($message->getHeaderValue('date', date('r')));
        // Set timezone to  system TZ (to save to DB)
        $timezone = new DateTimeZone(date_default_timezone_get());
        $date->setTimezone($timezone);

        $messageInformation = [
            'mime_message_id' => $message->getHeaderValue('message-id'),
            'date' => $date,
            'from' => $message->getHeaderValue('from', '(unknown)'),
            'subject' => $message->getHeaderValue('subject', '(no subject)'),
            'body_text' => $message->getTextPartCount() ? $message->getTextPart()->getContent() : null,
            'body_html' => $message->getHtmlPartCount() ? $message->getHtmlPart()->getContent() : null,
            'headers' => $this->serializeHeaders($message->getAllHeaders()),
            'mime_source_url' => $this->s3->path2Url($mimeOriginalPath),
        ];

        $row = $this->db->table(self::TABLE_MESSAGES)->insert($messageInformation);
        return $row;
    }


    /**
     * @param Message $message
     * @param int $messageId
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    private function saveAttachments(Message $message, int $messageId): void
    {
        /** @var MimePart $part */
        foreach ($message->getAllAttachmentParts() as $part) {
            $filename = Random::generate(32);
            $path = self::ATTACHMENTS_STORAGE_PATH . '/' . $filename;
            $name = $this->getMimePartName($part, $filename);
            $content = $part->hasContent() ? $part->getContent() : '';
            $contentType = $part->getContentType('application/octet-stream');

            $object = S3Object::createFromString($content, $contentType);
            $object->setFileName($name);
            $url = $this->s3->putObject($object, $path);

            $partInformation = [
                'name' => $name,
                'content_type' => $contentType,
                'size' => strlen($content),
                'headers' => $this->serializeHeaders($part->getAllHeaders()),
                'content_url' => $url,
            ];

            $this->db->table(self::TABLE_ATTACHMENTS)->insert(
                $partInformation + ['message_id' => $messageId]
            );
        }
    }


    /**
     * @param AbstractHeader[] $headers
     * @return string
     * @throws JsonException
     */
    private function serializeHeaders(array $headers): string
    {
        $rawHeaders = [];
        foreach ($headers as $headerName => $header) {
            $rawHeaders[$header->getName()] = $header->getRawValue();
        }
        return Json::encode($rawHeaders, Json::PRETTY);
    }


    /**
     * @param MessagePart $mimePart
     * @param string|null $default
     * @return string|null
     */
    private function getMimePartName(MessagePart $mimePart, ?string $default = null): ?string
    {
        $header = $mimePart->getFilename();
        return $header ?? $default;
    }


    /**
     * @return ResultSet
     */
    public function getEmails(): ResultSet
    {
        $selection = $this->db->query('
			SELECT `m`.*, COUNT(`a`.id ) as `attachments`
			FROM `' . self::TABLE_MESSAGES . '` as `m`
			LEFT JOIN `' . self::TABLE_ATTACHMENTS . '` as `a` ON m.id = a.message_id
			GROUP BY `m`.`id`
			ORDER BY `m`.`date` DESC;
		');
        return $selection;
    }


    /**
     * @param int $id
     * @return ActiveRow
     */
    public function getEmail(int $id): ActiveRow
    {
        $row = $this->db->table(self::TABLE_MESSAGES)
            ->where('id', $id)
            ->fetch();
        return $row;
    }


    /**
     * @param int $messageId
     * @return Selection
     */
    public function getAttachmentsFor(int $messageId): Selection
    {
        $selection = $this->db->table(self::TABLE_ATTACHMENTS)
            ->where('message_id', $messageId);
        return $selection;
    }


    /**
     * @param int $id
     * @return ActiveRow
     */
    public function getAttachment(int $id): ActiveRow
    {
        $row = $this->db->table(self::TABLE_ATTACHMENTS)
            ->where('id', $id)
            ->fetch();
        return $row;
    }


    /**
     * @param string $url
     * @param null|string $contentTypeOverride
     * @param null|string $contentDispositionOverride
     * @return string
     */
    public function signUrl(
        string $url,
        ?string $contentTypeOverride = null,
        ?string $contentDispositionOverride = null
    ): string {
        $overrides = [];
        if ($contentTypeOverride) {
            $overrides['ResponseContentType'] = $contentTypeOverride;
        }
        if ($contentDispositionOverride) {
            $overrides['ResponseContentDisposition'] = $contentDispositionOverride;
        }

        return $this->s3->signUrl($url, '+ 5 minutes', $overrides);
    }
}
