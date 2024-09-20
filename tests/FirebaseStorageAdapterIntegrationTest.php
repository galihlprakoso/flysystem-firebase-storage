<?php

namespace galihlprakoso\Adapters;

use PHPUnit\Framework\TestCase;
use Kreait\Firebase\Factory;
use League\Flysystem\Config;

class FirebaseStorageAdapterTest extends TestCase
{
    protected FirebaseStorageAdapter $adapter;

    protected function setUp(): void
    {
        $factory = (new Factory())->withServiceAccount(__DIR__ . '/<service-account-name>.json');
        $storageClient = $factory->createStorage();

        $this->adapter = new FirebaseStorageAdapter($storageClient, '<bucket name>');
    }

    public function testUploadFileToFirebaseStorage(): void
    {
        $path = 'test/test-file.txt';
        $contents = 'Hello, Firebase!';

        $this->adapter->write($path, $contents, new Config());

        $this->assertTrue($this->adapter->fileExists($path));

        $this->adapter->delete($path);
    }

    public function testReadFileFromFirebaseStorage(): void
    {
        $path = 'test/test-file.txt';
        $contents = 'Hello, Firebase!';

        $this->adapter->write($path, $contents, new Config());

        $retrievedContents = $this->adapter->read($path);

        $this->assertEquals($contents, $retrievedContents);

        $this->adapter->delete($path);
    }
}
