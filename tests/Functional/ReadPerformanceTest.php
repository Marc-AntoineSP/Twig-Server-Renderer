<?php

namespace App\Tests\Functional;

use App\Appearance\{AppearanceManager,DefaultThemes,ThemeFamilies};
use App\Catalogue\{CatalogueWriter,CreateItemInput,SearchQuery};
use App\Command\BackfillThemeFamiliesCommand;
use App\Entity\{CatalogueItem,Favorite,Release,Theme,ThemeFamily};
use App\Mcp\Security\ComposeTokens;
use App\Repository\CatalogueRepository;
use App\Tests\DatabaseTestCase;
use App\Tests\ReadPerformance\MeasuredKernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Ulid;

final class ReadPerformanceTest extends DatabaseTestCase
{
    protected static function getKernelClass(): string { return MeasuredKernel::class; }
    protected static function createKernel(array $options = []): \Symfony\Component\HttpKernel\KernelInterface
    {
        return parent::createKernel(['debug' => true] + $options);
    }
    private function em(): EntityManagerInterface { return self::getContainer()->get(EntityManagerInterface::class); }
    private function prefix(): string { return 'read-'.strtolower((string) new Ulid()); }
    private function queries(): array
    {
        $sql = [];
        foreach (self::getContainer()->get('doctrine.debug_data_holder')->getData() as $queries) foreach ($queries as $q) $sql[] = $q['sql'];
        return $sql;
    }
    private function resetQueries(): void { $this->em()->clear(); self::getContainer()->get('doctrine.debug_data_holder')->reset(); }

    public function testCatalogueBulkHydrationPreservesBothOrdersFiltersCountsAndEmptyPages(): void
    {
        self::bootKernel(); $owner = $this->account(); $other = $this->account(); $name = $this->prefix(); $ids = [];
        $writer = self::getContainer()->get(CatalogueWriter::class);
        for ($i = 0; $i < 25; ++$i) {
            $item = $writer->create(new CreateItemInput($name.'/item-'.sprintf('%02d',$i),'component','Read sample','',['read','even']),$owner)->getItem(); $ids[] = $item->getId();
            $this->em()->persist(new Favorite($i === 24 ? $other : $owner,$item));
        }
        $this->em()->flush(); $repo = self::getContainer()->get(CatalogueRepository::class);
        foreach (['name','updated'] as $sort) {
            $this->resetQueries();
            $page = $repo->search(new SearchQuery(q:$name,sort:$sort,page:2,tags:'READ,even',kind:'component'));
            $expected = $this->em()->getConnection()->fetchFirstColumn('SELECT id FROM catalogue_item WHERE namespace=? ORDER BY '.($sort === 'name' ? 'namespace,slug' : 'updated_at DESC,id DESC').' LIMIT 12 OFFSET 12',[$name]);
            self::assertSame($expected,array_map(fn($item)=>$item->getId(),$page['items'])); self::assertSame(25,$page['total']); self::assertSame(3,$page['pages']);
            $hydrations = array_filter($this->queries(),static fn($sql)=>str_contains($sql,'FROM catalogue_item t0'));
            self::assertCount(1,$hydrations,'One bounded item hydration query for a twelve-card page.');
        }
        $last = $repo->search(new SearchQuery(q:$name,sort:'name',page:99,favorites:true),$owner);
        self::assertSame(24,$last['total']); self::assertSame(2,$last['page']); self::assertSame(array_slice($ids,12,12),array_map(fn($i)=>$i->getId(),$last['items']));
        $empty = $repo->search(new SearchQuery(q:$name,tags:'missing',page:99));
        self::assertSame(0,$empty['total']); self::assertSame(1,$empty['page']); self::assertSame([],$empty['items']);
        self::assertSame(1,$repo->search(new SearchQuery(q:$name,favorites:true),$other)['total']);
    }

