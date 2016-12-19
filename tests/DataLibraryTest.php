<?php

use CubeUpload\Storage\DataLibrary;
use PHPUnit\Framework\TestCase;

class DataLibraryTest extends TestCase
{
    private static $library;

    public static function setUpBeforeClass()
    {
        if (file_exists( 'content' ))
            recursiveDelete('content'); 
            
        mkdir( 'content' );
        
        self::$library = new DataLibrary('content');
    }

    public static function tearDownAfterClass()
    {
        self::$library = null;

        if (file_exists( 'content' ))
            self::recursiveDelete( 'content' );
    }

    // http://andy-carter.com/blog/recursively-remove-a-directory-in-php
    public static function recursiveDelete( $path )
    {
        $files = glob($path . '/*');
        foreach ($files as $file) {
            is_dir($file) ? self::recursiveDelete($file) : unlink($file);
        }
        rmdir($path);
        return;
    }

    public function testClassLoading()
    {
        self::$library = new DataLibrary('content');

        $this->assertTrue( file_exists('./content/index.db') );
    }

    public function testFileSaving()
    {
        self::$library->put("testfile.txt", "./tests/fixtures/testfile.txt");
        $this->assertFileExists("./content/5/0/a/50a67ca95104ed586a1ba3e61f262f54.dat");
    }

    public function testFileLoading()
    {
        $data = self::$library->get('testfile.txt');

        $this->assertEquals('THIS IS A TEST FILE', $data);
    }

    public function testFileReferences()
    {
        $refs = self::$library->references('50a67ca95104ed586a1ba3e61f262f54');

        $this->assertCount(1, $refs);
    }

    public function testFileInfo()
    {
        $info = self::$library->info('testfile.txt');

        $this->assertCount(4, $info);
    }

    public function testFileRetrievedUpdated()
    {
        sleep(1); // Wait for a second before retrieving the content. This should update the retrieved_at column.

        $content = self::$library->get('testfile.txt');

        $info = self::$library->info('testfile.txt');

        $created_at = date_parse($info['created_at']);
        $retrieved_at = date_parse($info['retrieved_at']);

        $this->assertGreaterThan( $info['created_at'], $info['retrieved_at']);
    }

    public function testFileDeletion()
    {
        self::$library->delete('testfile.txt');

        $this->assertFileNotExists("./content/5/0/a/50a67ca95104ed586a1ba3e61f262f54.dat");
    }

    public function testFileDuplication()
    {
        self::$library->put('testfile.txt', './tests/fixtures/testfile.txt');
        self::$library->put('otherfile.txt', './tests/fixtures/testfile.txt');

        $hash = self::$library->info('otherfile.txt')['hash'];
        $refs = self::$library->references($hash);

        $this->assertEquals(2, count($refs));
    }

    public function testGetInvalidFile()
    {
        $content = self::$library->get('invalid_file.txt');
        $this->assertEquals(false, $content);
    }

    /**
     * @expectedException CubeUpload\Storage\Exceptions\FileNotFoundException
     */
    public function testPutInvalidFile()
    {
        self::$library->put('filename.txt', 'path/to/invalid_file.txt');
    }
}
