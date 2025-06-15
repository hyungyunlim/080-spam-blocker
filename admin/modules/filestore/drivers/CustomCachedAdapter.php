<?php

namespace FreePBX\modules\Filestore\drivers;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\StorageAttributes;
use Symfony\Component\Cache\Adapter\FilesystemAdapter as SymfonyCacheAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class CustomCachedAdapter implements FilesystemAdapter
{
    private FilesystemAdapter $adapter;
    private SymfonyCacheAdapter $cache;

    public function __construct(FilesystemAdapter $adapter, SymfonyCacheAdapter $cache)
    {
        $this->adapter = $adapter;
        $this->cache = $cache;
    }

    public function fileExists(string $path): bool
    {
        return $this->cache->get($path . ':exists', function (ItemInterface $item) use ($path) {
            $item->expiresAfter(3600); // Cache for 1 hour
            return $this->adapter->fileExists($path);
        });
    }

    public function directoryExists(string $path): bool
    {
        return $this->cache->get($path . ':dir_exists', function (ItemInterface $item) use ($path) {
            $item->expiresAfter(3600); // Cache for 1 hour
            return $this->adapter->directoryExists($path);
        });
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->adapter->write($path, $contents, $config);
        $this->cache->delete($path . ':exists');
        $this->cache->delete($path . ':metadata');
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->adapter->writeStream($path, $contents, $config);
        $this->cache->delete($path . ':exists');
        $this->cache->delete($path . ':metadata');
    }

    public function read(string $path): string
    {
        return $this->cache->get($path . ':contents', function (ItemInterface $item) use ($path) {
            $item->expiresAfter(3600); // Cache for 1 hour
            return $this->adapter->read($path);
        });
    }

    public function readStream(string $path)
    {
        return $this->adapter->readStream($path); // Not caching streams for simplicity
    }

    public function delete(string $path): void
    {
        $this->adapter->delete($path);
        $this->cache->delete($path . ':exists');
        $this->cache->delete($path . ':contents');
        $this->cache->delete($path . ':metadata');
    }

    public function deleteDirectory(string $path): void
    {
        $this->adapter->deleteDirectory($path);
        $this->cache->delete($path . ':exists');
        $this->cache->delete($path . ':contents');
        $this->cache->delete($path . ':metadata');
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->adapter->createDirectory($path, $config);
        $this->cache->delete($path . ':exists');
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->adapter->setVisibility($path, $visibility);
        $this->cache->delete($path . ':metadata');
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->cache->get($path . ':metadata', function (ItemInterface $item) use ($path) {
            $item->expiresAfter(3600); // Cache for 1 hour
            return $this->adapter->visibility($path);
        });
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->cache->get($path . ':metadata', function (ItemInterface $item) use ($path) {
            $item->expiresAfter(3600); // Cache for 1 hour
            return $this->adapter->mimeType($path);
        });
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->cache->get($path . ':metadata', function (ItemInterface $item) use ($path) {
            $item->expiresAfter(3600); // Cache for 1 hour
            return $this->adapter->lastModified($path);
        });
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->cache->get($path . ':metadata', function (ItemInterface $item) use ($path) {
            $item->expiresAfter(3600); // Cache for 1 hour
            return $this->adapter->fileSize($path);
        });
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->adapter->listContents($path, $deep); // Not caching directory listings for simplicity
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->adapter->move($source, $destination, $config);
        $this->cache->delete($source . ':exists');
        $this->cache->delete($source . ':contents');
        $this->cache->delete($source . ':metadata');
        $this->cache->delete($destination . ':exists');
        $this->cache->delete($destination . ':contents');
        $this->cache->delete($destination . ':metadata');
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->adapter->copy($source, $destination, $config);
        $this->cache->delete($destination . ':exists');
        $this->cache->delete($destination . ':contents');
        $this->cache->delete($destination . ':metadata');
    }
}