    public function testThemeFavoritesStillMatchLegacyCatalogueThemeIdentity(): void
    {
        self::bootKernel(); $owner = $this->account(); $other = $this->account(); $namespace = $this->prefix();
        $theme = new Theme($namespace.'/theme',DefaultThemes::document('light',$namespace.'/theme'));
        $item = new CatalogueItem($namespace,'theme','theme','Typed theme');
        $this->em()->persist($theme); $this->em()->persist($item); $this->em()->persist(new Favorite($owner,$theme)); $this->em()->flush();
        $repo = self::getContainer()->get(CatalogueRepository::class);
        self::assertSame([$item->getId()],array_map(fn($i)=>$i->getId(),$repo->search(new SearchQuery(q:$namespace,favorites:true),$owner)['items']));
        self::assertSame(0,$repo->search(new SearchQuery(q:$namespace,favorites:true),$other)['total']);
        $this->em()->remove($item); $this->em()->flush();
    }

    public function testLegacyReadsDoNotFlushAndExplicitBoundedBackfillIsRestartableWithoutReplacingVariants(): void
    {
        $client = self::createClient(); $this->signIn($client,$this->account('ROLE_READER'));
        $families = self::getContainer()->get(ThemeFamilies::class); $families->default(); $prefix = $this->prefix(); $ids = [];
        foreach (['light','dark','light'] as $i=>$mode) {
            $doc = DefaultThemes::document($mode,$prefix.'/theme-'.$i);
            $doc->provenance = (object)['managed'=>true,'collection'=>'website-inspired','reference'=>$prefix,'title'=>'Legacy fixture '.$i,'surface'=>'Disposable read fixture','slug'=>'legacy-fixture-'.$i,'modeOrigin'=>'derived'];
            $theme = new Theme($doc->name,$doc); $this->em()->persist($theme); $ids[] = $theme->getId();
        }
        $this->em()->flush(); $db = $this->em()->getConnection();
        $count = (int)$db->fetchOne('SELECT COUNT(*) FROM theme_family');
        // A read must not flush even unrelated pending work in the unit of work.
        $pending = new CatalogueItem($prefix,'pending','component','Pending'); $this->em()->persist($pending);
        $fallback = $families->forTheme($this->em()->find(Theme::class,$ids[0]));
        self::assertSame($ids[0],$fallback->getId()); self::assertSame(0,(int)$db->fetchOne('SELECT COUNT(*) FROM catalogue_item WHERE id=?',[$pending->getId()])); $this->em()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $client->request('GET','/api/v1/themes/'.$ids[0]); self::assertResponseIsSuccessful();
        self::assertSame($ids[0],json_decode($client->getResponse()->getContent(),true)['family']['id']);
        self::assertSame($count,(int)$db->fetchOne('SELECT COUNT(*) FROM theme_family'));
        self::assertSame([],array_values(array_filter($this->queries(),fn($sql)=>preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i',$sql))));
        $client->request('GET','/api/v1/appearance/families'); self::assertResponseIsSuccessful();
        self::assertContains($ids[0],array_column(json_decode($client->getResponse()->getContent(),true)['families'],'id'));
        $tester = new CommandTester(self::getContainer()->get(BackfillThemeFamiliesCommand::class));
        $orphanSql = 'SELECT COUNT(*) FROM theme t WHERE NOT EXISTS (SELECT 1 FROM theme_family f WHERE f.light_theme_id=t.id OR f.dark_theme_id=t.id)';
        $before = (int)$db->fetchOne($orphanSql); self::assertGreaterThanOrEqual(3,$before);
        self::assertSame(2,$tester->execute(['--limit'=>'0'])); self::assertSame($before,(int)$db->fetchOne($orphanSql));
        self::assertSame(0,$tester->execute(['--limit'=>'1'])); self::assertSame($before-1,(int)$db->fetchOne($orphanSql));
        while ((int)$db->fetchOne($orphanSql)>0) self::assertSame(0,$tester->execute(['--limit'=>'1000']));
        $after = (int)$db->fetchOne('SELECT COUNT(*) FROM theme_family');
        self::assertSame(0,$tester->execute(['--limit'=>'100'])); self::assertStringContainsString('processed=0 remaining=0',$tester->getDisplay()); self::assertSame($after,(int)$db->fetchOne('SELECT COUNT(*) FROM theme_family'));
        $this->em()->clear(); $families = self::getContainer()->get(ThemeFamilies::class);
        $first = $families->forTheme($this->em()->find(Theme::class,$ids[0])); $second = $families->forTheme($this->em()->find(Theme::class,$ids[1]));
        self::assertSame($first->getId(),$second->getId()); self::assertCount(2,$first->variants());
        self::assertNotSame($first->getId(),$families->forTheme($this->em()->find(Theme::class,$ids[2]))->getId());
    }

    public function testFamilyPageSummariesAreBoundedAndChoicesDoNotHydrateThemeDocuments(): void
    {
        self::bootKernel(); $this->account(); $families = self::getContainer()->get(ThemeFamilies::class); $families->default();
        $name = $this->prefix(); $ids = [];
        for ($i=0;$i<12;$i++) {
            $family = new ThemeFamily($name.'/family-'.$i,'Read family '.$i); $this->em()->persist($family);
            foreach (['light','dark'] as $mode) {
                $themeName = $name.'/theme-'.$i.'-'.$mode;
                $theme = new Theme($themeName,DefaultThemes::document($mode,$themeName)); $this->em()->persist($theme); $family->attach($theme);
                if ($mode === 'light') $ids[] = $theme->getId();
            }
        }
        $this->em()->flush(); $this->em()->clear();
        $themes = $this->em()->getRepository(Theme::class)->findBy(['id'=>$ids]);
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $summaries = $families->describeMany($themes); self::assertCount(1,$this->queries());
        foreach ($themes as $theme) {
            self::assertCount(2,$summaries[$theme->getId()]['variants']);
            self::assertSame($families->describe($families->forTheme($theme)),$summaries[$theme->getId()]);
        }
        $this->resetQueries(); $choices = $families->choices();
        self::assertLessThanOrEqual(2,count($this->queries()));
        foreach ($this->queries() as $sql) self::assertDoesNotMatchRegularExpression('/SELECT .*document.* AS document_/i',$sql);
        self::assertContains('Read family 0',array_column($choices,'label'));
    }

    public function testApplyingAnUnrepairedLegacyChoiceCreatesItsRealFamilyOnlyInTheExplicitWrite(): void
    {
        self::bootKernel(); $owner = $this->account(); $families = self::getContainer()->get(ThemeFamilies::class); $families->default();
        $name = $this->prefix().'/legacy'; $theme = new Theme($name,DefaultThemes::document('light',$name)); $this->em()->persist($theme); $this->em()->flush();
        $manager = self::getContainer()->get(AppearanceManager::class);
        $prepared = $manager->prepare(['kind'=>'family','familyId'=>$theme->getId()],$owner);
        self::assertNotSame($theme->getId(),$prepared['familyId']); self::assertSame($theme->getId(),$prepared['variants']['light']['themeId']);
        self::assertSame($prepared['familyId'],$manager->persist($prepared,$owner,0)['familyId']);
        self::assertSame($prepared['familyId'],$families->ensureForTheme($theme)->getId());
    }

    public function testMcpEnrichmentQueryBoundAndExactAuthorizedPaginationAndTopTenSummaries(): void
    {
        $client = self::createClient(); $owner = $this->account(); $other = $this->account(); $namespace = $this->prefix(); $title = $namespace.' sample'; $ids = [];
        $writer = self::getContainer()->get(CatalogueWriter::class);
        for ($i=0;$i<56;$i++) {
            $name = ($i<5?'aaa-private-'.$namespace:$namespace).'/item-'.sprintf('%02d',$i);
            $draft = $writer->create(new CreateItemInput($name,$i%2?'component':'layout',$title),$owner); $item=$draft->getItem();
            if ($i>=5) $ids[]=$item->getId();
            $this->em()->persist(new Favorite($i===55?$other:$owner,$item));
            if ($i===5) for($version=0;$version<12;$version++) {
                $manifest=$draft->getManifestData(); $manifest->version='1.0.'.$version;
                [$key,$hash]=self::getContainer()->get(\App\Packaging\ArchiveStore::class)->put($manifest,$draft->getSourceMap());
                $release=new Release($item,$manifest->version,$manifest,$hash,$key);
                (new \ReflectionProperty($release,'createdAt'))->setValue($release,new \DateTimeImmutable('2026-01-01T00:00:'.sprintf('%02d',$version).'Z'));
                $this->em()->persist($release);
            }
        }
        $this->em()->flush();
        [, $plain]=self::getContainer()->get(ComposeTokens::class)->issue($owner,'Bounded read',['compose:read'],[$namespace.'/*']);
        $headers=['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json, text/event-stream','HTTP_AUTHORIZATION'=>'Bearer '.$plain]; $sequence=0;
        $rpc=function(string $method,mixed $params,bool $notification=false)use($client,&$headers,&$sequence):array{
            $body=['jsonrpc'=>'2.0','method'=>$method,'params'=>$params]; if(!$notification)$body['id']=++$sequence;
            $client->request('POST','/mcp/compose',server:$headers,content:json_encode($body,JSON_THROW_ON_ERROR));
            if($session=$client->getResponse()->headers->get('Mcp-Session-Id'))$headers['HTTP_MCP_SESSION_ID']=$session;
            self::assertResponseIsSuccessful(); if($notification)return [];
            $content=$client->getResponse()->getContent(); if($content===false){ob_start();$client->getResponse()->sendContent();$content=ob_get_clean();}
            if(str_starts_with($client->getResponse()->headers->get('Content-Type',''),'text/event-stream')){preg_match_all('/^data: (.+)$/m',$content,$matches);$content=end($matches[1]);}
            return json_decode($content,true,flags:JSON_THROW_ON_ERROR);
        };
        $rpc('initialize',['protocolVersion'=>'2025-11-25','capabilities'=>(object)[],'clientInfo'=>['name'=>'query-bound','version'=>'1']]);$headers['HTTP_MCP_PROTOCOL_VERSION']='2025-11-25';$rpc('notifications/initialized',(object)[],true);
        $search=function(array $input)use($rpc):array{$r=$rpc('tools/call',['name'=>'compose_search_catalogue','arguments'=>$input]);self::assertFalse($r['result']['isError']??false,json_encode($r));return $r['result']['structuredContent'];};
        $one=$search(['query'=>$title,'limit'=>1]); $small=count($this->queries());
        $many=$search(['query'=>$title,'limit'=>50]); self::assertLessThanOrEqual($small,count($this->queries())); self::assertLessThanOrEqual(8,count($this->queries()));
        self::assertSame(array_slice($ids,0,50),array_column($many['items'],'id')); self::assertSame($one['items'][0],$many['items'][0]); self::assertSame(50,$many['nextOffset']);
        $first=$many['items'][0]; self::assertSame(['1.0.11','1.0.10','1.0.9','1.0.8','1.0.7','1.0.6','1.0.5','1.0.4','1.0.3','1.0.2'],array_column($first['releases'],'version'));
        self::assertSame('source',$first['editor']); self::assertFalse($first['editableInComposer']); self::assertTrue($first['favorite']); self::assertTrue($first['insertable']); self::assertSame(1,$first['revision']);
        self::assertSame('composition',$many['items'][1]['editor']); self::assertTrue($many['items'][1]['editableInComposer']); self::assertFalse($many['items'][1]['insertable']);
        $last=$search(['query'=>$title,'offset'=>50,'limit'=>50]);self::assertSame([$ids[50]],array_column($last['items'],'id'));self::assertNull($last['nextOffset']);self::assertFalse($last['items'][0]['favorite']);
        $fav=$search(['query'=>$title,'favoritesOnly'=>true,'limit'=>50]);self::assertSame(array_slice($ids,0,50),array_column($fav['items'],'id'));self::assertNull($fav['nextOffset']);
        self::assertSame([],$search(['query'=>$title,'offset'=>99,'limit'=>50])['items']);
        // An absent selected draft retains the existing error rather than exposing a partial summary.
        $this->em()->getConnection()->executeStatement('DELETE FROM draft WHERE item_id=?',[$ids[50]]);
        $error=$rpc('tools/call',['name'=>'compose_search_catalogue','arguments'=>['query'=>$title,'offset'=>50,'limit'=>1]]);
        self::assertTrue($error['result']['isError']);self::assertStringContainsString('Draft not found.',json_encode($error));
        $this->em()->getConnection()->executeStatement('DELETE FROM catalogue_item WHERE id=?',[$ids[50]]);
    }
}
