<?php

namespace App\Command;

use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use Styleguide\Contracts\PackageValidator;
use App\Repository\CatalogueRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'styleguide:fixtures', description: 'Add the bundled example drafts without replacing existing catalogue items.')]
final class FixturesCommand extends Command
{
    public function __construct(
        private readonly CatalogueWriter $writer,
        private readonly CatalogueRepository $catalogue,
        private readonly PackageValidator $validator,
        private readonly \App\Packaging\TransferImporter $imports,
        private readonly \App\Composition\CompositionCompiler $compositions,
        private readonly \Doctrine\ORM\EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/agent-kit/examples/packages')]
        private readonly string $directory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (['alert', 'notice-grid'] as $fixture) {
            $root = $this->directory.'/'.$fixture;
            $manifest = json_decode(file_get_contents($root.'/manifest.json'), false, 32, JSON_THROW_ON_ERROR);
            if ($fixture === 'notice-grid') {
                $alert=$this->catalogue->named('awui/alert');
                if(!$alert||!$this->em->getRepository(\App\Entity\Release::class)->findOneBy(['item'=>$alert,'version'=>'1.0.0'])){
                    // Import the bundled immutable dependency; never publish an edited user draft.
                    $prepared=$this->imports->prepare($this->directory.'/alert/manifest.json',adjacent:true);
                    $this->imports->apply($prepared,null,$prepared['planHash']);
                    $output->writeln('awui/alert@1.0.0: validated bundled dependency installed; existing draft preserved.');
                }
            }
            if ($this->catalogue->named($manifest->name)) {
                $output->writeln($manifest->name.': already exists; left unchanged.');
                continue;
            }
            $sources = new \stdClass();
            foreach ($manifest->files as $file) {
                $this->validator->path($file->path);
                $sources->{$file->path} = file_get_contents($root.'/'.$file->path);
            }
            $this->validator->validate($manifest, $sources);
            if(isset($sources->{'meta/composition.json'})){
                [$manifest,$sources]=$this->compositions->materialize($manifest,$sources,\Styleguide\Contracts\StrictJson::decode($sources->{'meta/composition.json'},65536));
            }
            $this->writer->create(new CreateItemInput($manifest->name, $manifest->kind, $manifest->title, $manifest->description, ['example', $fixture === 'alert' ? 'feedback' : 'composition']), null, $manifest, $sources);
            $output->writeln($manifest->name.': draft created.');
        }

        return Command::SUCCESS;
    }
}
