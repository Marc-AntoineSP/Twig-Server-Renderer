<?php
namespace App\Packaging;
use App\Entity\CatalogueItem;
use App\Entity\Draft;
use App\Entity\Release;
use App\Entity\Theme;
use App\Entity\ThemeRelease;
use App\Entity\User;
use App\Rendering\PreviewRenderer;
use App\Rendering\ReleaseSourceReader;
use App\Repository\CatalogueRepository;
use App\Theme\ThemeWriter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageGraph;
use Styleguide\Contracts\PackageSetReader;
use Styleguide\Contracts\StrictJson;
final readonly class TransferImporter
{
    public function __construct(private PackageSetReader $reader,private PackageGraph $graphs,private CatalogueRepository $catalogue,private EntityManagerInterface $em,private ReleaseSourceReader $releases,private PreviewRenderer $renderer,private ThemeWriter $themes,private ArchiveStore $store,private CanonicalJson $json,private Connection $connection){}
    public function prepare(string $path,?string $format=null,bool $adjacent=false):array
    {
        $set=$this->reader->read($path,$format,$adjacent);$versions=[];$themeFiles=[];$renders=0;$deadline=microtime(true)+45;
        $lookup=function(string $name,string $version) use($set):array {
            if(isset($set['packages'][$name]) && $set['packages'][$name]['manifest']->version===$version) return [$set['packages'][$name]['manifest'],$set['packages'][$name]['sources']];
            $item=$this->catalogue->named($name);$release=$item?$this->em->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>$version]):null;
            if(!$release)throw new CatalogueException(422,'Missing exact dependency '.$name.'@'.$version.'.');
            return [$release->getManifestData(),$this->releases->read($release)];
        };
        foreach($set['packages'] as $name=>$package){
            $m=$package['manifest'];$s=$package['sources'];$closure=$this->graphs->resolve($m,$s,$lookup);
            foreach($closure['versions'] as $resolved=>$version){if(isset($versions[$resolved]) && $versions[$resolved]!==$version)throw new CatalogueException(409,'Collection requires incompatible duplicate versions.');$versions[$resolved]=$version;}
            if('theme'===$m->kind){$themeFiles[$name]=$this->themes->outputs(StrictJson::decode($s->{'meta/theme.json'},65536,24));continue;}
            $examples=isset($s->{'meta/examples.json'})?StrictJson::decode($s->{'meta/examples.json'},65536,24):[];
            if(count($examples)>20)throw new CatalogueException(422,'At most 20 publication/import examples are supported.');
            unset($closure['packages']);
            foreach([$m->defaults,...array_map(static fn($example)=>$example->props,$examples)] as $props){
                if(++$renders>64 || microtime(true)>$deadline)throw new CatalogueException(422,'Import exceeds its 64-render/45-second validation budget. Use smaller collections.');
                $result=$this->renderer->render([...$closure,'props'=>$props,'channel'=>bin2hex(random_bytes(16))]);
                if(!$result['ok'])throw new CatalogueException(422,'Import render/build validation failed: '.$result['error']['message'],[],'import_build_failed');
            }
        }
        $plan=$this->plan($set);return ['set'=>$set,'plan'=>$plan,'planHash'=>hash('sha256',$this->json->encode($plan)),'themeFiles'=>$themeFiles];
    }
    private function plan(array $set):array
    {
        $plan=['schemaVersion'=>1,'origin'=>'local file','roots'=>$set['roots'],'packages'=>[]];
        foreach($set['packages'] as $name=>$package){
            $m=$package['manifest'];$item=$this->catalogue->named($name);
            if($item && $item->getKind()!==$m->kind)throw new CatalogueException(409,'Catalogue kind/name collision: '.$name);
            $theme=$this->em->getRepository(Theme::class)->findOneBy(['name'=>$name]);
            if($theme && 'theme'!==$m->kind)throw new CatalogueException(409,'Name belongs to a typed theme: '.$name);
            $existing=$item?$this->em->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>$m->version]):null;
            if($existing && !hash_equals($existing->getArchiveHash(),$package['sha256']))throw new CatalogueException(409,'Immutable version has different bytes: '.$name.'@'.$m->version);
            if('theme'===$m->kind && $theme){
                $tr=$this->em->getRepository(ThemeRelease::class)->findOneBy(['theme'=>$theme,'version'=>$m->version]);
                if($tr && $this->json->encode($tr->getDocument())!==$this->json->encode(StrictJson::decode($package['sources']->{'meta/theme.json'},65536,24)))throw new CatalogueException(409,'Immutable typed theme version differs.');
            }
            $plan['packages'][]=['name'=>$name,'version'=>$m->version,'kind'=>$m->kind,'archiveSha256'=>$package['sha256'],'action'=>$existing?'skip-identical':'create-release','draft'=>$item?'preserve-existing':'create','license'=>$m->license,'dependencies'=>$m->dependencies,'files'=>$m->files];
        }
        return $plan;
    }
    public function apply(array $prepared,?User $owner,string $expectedPlan):array
    {
        if(!hash_equals($prepared['planHash'],$expectedPlan))throw new CatalogueException(409,'Import plan changed. Validate again.');
        try{return $this->em->wrapInTransaction(function() use($prepared,$owner,$expectedPlan):array {
            $this->connection->executeQuery("SELECT pg_advisory_xact_lock(hashtextextended('styleguide-import',0))");
            $plan=$this->plan($prepared['set']);
            if(!hash_equals($expectedPlan,hash('sha256',$this->json->encode($plan))))throw new CatalogueException(409,'Catalogue changed since the plan. Validate again.');
            $result=['applied'=>0,'skipped'=>0,'items'=>[],'themes'=>[],'themeReleases'=>[]];
            foreach($prepared['set']['packages'] as $name=>$package){
                $m=$package['manifest'];$item=$this->catalogue->named($name);
                $existing=$item?$this->em->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>$m->version]):null;
                if(!$item){[$ns,$slug]=explode('/',$name);$item=new CatalogueItem($ns,$slug,$m->kind,$m->title,$m->description,[]);$this->em->persist($item);$this->em->persist(new Draft($item,$m,$package['sources'],$owner));}
                if($existing){$result['skipped']++;}else{
                    [$key,$hash]=$this->store->putBytes($package['archiveBytes']);
                    if(!hash_equals($package['sha256'],$hash))throw new CatalogueException(422,'Staged archive checksum changed.');
                    $this->em->persist(new Release($item,$m->version,$m,$hash,$key));$result['applied']++;
                }
                $result['items'][$name]=$item->getId();
                if('theme'===$m->kind){
                    $document=StrictJson::decode($package['sources']->{'meta/theme.json'},65536,24);
                    $theme=$this->em->getRepository(Theme::class)->findOneBy(['name'=>$name]);
                    if(!$theme){$theme=new Theme($name,$document);$this->em->persist($theme);}
                    $tr=$this->em->getRepository(ThemeRelease::class)->findOneBy(['theme'=>$theme,'version'=>$m->version]);
                    if(!$tr){$tr=new ThemeRelease($theme,$m->version,$document,$prepared['themeFiles'][$name]);$this->em->persist($tr);}
                    $result['themes'][$name]=$theme->getId();$result['themeReleases'][$name]=$tr->getId();
                }
            }
            return $result;
        });}catch(UniqueConstraintViolationException){throw new CatalogueException(409,'A concurrent import changed an immutable name/version. Validate again.');}
    }
}
