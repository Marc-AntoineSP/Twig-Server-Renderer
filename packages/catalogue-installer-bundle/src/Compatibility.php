<?php
namespace Styleguide\Installer;
use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\StrictJson;
use Symfony\Component\Process\Process;
final readonly class Compatibility
{
    public function __construct(private Project $project){}
    public function versions():array
    {
        $versions=['php'=>PHP_VERSION,'twig'=>InstalledVersions::getPrettyVersion('twig/twig')??'0'];
        foreach(['tailwind'=>'tailwindcss','daisyui'=>'daisyui'] as $key=>$package){$path=$this->project->root.'/node_modules/'.$package.'/package.json';if(!is_file($path))throw new CatalogueException(422,'Install locked Tailwind 4 and daisyUI 5 in the consumer before installation.');$versions[$key]=StrictJson::decode(file_get_contents($path))->version??'0';}
        $map=$this->project->root.'/importmap.php';if(!is_file($map))throw new CatalogueException(422,'Configure AssetMapper importmap.php and @hotwired/stimulus 3.');$imports=require $map;$versions['stimulus']=$imports['@hotwired/stimulus']['version']??'0';
        return $versions;
    }
    public function doctor():array
    {
        foreach(['symfony/framework-bundle','symfony/asset-mapper','symfony/stimulus-bundle'] as $package)if(!InstalledVersions::isInstalled($package))throw new CatalogueException(422,'Install '.$package.' in the compatible Symfony consumer.');
        if(!Semver::satisfies(InstalledVersions::getPrettyVersion('symfony/framework-bundle'),'^8.1'))throw new CatalogueException(422,'Symfony 8.1+ is required.');
        $versions=$this->versions();foreach(['php'=>'^8.5','twig'=>'^3.30','tailwind'=>'^4.0','daisyui'=>'^5.0','stimulus'=>'^3.0'] as $key=>$constraint)if(!Semver::satisfies($versions[$key],$constraint))throw new CatalogueException(422,'Configure compatible '.$key.' '.$constraint.'.');
        $cfg=$this->project->config();$css=$this->project->path($cfg['css']['input']);$bootstrap=$this->project->path($cfg['bootstrap']);
        if(!is_file($css)||!preg_match('/@import\s+[\'"]tailwindcss[\'"]\s+source\(none\)/',file_get_contents($css)))throw new CatalogueException(422,'Use a separate Tailwind input with @import "tailwindcss" source(none), explicit @source paths and @plugin "daisyui". Configure --css-input and --css-output.');
        if(!is_file($this->project->path($cfg['css']['output'])))throw new CatalogueException(422,'Compile the consumer initial CSS output before installing packages.');
        if(!preg_match('/@plugin\s+[\'"]daisyui[\'"]/',file_get_contents($css)))throw new CatalogueException(422,'Configure the fixed daisyUI plugin in the CSS entry.');
        if(!is_file($bootstrap)||!preg_match('/\b(?:const|let|var)\s+app\s*=\s*startStimulusApp\s*\(/',file_get_contents($bootstrap)))throw new CatalogueException(422,'Configure a Stimulus bootstrap that declares const app = startStimulusApp().');
        $node=new Process(['node','--version'],$this->project->root);$node->setTimeout(5);$node->run();if(!$node->isSuccessful())throw new CatalogueException(422,'Provide Node with the fixed installed Tailwind CLI; no npm install or scripts run during package installation.');
        if(!is_file($this->project->root.'/node_modules/@tailwindcss/cli/dist/index.mjs'))throw new CatalogueException(422,'Install the locked @tailwindcss/cli 4 toolchain before installation.');
        if(!is_file($this->project->root.'/node_modules/acorn/dist/acorn.mjs')||!is_file($this->project->root.'/node_modules/lightningcss/node/index.js'))throw new CatalogueException(422,'Install the fixed parser toolchain: npm install --save-dev --save-exact acorn@8.19.0 lightningcss@1.32.0. Installation only parses source; it never executes imported modules.');
        return [...$versions,'node'=>trim($node->getOutput()),'cssInput'=>$cfg['css']['input'],'cssOutput'=>$cfg['css']['output'],'bootstrap'=>$cfg['bootstrap']];
    }
    public function package(\stdClass $manifest,array $versions):void
    {
        foreach($manifest->compatibility as $key=>$constraint){try{$ok=isset($versions[$key])&&Semver::satisfies($versions[$key],$constraint);}catch(\Throwable){$ok=false;}if(!$ok)throw new CatalogueException(422,$manifest->name.' requires '.$key.' '.$constraint.'; consumer has '.($versions[$key]??'unknown').'.');}
    }
}
