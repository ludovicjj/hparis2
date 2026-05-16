<?php

namespace App\Service;

use Aws\S3\S3Client;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class S3Service
{
    public const string EXPIRATION = '+6 days';

    private S3Client $client;

    public function __construct(
        #[Autowire('%env(AWS_S3_ENDPOINT)%')]
        private readonly string $endpoint,
        #[Autowire('%env(AWS_S3_REGION)%')]
        private readonly string $region,
        #[Autowire('%env(AWS_S3_ACCESS_KEY)%')]
        private readonly string $accessKey,
        #[Autowire('%env(AWS_S3_SECRET_KEY)%')]
        private readonly string $secretKey,
        #[Autowire('%env(AWS_S3_BUCKET)%')]
        private readonly string $bucket,
        #[Autowire('%env(AWS_S3_PUBLIC_URL)%')]
        private readonly string $publicUrl,
    ) {
        $this->client = new S3Client([
            'version' => 'latest',
            'endpoint' => $this->endpoint,
            'region' => $this->region,
            'credentials' => [
                'key' => $this->accessKey,
                'secret' => $this->secretKey,
            ],
        ]);
    }

    public function uploadPublicFile(string $key, string $content, string $contentType): bool
    {
        return $this->doPutObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $content,
            'ContentType' => $contentType,
            'ACL' => 'public-read',
        ]);
    }

    public function uploadPrivateFile(string $key, string $content, string $contentType): bool
    {
        return $this->doPutObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $content,
            'ContentType' => $contentType,
        ]);
    }

    public function uploadPublicFileFromPath(string $key, string $filePath, string $contentType): bool
    {
        return $this->doPutObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SourceFile' => $filePath,
            'ContentType' => $contentType,
            'ACL' => 'public-read',
        ]);
    }

    public function uploadPrivateFileFromPath(string $key, string $filePath, string $contentType): bool
    {
        return $this->doPutObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SourceFile' => $filePath,
            'ContentType' => $contentType,
        ]);
    }

    private function doPutObject(array $args): bool
    {
        try {
            $result = $this->client->putObject($args);

            return isset($result['@metadata']) && $result['@metadata']['statusCode'] === 200;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Delete an object. Returns false if the object does not exist.
     */
    public function deleteFile(string $key): bool
    {
        if (!$this->fileExists($key)) {
            return false;
        }

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Batch-delete a list of keys via the DeleteObjects API. Chunks the list
     * in groups of 1000 (the S3 hard limit per call). Returns the number of
     * objects S3 reported as successfully deleted.
     *
     * @param string[] $keys
     */
    public function deleteFiles(array $keys): int
    {
        if (empty($keys)) {
            return 0;
        }

        $deleted = 0;
        foreach (array_chunk($keys, 1000) as $chunk) {
            $objects = array_map(fn(string $key) => ['Key' => $key], $chunk);
            $deleted += $this->doDeleteObjects($objects);
        }

        return $deleted;
    }

    /**
     * List every object under a prefix and batch-delete them. Pages through
     * ListObjectsV2 (1000 keys per page) until exhausted. Useful to purge
     * everything that belongs to a single owner (e.g. a gallery).
     */
    public function deleteFilesByPrefix(string $prefix): int
    {
        $deleted = 0;
        $continuationToken = null;

        do {
            $listArgs = [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'MaxKeys' => 1000,
            ];
            if ($continuationToken !== null) {
                $listArgs['ContinuationToken'] = $continuationToken;
            }

            try {
                $listResult = $this->client->listObjectsV2($listArgs);
            } catch (Exception) {
                return $deleted;
            }

            $contents = $listResult['Contents'] ?? [];
            if (!empty($contents)) {
                $objects = array_map(fn(array $obj) => ['Key' => $obj['Key']], $contents);
                $deleted += $this->doDeleteObjects($objects);
            }

            $continuationToken = ($listResult['IsTruncated'] ?? false)
                ? ($listResult['NextContinuationToken'] ?? null)
                : null;
        } while ($continuationToken !== null);

        return $deleted;
    }

    /**
     * @param array<array{Key: string}> $objects
     */
    private function doDeleteObjects(array $objects): int
    {
        if (empty($objects)) {
            return 0;
        }

        try {
            $result = $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => [
                    'Objects' => $objects,
                    'Quiet' => true,
                ],
            ]);

            return count($objects) - count($result['Errors'] ?? []);
        } catch (Exception) {
            return 0;
        }
    }

    /**
     * Download an object's content as a binary string.
     */
    public function getFileContent(string $key): string|false
    {
        if (!$this->fileExists($key)) {
            return false;
        }

        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            if (!isset($result['Body'])) {
                return false;
            }

            return $result['Body']->getContents();
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Build the direct public URL for an object (valid for objects uploaded with ACL public-read).
     */
    public function getPublicUrl(string $key): string
    {
        return rtrim($this->publicUrl, '/') . '/' . ltrim($key, '/');
    }

    /**
     * Generate a time-limited presigned PUT URL so the browser can upload a binary
     * directly to S3 without going through PHP. The signed URL pins the Content-Type
     * so the client is forced to PUT exactly what was negotiated.
     */
    public function createPresignedPutUrl(string $key, string $contentType, string $expiration = '+15 minutes'): string
    {
        $cmd = $this->client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $contentType,
        ]);

        $request = $this->client->createPresignedRequest($cmd, $expiration);

        return (string) $request->getUri();
    }

    /**
     * Generate a time-limited presigned URL for an object. Kept for future use cases
     * where we might want private downloads.
     */
    public function getPresignedUrl(string $key, string $expiration = self::EXPIRATION): string|false
    {
        if (!$this->fileExists($key)) {
            return false;
        }

        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        $request = $this->client->createPresignedRequest($cmd, $expiration);

        return (string) $request->getUri();
    }

    /**
     * Check if an object exists in the bucket (HEAD request, no body transfer).
     */
    public function fileExists(string $key): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Return the size of an object in bytes, or false if the object does not exist
     * or the metadata cannot be retrieved.
     */
    public function getFileSize(string $key): int|false
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return (int) ($result['ContentLength'] ?? 0);
        } catch (Exception) {
            return false;
        }
    }
}
