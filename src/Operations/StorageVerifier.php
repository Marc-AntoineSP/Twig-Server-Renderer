<?php
namespace App\Operations;
use Doctrine\DBAL\Connection;
use Styleguide\Contracts\PackageArchive;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class StorageVerifier
{
    public function __construct(private Connection $connection,private PackageArchive $archives,#[Autowire('%kernel.project_dir%/var/catalogue')]private string $root){}
    public function verify():array
    {
        $errors=[];$count=0;
        foreach($this->connection->iterateAssociative('SELECT archive_key,archive_hash FROM catalogue_release') as $row){$count++;$key=$row['archive_key'];
            if(!preg_match('~^releases/[a-f0-9]{64}\.zip$~D',$key)){$errors[]='Invalid immutable archive key.';continue;}$path=$this->root.'/'.$key;
            if(!is_file($path)||is_link($path)||!hash_equals($row['archive_hash'],hash_file('sha256',$path))){$errors[]='Missing or corrupt '.$key;continue;}
            try{$this->archives->read($path);}catch(\Throwable){$errors[]='Invalid package '.$key;}
        }
        return ['healthy'=>!$errors,'checkedReleases'=>$count,'errors'=>$errors];
    }
}
