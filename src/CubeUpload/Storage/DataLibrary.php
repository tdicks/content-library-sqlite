<?php namespace CubeUpload\Storage;

use \PDO;
use CubeUpload\Storage\Exceptions\FileNotFoundException;

class DataLibrary
{
    private $storage_dir;
    private $pdo;

    public function __construct($storage_dir)
    {
        if(file_exists($storage_dir) && is_dir($storage_dir))
        {
            $this->storage_dir = $storage_dir;
            $this->connectDb();
        }
        else
            throw new \Exception("Invalid storage dir: $storage_dir");
    }

    private function connectDb()
    {
        $this->pdo = new PDO("sqlite:{$this->storage_dir}/index.db");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS lookup (id INTEGER PRIMARY KEY, filename TEXT UNIQUE, hash TEXT, created_at TEXT, retrieved_at TEXT)");
    }

    /**
     * Retrieves data from the library by name
     * @param string $filename The filename of the data to retrieve
    */
    public function get($filename)
    {
        $statement = $this->pdo->prepare("SELECT hash FROM lookup WHERE filename = :filename");
        $statement->bindParam(":filename", $filename);

        $success = $statement->execute();

        if($success)
        {
            $result = $statement->fetchAll();

            if(count($result) == 1)
            {
                $this->pdo->query("UPDATE lookup SET retrieved_at = datetime()")->execute();
                return $this->loadContent($result[0][0]);
            }
            else
                return false;

        }
        else
            return false;
    }

    public function put($filename, $filePath)
    {
        if (!file_exists($filePath))
            throw new FileNotFoundException("File {$filePath} doesn't exist");

        $hash = md5_file($filePath);

        $statement = $this->pdo->prepare("INSERT OR IGNORE INTO lookup (filename, hash, created_at) VALUES (:filename, :hash, datetime())");
        $statement->bindParam(":filename", $filename);
        $statement->bindParam(":hash", $hash);

        $statement->execute();

        $this->saveFile($hash, $filePath);
    }

    public function delete($filename)
    {
        $statement = $this->pdo->prepare("SELECT hash FROM lookup WHERE filename = :filename");
        $statement->bindParam(":filename", $filename);

        $success = $statement->execute();

        if($success)
        {
            $result = $statement->fetchAll();

            if(count($result) >= 1)
            {
                $hash = $result[0]["hash"];

                $delete_stmt = $this->pdo->prepare("DELETE FROM lookup WHERE filename = :filename");
                $delete_stmt->bindParam(":filename", $filename);

                $delete_stmt->execute();

                $statement = $this->pdo->prepare("SELECT hash FROM lookup WHERE hash = :hash");
                $statement->bindParam(":hash", $hash);
                $statement->execute();

                $result = $statement->fetchAll();

                if(count($result) == 0)
                    $this->deleteContent($hash);
            }
        }
        else
            return false;
    }

    public function references( $hash )
    {
        $statement = $this->pdo->prepare("SELECT filename FROM lookup WHERE hash = :hash");
        $statement->bindParam(":hash", $hash);
        $statement->execute();

        $result = $statement->fetchAll();

        if (count($result) == 0)
            return null;
        else
            return $result;
    }

    public function info( $filename )
    {
        $statement = $this->pdo->prepare("SELECT hash, created_at, retrieved_at FROM lookup WHERE filename = :filename");
        $statement->bindParam(":filename", $filename);
        $statement->execute();

        $result = $statement->fetchAll();

        if( count($result) == 0)
            return null;
        else
            return $result[0];
    }

    private function getPath($hash)
    {
        return $this->storage_dir . '/' . $hash[0] .'/' . $hash[1] . '/' . $hash[2];
    }

    private function getFilePath($hash)
    {
        return $this->getPath($hash) . '/' . $hash . '.dat';
    }

    private function loadContent($hash)
    {
        $path = $this->getFilePath($hash);

        if(file_exists($path)) 
            return file_get_contents($path);
    }

    private function saveContent($hash, $content)
    {
        $path = $this->getFilePath($hash);
        $hashPath = $this->getPath($hash);

        if(!file_exists($hashPath))
            mkdir($hashPath, 0777, true);

        file_put_contents($path, $content);
    }

    private function saveFile($hash, $filePath)
    {
        $path = $this->getFilePath($hash);
        $hashPath = $this->getPath($hash);

        if(!file_exists($hashPath))
            mkdir($hashPath, 0777, true);

        copy($filePath, $path);
    }

    private function deleteContent($hash)
    {
        $path = $this->getFilePath($hash);

        if(file_exists($path)) 
            unlink($path);
    }
}