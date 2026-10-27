<?php
namespace Styleguide\Installer;
use Styleguide\Contracts\PackageValidator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
final class CatalogueInstallerBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container,ContainerBuilder $builder):void
    {
        // Package CSS is compiler source. Only the consumer's compiled CSS is served.
        $container->extension('framework',['asset_mapper'=>['excluded_patterns'=>['*/styles/catalogue/*.css', '*/styles/catalogue/*.txt']]]);
    }
    public function loadExtension(array $config,ContainerConfigurator $container,ContainerBuilder $builder):void
    {
        $services=$container->services()->defaults()->autowire()->autoconfigure();$services->load('Styleguide\\Contracts\\',dirname((new \ReflectionClass(PackageValidator::class))->getFileName()).'/')->exclude([dirname((new \ReflectionClass(PackageValidator::class))->getFileName()).'/CatalogueException.php']);
        foreach([Registries::class,RegistryClient::class,Compatibility::class,PortableTwig::class,Journal::class,Integration::class,Installer::class,ThemeExtension::class] as $class)$services->set($class);
        $services->set(Project::class)->arg('$root',param('kernel.project_dir'));
        foreach(['registry'=>'styleguide:registry:add','require'=>'styleguide:require','install'=>'styleguide:install','theme'=>'styleguide:theme:install','update'=>'styleguide:update','remove'=>'styleguide:remove','doctor'=>'styleguide:doctor'] as $operation=>$name){$services->set('styleguide.installer.command.'.$operation,InstallerCommand::class)->arg('$operation',$operation)->arg('$name',$name)->tag('console.command',['command'=>$name]);}
    }
}
