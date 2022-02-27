<?php

use ArgentCrusade\Flysystem\Selectel\SelectelAdapter;
use ArgentCrusade\Selectel\CloudStorage\Api\ApiClient;
use ArgentCrusade\Selectel\CloudStorage\Collections\Collection;
use ArgentCrusade\Selectel\CloudStorage\File;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use PHPUnit\Framework\TestCase;

class SelectelAdapterTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
    }

    public function selectelProvider()
    {
        $collection = new Collection([
            [
                'name' => 'path/to/file',
                'content_type' => 'text/plain',
                'bytes' => 1024,
                'last_modified' => '2000-01-01 00:00:00',
            ],
        ]);

        $files = Mockery::mock('ArgentCrusade\Selectel\CloudStorage\FluentFilesLoader');
        $files->shouldReceive('withPrefix')->andReturn($files);
        $files->shouldReceive('get')->andReturn($collection);

        $mock = Mockery::mock('ArgentCrusade\Selectel\CloudStorage\Container');
        $mock->shouldReceive('type')->andReturn('public');
        $mock->shouldReceive('files')->andReturn($files);

        return [
            [new SelectelAdapter($mock), $mock, $files, $collection],
        ];
    }

    public function metaDataProvider()
    {
        $file = Mockery::mock('ArgentCrusade\Selectel\CloudStorage\File');
        $files = Mockery::mock('ArgentCrusade\Selectel\CloudStorage\FluentFilesLoader');
        $files->shouldReceive('find')->andReturn($file);

        $mock = Mockery::mock('ArgentCrusade\Selectel\CloudStorage\Container');
        $mock->shouldReceive('type')->andReturn('public');
        $mock->shouldReceive('files')->andReturn($files);

        return [[new SelectelAdapter($mock), $file]];
    }

    /**
     * @dataProvider metaDataProvider
     */
    public function testMimeType($adapter, $file)
    {
        $file->expects('contentType')->andReturn('application/file');

        $result = $adapter->mimeType('path');

        $this->assertInstanceOf(FileAttributes::class, $result);
    }

    /**
     * @dataProvider metaDataProvider
     */
    public function testLastModified($adapter, $file)
    {
        $file->expects('lastModifiedAt')->andReturn('2020-01-20 00:00:00');

        $result = $adapter->lastModified('path');

        $this->assertInstanceOf(FileAttributes::class, $result);
    }

    /**
     * @dataProvider metaDataProvider
     */
    public function testFileSize($adapter, $file)
    {
        $file->expects('contentType')->andReturn('application/file');

        $result = $adapter->mimeType('path');

        $this->assertInstanceOf(FileAttributes::class, $result);
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testFileExists($adapter, $mock, $files)
    {
        $files->expects('exists')->andReturn(true);

        $this->assertTrue($adapter->fileExists('something'));
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testDirectoryExists($adapter, $mock, $files)
    {
        $files->expects('exists')->andReturn(true);

        $this->assertTrue($adapter->directoryExists('something'));
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testUrl($adapter, $mock, $files)
    {
        $mock->expects('url')->with('/file.txt')->andReturn('https://static.example.org/file.txt');
        $mock->expects('url')->with('file.txt')->andReturn('https://static.example.org/file.txt');

        $this->assertEquals('https://static.example.org/file.txt', $adapter->getUrl('/file.txt'));
        $this->assertEquals('https://static.example.org/file.txt', $adapter->getUrl('file.txt'));
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testWrite($adapter, $mock)
    {
        $mock->expects('uploadFromString')->andReturn(md5('test'));

        $result = $adapter->write('something', 'contents', new Config());
        $this->assertNull($result);
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testWriteStream($adapter, $mock)
    {
        $mock->expects('uploadFromStream')->andReturn(md5('test'));

        $file = tmpfile();
        $result = $adapter->writeStream('something', $file, new Config());
        $this->assertNull($result);
        fclose($file);
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testRead($adapter, $mock, $files)
    {
        $file = Mockery::mock('ArgentCrusade\Selectel\CloudStorage\File');
        $file->expects('read')->andReturn('something');
        $files->expects('find')->andReturn($file);

        $result = $adapter->read('somewhere');
        $this->assertIsString($result);
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testReadStream($adapter, $mock, $files)
    {
        $stream = tmpfile();
        fwrite($stream, 'something', 1024);

        $file = Mockery::mock('ArgentCrusade\Selectel\CloudStorage\File');
        $file->expects('readStream')->andReturn($stream);
        $files->expects('find')->andReturn($file);

        $result = $adapter->readStream('somewhere');
        $this->assertIsResource($result);
        $this->assertEquals('something', fread($result, 1024));

        fclose($stream);
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testCopy($adapter, $mock, $files)
    {
        $file = Mockery::mock('ArgentCrusade\Selectel\CloudStorage\File');
        $file->expects('copy')->andReturn('newpath');
        $files->expects('find')->andReturn($file);

        $this->assertNull($adapter->copy('from', 'to', new Config([])));
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testCreateDir($adapter, $mock)
    {
        $mock->expects('createDir')->andReturn(md5('test'));
        $result = $adapter->createDirectory('somewhere', new Config());

        $this->assertNull($result);
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testDelete($adapter, $mock, $files)
    {
        $file = Mockery::mock('ArgentCrusade\Selectel\CloudStorage\File');
        $file->expects('delete')->andReturn(true);
        $files->expects('find')->andReturn($file);

        $mock->expects('deleteDir')->andReturn(true);

        $this->assertNull($adapter->delete('something'));
        $this->assertNull($adapter->deleteDirectory('something'));
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testSetVisibility($adapter, $mock, $files)
    {
        $this->expectException(UnableToSetVisibility::class);

        $adapter->setVisibility('somewhere', 'somekind');
    }

    /**
     * @dataProvider selectelProvider
     */
    public function testVisibility($adapter, $mock, $files)
    {
        $this->expectException(UnableToRetrieveMetadata::class);

        $adapter->visibility('somewhere', 'somekind');
    }
}
