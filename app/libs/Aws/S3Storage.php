<?php

namespace App\Aws;

use Aws\S3\S3Client;
use Guzzle\Http\Mimetypes;
use Nette\InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class S3Storage
{

    private $appConfig;
    private $aws;

    private $_s3;


    public function __construct(array $appConfig, Aws $aws)
    {
        $this->appConfig = $appConfig;
        $this->aws = $aws;
    }


    public function putObject($sourceObject, $path): string
    {
        if ($sourceObject instanceof S3Object) {
            $object = $sourceObject->toArray();
        } else {
            $object = $sourceObject;
        }

        $key = $this->path2Key($path);
        $object += [
            'Bucket' => $this->appConfig['bucket'],
            'Key' => $key,
        ];

        $result = $this->getS3()->putObject($object);

        return $this->path2Url($path);
    }


    public function copyObject($sourceObject, $sourcePath, $path): string
    {
        if ($sourceObject instanceof S3Object) {
            $object = $sourceObject->toArray();
        } else {
            $object = $sourceObject;
        }

        $bucket = $this->appConfig['bucket'];
        $sourceKey = $this->path2Key($sourcePath);
        $key = $this->path2Key($path);
        $object += [
            'Bucket' => $bucket,
            'CopySource' => $bucket . '/' . $sourceKey,
            'Key' => $key,
        ];

        $result = $this->getS3()->copyObject($object);

        return $this->path2Url($path);
    }


    public function getObject($path): S3Object
    {
        $key = $this->path2Key($path);
        $object = array(
            'Bucket' => $this->appConfig['bucket'],
            'Key' => $key,
        );
        $result = $this->getS3()->getObject($object);

        return new S3Object($result);
    }


    public function headObject($path): S3Object
    {
        $key = $this->path2Key($path);
        $object = array(
            'Bucket' => $this->appConfig['bucket'],
            'Key' => $key,
        );
        $result = $this->getS3()->headObject($object);

        return new S3Object($result);
    }


    public function getS3(): S3Client
    {
        if (!$this->_s3) {
            $this->_s3 = $this->aws->getS3();
        }
        return $this->_s3;
    }


    public function getMimeType($fileName)
    {
        return Mimetypes::getInstance()->fromFilename($fileName);
    }


    public function listObjects($path)
    {
        $key = $this->path2key($path);

        $result = $this->getS3()->ListObjects(array(
            'Bucket' => $this->appConfig['bucket'],
            'Prefix' => rtrim($key, '/') . '/',
            'Delimiter' => '/',
        ));

        return new S3StorageListResult($result);
    }


    public function isObjectExist($path): bool
    {
        $key = $this->path2Key($path);
        return $this->getS3()->doesObjectExist($this->appConfig['bucket'], $key);
    }


    public function path2Url($path): string
    {
        return $this->appConfig['baseUrl'] . '/' . ltrim($path, '/');
    }


    public function url2Path($url)
    {
        if (!$this->isValidUrl($url)) {
            throw new InvalidArgumentException('Object URL is not based on known S3 storage.');
        }

        $pattern = preg_quote($this->appConfig['baseUrl'], '/');
        return preg_replace("/^$pattern/", '', $url);
    }


    public function isValidUrl($url)
    {
        $pattern = preg_quote($this->appConfig['baseUrl'], '/');
        return preg_match("/^$pattern/", $url);
    }


    public function key2Path($key)
    {
        $pattern = preg_quote($this->appConfig['basePath'], '/');
        return preg_replace("/^$pattern/", '', $key);
    }


    public function path2Key($path): string
    {
        return ($this->appConfig['basePath'] ? $this->appConfig['basePath'] . '/' : '') . ltrim($path, '/');
    }


    public function signUrl($url, $expiration, $overrides = []): UriInterface
    {
        $s3 = $this->getS3();
        $path = $this->url2Path($url);

        $commandParameters = [
            'Bucket' => $this->appConfig['bucket'],
            'Key' => $this->path2Key($path),
        ];

        $commandParameters += $overrides;

        $cmd = $s3->getCommand('GetObject', $commandParameters);
        return $this->getS3()->createPresignedRequest($cmd, $expiration)->getUri();
    }
}
