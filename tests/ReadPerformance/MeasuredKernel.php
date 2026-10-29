<?php
namespace App\Tests\ReadPerformance;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Instrument only this CLI process, never the serving studio or production image. */
final class MeasuredKernel extends \App\Kernel
{
    public function getProjectDir(): string { return dirname(__DIR__, 2); }
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasDefinition('doctrine.debug_data_holder')) $container->getDefinition('doctrine.debug_data_holder')->setPublic(true);
            }
        });
    }
}
