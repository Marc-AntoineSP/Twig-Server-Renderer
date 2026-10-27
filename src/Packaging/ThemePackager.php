<?php
namespace App\Packaging;
use App\Entity\CatalogueItem;
use App\Entity\Draft;
use App\Entity\Release;
use App\Entity\ThemeRelease;
use App\Repository\CatalogueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageValidator;
use Styleguide\Contracts\ZipBuilder;
final readonly class ThemePackager
{
    public function __construct(private PackageValidator $validator,private CanonicalJson $json,private CatalogueRepository $catalogue,private EntityManagerInterface $em,private ArchiveStore $store){}
    public function package(ThemeRelease $theme):array
    {
        $t=$theme->getDocument();$sources=new \stdClass();
        foreach($theme->getFiles() as $name=>$content)$sources->{'theme.json'===$name?'meta/theme.json':'assets/styles/catalogue/'.$t->name.'/'.$name}=$content;
        $sources->{'meta/LICENSE'}="UNLICENSED\nNo distribution rights are granted by this generated package. Choose and document a licence before distribution.\n";
        $sources->{'meta/README.md'}='# '.$t->name."\n\nSystem-font theme. app.css is a complete Tailwind SOURCE entry for a fresh project.\nMerge theme.fragment.css and typography.css into an existing entry once; do not import a second Tailwind root.\npreview.css is COMPILED for the studio sample, not every possible utility.\nmeta/theme.json is authoritative and reimportable. No remote fonts or studio credentials.\n";
        $m=(object)['schemaVersion'=>1,'name'=>$t->name,'version'=>$t->version,'kind'=>'theme','title'=>$t->name,'description'=>'Typed system-font theme.','entry'=>'meta/theme.json','dependencies'=>[],'controllers'=>[],'safelist'=>[],'compatibility'=>(object)['php'=>'^8.5','twig'=>'^3.0','tailwind'=>'^4.0','daisyui'=>'^5.0','stimulus'=>'^3.0'],'files'=>[],'license'=>'UNLICENSED'];
        $m=$this->validator->validate($m,$sources,true);$bytes=ZipBuilder::bytes(['manifest.json'=>$this->json->encode($m,true)."\n",...(array)$sources]);
        return ['manifest'=>$m,'sources'=>$sources,'archiveBytes'=>$bytes,'sha256'=>hash('sha256',$bytes)];
    }
    public function publish(ThemeRelease $theme):Release
    {
        $package=$this->package($theme);$m=$package['manifest'];$item=$this->catalogue->named($m->name);
        if($item && 'theme'!==$item->getKind()) throw new CatalogueException(409,'Name belongs to a non-theme catalogue item.');
        $existing=$item?$this->em->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>$m->version]):null;
        if($existing)throw new CatalogueException(409,'Theme package version already exists and is immutable.');
        [$key,$hash]=$this->store->putBytes($package['archiveBytes']);
        try { return $this->em->wrapInTransaction(function() use($m,$package,$item,$key,$hash):Release {
            if(!$item){[$ns,$slug]=explode('/',$m->name);$item=new CatalogueItem($ns,$slug,'theme',$m->title,$m->description,[]);$this->em->persist($item);$this->em->persist(new Draft($item,$m,$package['sources']));}
            $release=new Release($item,$m->version,$m,$hash,$key);$this->em->persist($release);return $release;
        }); } catch (UniqueConstraintViolationException) { throw new CatalogueException(409,'A concurrent publication already created this theme package version.'); }
    }
}
