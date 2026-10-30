<?php
namespace App\Tests\PublicationBenchmark;

use App\Entity\Release;
use App\Testing\BrowserRunGuard;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\{CanonicalJson,CompositionValidator,PackageArchive,PackageValidator,PropsValidator,SafeArchive,ThemeCssGenerator,ThemeValidator};

/** CLI-only benchmark: both database and blobs must prove ownership before any request. */
final class Context
{
    public readonly EntityManagerInterface $em;
    public readonly array $owner;
    private \App\Kernel $kernel;
    public const RECEIPT = BrowserRunGuard::DIRECTORY.'/publication-benchmark.json';
    public function __construct()
    {
        $this->kernel = new \App\Kernel('dev',false); $this->kernel->boot();
        $this->em = $this->kernel->getContainer()->get('doctrine')->getManager();
        $this->owner = (new BrowserRunGuard($this->em->getConnection()))->verify();
    }
    public function client(string $role = 'editor'): Client
    {
        $accounts = json_decode(file_get_contents(BrowserRunGuard::DIRECTORY.'/accounts.json'),true,flags:JSON_THROW_ON_ERROR);
        return new Client($accounts[$role],$this->owner);
    }
    public function fixtures(): array
    {
        $data = json_decode(file_get_contents(self::RECEIPT),true,flags:JSON_THROW_ON_ERROR);
        if ($data['runId'] !== $this->owner['runId'] || $data['seed'] !== 'publication-v1') throw new \RuntimeException('Fixture ownership mismatch.');
        return $data['profiles'];
    }
    public function release(array $fixture,string $version): ?Release
    {
        $this->em->clear();
        return $this->em->getRepository(Release::class)->findOneBy(['item'=>$fixture['id'],'version'=>$version]);
    }
    public function verifyRelease(array $fixture,string $version): array
    {
        $release = $this->release($fixture,$version) ?? throw new \RuntimeException('Successful publication has no committed release.');
        $json = new CanonicalJson(); $themes = new ThemeValidator();
        $validator = new PackageValidator(new PropsValidator(),$themes,new ThemeCssGenerator($themes,$json),new CompositionValidator());
        $reader = new \App\Rendering\ReleaseSourceReader(new PackageArchive(new SafeArchive(),$validator),'/app/var/catalogue');
        $sources = $reader->read($release);
        if (hash('sha256',$json->encode($sources)) !== $fixture['sourceHash']) throw new \RuntimeException('Published sources changed from the captured fixture.');
        if ($release->getManifestData()->version !== $version) throw new \RuntimeException('Published version mismatch.');
        return ['releaseId'=>$release->getId(),'version'=>$version,'sourceMatches'=>true,'archiveBytes'=>filesize($reader->path($release))];
    }
}
