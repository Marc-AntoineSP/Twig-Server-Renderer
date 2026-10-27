<?php
namespace App\Command;
use App\Entity\ComposeToken;
use App\Mcp\Security\ComposeTokens;
use App\Security\UserCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
#[AsCommand(name:'styleguide:mcp:smoke',description:'Official MCP client round trip using a dedicated scoped test actor/draft; no original content is changed.')]
final class ComposeMcpSmokeCommand extends Command
{
    public function __construct(private readonly UserCreator $users,private readonly ComposeTokens $tokens,private readonly EntityManagerInterface $em,#[Autowire('%kernel.project_dir%')]private readonly string $project){parent::__construct();}
    protected function configure():void{$this->addOption('url',null,InputOption::VALUE_REQUIRED,'HTTP endpoint reachable from inside the app container.','http://localhost/mcp/compose');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $user=$this->users->create('mcp-smoke-'.bin2hex(random_bytes(8)).'@example.test',bin2hex(random_bytes(32)),'ROLE_EDITOR');[$token,$plain]=$this->tokens->issue($user,'Official SDK smoke',ComposeTokens::SCOPES,['mcp-smoke/*','studio-showcase/*']);
        try{$run=require $this->project.'/tools/mcp/smoke-client.php';$result=$run($input->getOption('url'),$plain);$output->writeln(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));return Command::SUCCESS;}
        finally{$token->revoke();$user->disable();$this->em->flush();}
    }
}
