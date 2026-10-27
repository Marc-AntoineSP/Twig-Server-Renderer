<?php
namespace Styleguide\Installer;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\StrictJson;
final readonly class Journal
{
    public function __construct(private Project $project){}
    public function clean(string $directory):void
    {
        if(!str_starts_with($directory,$this->project->root.'/.styleguide/staging/'))throw new \LogicException('Invalid staging root.');
        if(!is_dir($directory))return;
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($directory);
    }
    public function pending():array{return glob($this->project->root.'/.styleguide/staging/*/journal.json')?:[];}
    public function recover():array
    {
        $recovered=[];
        foreach($this->pending() as $file){$j=StrictJson::decode(file_get_contents($file),2097152,16);$directory=dirname($file);
            if(($j->schemaVersion??null)!==1||!is_array($j->entries??null)||!in_array($j->state??null,['applying','committed'],true))throw new CatalogueException(422,'Invalid recovery journal; retained for manual review.');
            if('committed'===$j->state){$this->clean($directory);continue;}
            foreach(array_reverse($j->entries) as $entry){$path=$this->project->path($entry->path);$actual=is_file($path)?hash_file('sha256',$path):null;
                if($actual===$entry->beforeSha)continue;
                if($actual!==$entry->afterSha)throw new CatalogueException(409,'Recovery refuses a file modified outside the interrupted transaction: '.$entry->path);
                if(null===$entry->beforeSha){if(is_file($path)&&!unlink($path))throw new \RuntimeException('Cannot remove owned file.');}else{$backup=$directory.'/before/'.$entry->index;if(!is_file($backup)||!hash_equals($entry->beforeSha,hash_file('sha256',$backup)))throw new CatalogueException(422,'Recovery backup integrity failure.');$this->project->atomic($path,file_get_contents($backup));}
            }
            foreach(array_reverse($j->directories??[]) as $relative){$path=$this->project->path($relative);if(is_dir($path)&&count(scandir($path))===2)rmdir($path);}
            $recovered[]=basename($directory);$this->clean($directory);
        }
        return $recovered;
    }
    public function apply(string $directory,array $changes,array $expected):void
    {
        $entries=[];$directories=[];$index=0;
        foreach($changes as $relative=>$bytes){$path=$this->project->path($relative);$before=is_file($path)?file_get_contents($path):null;
            if((null===$before?null:hash('sha256',$before))!==($expected[$relative]??null))throw new CatalogueException(409,'Destination changed before commit: '.$relative);
            if($before===$bytes)continue;
            if(null!==$before){$backup=$directory.'/before/'.$index;$this->project->atomic($backup,$before);}
            $parts=explode('/',$relative);array_pop($parts);$prefix='';foreach($parts as $part){$prefix.=(''===$prefix?'':'/').$part;if(!is_dir($this->project->root.'/'.$prefix))$directories[$prefix]=$prefix;}
            $entries[]=['path'=>$relative,'index'=>$index++,'beforeSha'=>null===$before?null:hash('sha256',$before),'afterSha'=>null===$bytes?null:hash('sha256',$bytes)];
        }
        $journal=['schemaVersion'=>1,'state'=>'applying','entries'=>$entries,'directories'=>array_values($directories),'applied'=>0];$file=$directory.'/journal.json';$this->project->atomic($file,$this->project->encoded($journal));
        try{
            foreach($entries as $entry){$path=$this->project->path($entry['path']);$actual=is_file($path)?hash_file('sha256',$path):null;if($actual!==$entry['beforeSha']){$journal['entries']=array_slice($entries,0,$journal['applied']);$this->project->atomic($file,$this->project->encoded($journal));throw new CatalogueException(409,'Destination changed while installing: '.$entry['path']);}
                $bytes=$changes[$entry['path']];if(null===$bytes){if(is_file($path)&&!unlink($path))throw new \RuntimeException('Cannot remove owned file.');}else{$this->project->atomic($path,$bytes);}
                $journal['applied']++;$this->project->atomic($file,$this->project->encoded($journal));
                // Fault injection is restricted to test-mode CLI processes; imports cannot set it.
                if('test'===getenv('APP_ENV') && (string)$journal['applied']===getenv('STYLEGUIDE_TEST_PAUSE_AFTER'))while(true)usleep(100000);
            }
            $journal['state']='committed';$this->project->atomic($file,$this->project->encoded($journal));$this->clean($directory);
        }catch(\Throwable $e){$this->recover();throw $e;}
    }
}
