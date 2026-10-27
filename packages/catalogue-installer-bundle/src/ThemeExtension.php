<?php
namespace Styleguide\Installer;
use Styleguide\Contracts\PackageReference;
use Styleguide\Contracts\StrictJson;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
final class ThemeExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly Project $project){}
    public function getGlobals():array
    {
        $theme=$this->project->config()['activeTheme'];if(!$theme)return ['styleguide_theme'=>null];[$name,$version]=PackageReference::parse($theme);$path=$this->project->path('.styleguide/packages/'.$name.'/'.$version.'/meta/theme.json');
        if(!is_file($path))return ['styleguide_theme'=>null];$document=StrictJson::decode(file_get_contents($path),65536,24);return ['styleguide_theme'=>$document->cssName??null];
    }
}
