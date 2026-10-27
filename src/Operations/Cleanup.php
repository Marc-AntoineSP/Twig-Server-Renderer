<?php
namespace App\Operations;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class Cleanup
{
    public function __construct(private Connection $connection,#[Autowire('%kernel.project_dir%/var/catalogue')]private string $root){}
    public function run(bool $apply=false):array
    {
        if(!is_dir($this->root)&&!mkdir($this->root,0770,true)&&!is_dir($this->root))throw new \RuntimeException('Storage unavailable.');
        $lock=fopen($this->root.'/.storage.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Cleanup lock unavailable.');
        try{
            $now=time();$candidates=[];$bytes=0;$live=[];
            foreach($this->connection->fetchAllAssociative('SELECT id,run_token FROM preview_job WHERE expires_at > CURRENT_TIMESTAMP') as $row)$live[$row['id'].($row['run_token']?'-'.$row['run_token']:'').'.json']=true;
            $expiredIds=array_fill_keys($this->connection->fetchFirstColumn('SELECT id FROM preview_job WHERE expires_at <= CURRENT_TIMESTAMP'),true);
            foreach(glob($this->root.'/previews/*.json')?:[] as $file)if(isset($expiredIds[substr(basename($file),0,26)])||(!isset($live[basename($file)])&&filemtime($file)<$now-3600))$candidates[$file]=true;
            foreach(glob($this->root.'/exports/*/*.json')?:[] as $file){
                if(is_link($file)||filesize($file)>2048)continue;$meta=json_decode(file_get_contents($file),true);
                if(is_array($meta)&&is_int($meta['expiresAt']??null)&&$meta['expiresAt']<$now){$candidates[$file]=true;$bin=substr($file,0,-5).'.bin';if(is_file($bin))$candidates[$bin]=true;}
            }
            foreach(glob($this->root.'/exports/*/*.bin')?:[] as $file)if(!is_file(substr($file,0,-4).'.json')&&filemtime($file)<$now-3600)$candidates[$file]=true;
            $referenced=array_fill_keys($this->connection->fetchFirstColumn('SELECT archive_key FROM catalogue_release'),true);
            foreach(glob($this->root.'/releases/*.zip')?:[] as $file)if(!isset($referenced['releases/'.basename($file)])&&filemtime($file)<$now-86400)$candidates[$file]=true;
            foreach(['releases/.stage-*','exports/*/.stage-*'] as $pattern)foreach(glob($this->root.'/'.$pattern)?:[] as $file)if(filemtime($file)<$now-3600)$candidates[$file]=true;
            // Re-check age under the storage lock: reuse refreshes immutable blob mtime.
            $removed=0;foreach(array_keys($candidates) as $file){if(is_link($file)||!is_file($file))continue;$bytes+=filesize($file);if($apply){if(!unlink($file))throw new \RuntimeException('Cleanup could not remove a candidate.');$removed++;}}
            $expired=(int)$this->connection->fetchOne('SELECT COUNT(*) FROM preview_job WHERE expires_at <= CURRENT_TIMESTAMP');
            if($apply)$this->connection->executeStatement('DELETE FROM preview_job WHERE expires_at <= CURRENT_TIMESTAMP');
            return ['dryRun'=>!$apply,'candidateFiles'=>count($candidates),'removedFiles'=>$removed,'bytes'=>$bytes,'expiredJobs'=>$expired,'referencedArchives'=>count($referenced),'archiveGraceSeconds'=>86400,'orphanPreviewGraceSeconds'=>3600];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
