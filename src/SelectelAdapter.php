<?php

namespace ArgentCrusade\Flysystem\Selectel;

use Exception;
use League\Flysystem\Config;
use ArgentCrusade\Selectel\CloudStorage\Contracts\ContainerContract;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\FileNotFoundException;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use Psr\Http\Message\StreamInterface;

class SelectelAdapter implements FilesystemAdapter
{

    /**
     * Storage container.
     *
     * @var \ArgentCrusade\Selectel\CloudStorage\Contracts\ContainerContract
     */
    protected $container;

    /**
     * Create new instance.
     *
     * @param \ArgentCrusade\Selectel\CloudStorage\Contracts\ContainerContract $container
     */
    public function __construct(ContainerContract $container)
    {
        $this->container = $container;
    }

    /**
     * Loads file from container.
     *
     * @param string $path Path to file.
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FileContract
     */
    protected function getFile($path)
    {
        return $this->container->files()->find($path);
    }

    /**
     * Transforms internal files array to Flysystem-compatible one.
     *
     * @param array $files Original Selectel's files array.
     *
     * @return array
     */
    protected function transformFiles($files)
    {
        $result = [];

        foreach ($files as $file) {
            $result[] = [
                'type' => $file['content_type'] === 'application/directory' ? 'dir' : 'file',
                'path' => $file['name'],
                'size' => intval($file['bytes']),
                'timestamp' => strtotime($file['last_modified']),
                'mimetype' => $file['content_type'],
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function read($path): string
    {
        try {
            return $this->getFile($path)->read();
        } catch (FileNotFoundException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        try {
            $stream = $this->getFile($path)->readStream();
            if ($stream instanceof StreamInterface) {
                $stream->rewind();
            } else {
                rewind($stream);
            }
            return $stream;
        } catch (FileNotFoundException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false): iterable
    {
        $files = $this->container->files()->withPrefix($directory)->get();
        return $this->transformFiles($files);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $files = $this->listContents($path);

        return isset($files[0]) ? $files[0] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config): void
    {
        try {
            $this->writeToContainer('String', $path, $contents);
        } catch (Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config): void
    {
        try {
            $this->writeToContainer('Stream', $path, $resource);
        } catch (Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Writes string or stream to container.
     *
     * @param string          $type    Upload type
     * @param string          $path    File path
     * @param string|resource $payload String content or Stream resource
     *
     * @return array|bool
     */
    protected function writeToContainer($type, $path, $payload)
    {
        try {
            $this->container->{'uploadFrom'.$type}($path, $payload);
        } catch (UploadFailedException $e) {

        }

        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->getFile($source)->copy($destination);
        } catch (ApiRequestFailedException $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path): void
    {
        try {
            $fileContract = $this->getFile($path);
            $fileContract->delete();
        } catch (ApiRequestFailedException $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDirectory($path): void
    {
        try {
            $this->container->deleteDir($path);
        } catch (ApiRequestFailedException $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->container->createDir($path);
        } catch (ApiRequestFailedException $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage());
        }
    }

    /**
     * Get full URL to given path.
     *
     * @param string $path = ''
     *
     * @return string
     */
    public function getUrl($path = '')
    {
        return $this->container->url($path);
    }

    /**
     * {@inheritdoc}
     */
    public function fileExists(string $path): bool
    {
        try {
            return $this->container->files()->exists($path);
        } catch (Exception $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function directoryExists(string $path): bool
    {
        try {
            return $this->container->files()->exists($path);
        } catch (Exception $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, $visibility, new Exception('Unsupported.'));
    }

    /**
     * {@inheritdoc}
     */
    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, 'Unsupported.');
    }

    /**
     * {@inheritdoc}
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            return new FileAttributes($path, null, null, null, $this->getFile($path)->contentType());
        } catch (Exception $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            return new FileAttributes($path, null, null, strtotime($this->getFile($path)->lastModifiedAt()) ?: 0);
        } catch (Exception $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            return new FileAttributes($path, $this->getFile($path)->size());
        } catch (Exception $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (FilesystemException $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }
}
