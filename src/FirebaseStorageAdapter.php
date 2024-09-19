<?php

namespace App\Adapters;

use Google\Cloud\Storage\Bucket;
use Kreait\Firebase\Storage;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;

class FirebaseStorageAdapter implements FilesystemAdapter, ChecksumProvider
{
    protected Bucket $storageClient;
    protected PathPrefixer $prefixer;
    protected MimeTypeDetector $mimeTypeDetector;

    public function __construct(
        Storage $storageClient,
        string $bucket,
        string $prefix = '',
        MimeTypeDetector $mimeTypeDetector = null
    ) {
        $this->storageClient = $storageClient->getBucket($bucket);
        $this->prefixer = new PathPrefixer($prefix);
        $this->mimeTypeDetector = $mimeTypeDetector ?: new FinfoMimeTypeDetector();
    }

    public function fileExists(string $path): bool
    {
        $location = $this->applyPathPrefix($path);

        try {
            return $this->storageClient->object($location)->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        $location = $this->applyPathPrefix($path);

        return $this->fileExists($location);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->storageClient->upload($location, [
                'data' => $contents,
                'predefinedAcl' => 'publicRead',
            ]);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($location, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->storageClient->upload($location, [
                'data' => stream_get_contents($contents),
                'predefinedAcl' => 'publicRead',
            ]);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($location, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        $object = $this->readStream($path);
        return $object->getContents();
    }

    public function readStream(string $path)
    {
        $location = $this->applyPathPrefix($path);

        try {
            return $this->storageClient->object($location)->downloadAsStream();
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($location, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->storageClient->object($location)->delete();
        } catch (\Exception $e) {
            throw UnableToDeleteFile::atLocation($location, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $location = $this->applyPathPrefix($path);
        try {
            foreach ($this->listContents($location, true) as $item) {
                $this->delete($item->path());
            }
        } catch (\Exception $e) {
            throw UnableToDeleteDirectory::atLocation($location, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        throw UnableToCreateDirectory::atLocation($path, "Firebase storage doesn't support directory creation.");
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Firebase Storage does not support visibility settings.');
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        $location = $this->applyPathPrefix($path);

        return new FileAttributes(
            $path,
            null,
            null,
            null,
            $this->mimeTypeDetector->detectMimeTypeFromPath($location)
        );
    }

    public function lastModified(string $path): FileAttributes
    {
        $location = $this->applyPathPrefix($path);

        try {
            $object = $this->storageClient->object($location);
            $timestamp = $object->info()['updated'];
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::lastModified($location, $e->getMessage());
        }

        return new FileAttributes($path, null, null, strtotime($timestamp));
    }

    public function checksum(string $path, Config $config): string
    {
        $location = $this->applyPathPrefix($path);

        try {
            $object = $this->storageClient->object($location);
            return $object->info()['md5Hash'];
        } catch (\Exception $e) {
            throw new UnableToProvideChecksum($e->getMessage(), $e->getFile());
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        $location = $this->applyPathPrefix($path);

        try {
            $object = $this->storageClient->object($location);
            return new FileAttributes($path, $object->info()['size']);
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::fileSize($location, $e->getMessage());
        }
    }

    public function listContents(string $path = '', bool $deep = false): iterable
    {
        $location = $this->applyPathPrefix($path);

        foreach ($this->storageClient->objects(['prefix' => $location]) as $object) {
            $attrs = $this->normalizeResponse($object);
            if ($attrs->isDir() && $attrs->path() === $path) {
                continue;
            }

            yield $attrs;
        }
    }

    protected function normalizeResponse($object): StorageAttributes
    {
        $path = $this->prefixer->stripPrefix($object->name());
        $isDir = substr($path, -1) === '/';

        return $isDir
            ? new DirectoryAttributes($path)
            : new FileAttributes($path, $object->size());
    }

    protected function applyPathPrefix(string $path): string
    {
        return $this->prefixer->prefixPath($path);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (\Exception $e) {
            throw UnableToMoveFile::because($e->getMessage(), $source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $sourceLocation = $this->applyPathPrefix($source);
        $destinationLocation = $this->applyPathPrefix($destination);

        try {
            $this->storageClient->object($sourceLocation)->copy($destinationLocation);
        } catch (\Exception $e) {
            throw UnableToCopyFile::fromLocationTo($sourceLocation, $destinationLocation, $e);
        }
    }
}
