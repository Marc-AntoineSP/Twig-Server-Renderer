<?php

namespace App\Tests\Integration;

use App\Rendering\WorkerClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorkerBoundaryTest extends KernelTestCase
{
    private function render(string $source, array $props = []): array
    {
        return self::getContainer()->get(WorkerClient::class)->render(['entry' => 'catalogue/tests/worker/component.html.twig', 'templates' => ['catalogue/tests/worker/component.html.twig' => $source], 'props' => (object) $props]);
    }

    public function testFiniteTwigSandboxEscapesProps(): void
    {
        self::bootKernel();
        $result = $this->render('<h2>{% if message %}{{ message }}{% endif %}</h2>', ['message' => '<script>bad()</script>']);
        self::assertTrue($result['ok'], json_encode($result));
        self::assertSame('<h2>&lt;script&gt;bad()&lt;/script&gt;</h2>', $result['html']);
    }

    public static function forbiddenTwig(): iterable
    {
        yield 'app' => ['{{ app.request }}'];
        yield 'application include' => ["{% include 'base.html.twig' %}"];
        yield 'filesystem' => ["{% include '/etc/passwd' %}"];
        yield 'inactive unknown include' => ["{% if false %}{% include 'base.html.twig' %}{% endif %}"];
        yield 'PHP function' => ["{{ constant('PHP_VERSION') }}"];
        yield 'raw' => ["{{ message|raw }}"];
        yield 'callable' => ["{{ ['system']|map('system') }}"];
        yield 'recursive include' => ["{% include 'catalogue/tests/worker/component.html.twig' %}"];
    }

    #[DataProvider('forbiddenTwig')]
    public function testForbiddenTwigFailsAndSupervisorRemainsHealthy(string $source): void
    {
        self::bootKernel();
        $result = $this->render($source, ['message' => 'test']);
        self::assertFalse($result['ok'], json_encode($result));
        self::assertTrue($this->render('Still healthy')['ok']);
    }

    public function testRunawayTwigIsBoundedAndNextRenderWorks(): void
    {
        self::bootKernel();
        $started = microtime(true);
        $result = $this->render('{% for i in 1..100000000 %}{{ i }}{% endfor %}');
        self::assertFalse($result['ok']);
        self::assertLessThan(5, microtime(true) - $started);
        self::assertTrue($this->render('Recovered')['ok']);
    }

    public function testActualCompilerEmitsResponsiveClassesAndDaisyStyles(): void
    {
        self::bootKernel();
        $result = self::getContainer()->get(WorkerClient::class)->build(['sources' => (object) ['templates/catalogue/tests/worker/component.html.twig' => '<div class="grid md:grid-cols-3 alert alert-success"></div>'], 'controllers' => [], 'safelist' => ['text-xl']]);
        self::assertTrue($result['ok'], json_encode($result));
        self::assertStringContainsString('md\\:grid-cols-3', $result['css']);
        self::assertStringContainsString('.alert', $result['css']);
        self::assertStringContainsString('.text-xl', $result['css']);
        self::assertStringContainsString('Stimulus', $result['js']);
    }

    public function testCpuOnlyLoopHitsWallTimeoutAndSupervisorSurvives(): void
    {
        self::bootKernel();
        $started = microtime(true);
        $result = $this->render('{% for i in 1..100000 %}{% for j in 1..100000 %}{% set n = i + j %}{% endfor %}{% endfor %}');
        self::assertFalse($result['ok']);
        self::assertSame('render_resource_limit', $result['error']['code']);
        self::assertGreaterThan(1.5, microtime(true) - $started);
        self::assertLessThan(5, microtime(true) - $started);
        self::assertTrue($this->render('Recovered after timeout')['ok']);
    }

    public function testCompilerRejectsCssPlugins(): void
    {
        self::bootKernel();
        $result = self::getContainer()->get(WorkerClient::class)->build(['sources' => (object) ['assets/catalogue/tests/worker/unsafe.css' => '@plugin "untrusted";'], 'controllers' => [], 'safelist' => []]);
        self::assertFalse($result['ok']);
        self::assertTrue(self::getContainer()->get(WorkerClient::class)->ready());
    }

    public function testLiteralPackageStylesArePresentInActualCompiledOutput(): void
    {
        self::bootKernel();
        $result=self::getContainer()->get(WorkerClient::class)->build(['sources'=>(object)['assets/catalogue/tests/worker/style.css'=>'.owned-custom-style { color: #123456; }'],'controllers'=>[],'safelist'=>[]]);
        self::assertTrue($result['ok'],json_encode($result));
        self::assertStringContainsString('.owned-custom-style { color: #123456; }',$result['css']);
    }

    public function testInvalidLiteralCssFailsBeforeSuccessAndNextBuildWorks():void
    {
        self::bootKernel();$worker=self::getContainer()->get(WorkerClient::class);
        $result=$worker->build(['sources'=>(object)['assets/catalogue/tests/worker/style.css'=>'.broken { color: rgb(; }'],'controllers'=>[],'safelist'=>[]]);
        self::assertFalse($result['ok']);self::assertStringContainsString('CSS syntax error',$result['error']['message']);
        self::assertTrue($worker->build(['sources'=>(object)['assets/catalogue/tests/worker/style.css'=>'.healthy { color: red; }'],'controllers'=>[],'safelist'=>[]])['ok']);
    }

    public static function forbiddenImports(): iterable
    {
        yield ['import fs from "node:fs"; export default class {}'];
        yield ['import "https://example.test/script.js"; export default class {}'];
        yield ['import "/etc/passwd"; export default class {}'];
        yield ['const path = "./module.js"; import(path); export default class {}'];
    }

    #[DataProvider('forbiddenImports')]
    public function testCompilerRejectsForbiddenImportsWithoutExecutingCode(string $source): void
    {
        self::bootKernel();
        $result = self::getContainer()->get(WorkerClient::class)->build([
            'sources' => (object) ['assets/controllers/catalogue/tests/worker_controller.js' => $source],
            'controllers' => [(object) ['identifier' => 'catalogue--tests--worker', 'path' => 'assets/controllers/catalogue/tests/worker_controller.js']], 'safelist' => [],
        ]);
        self::assertFalse($result['ok'], json_encode($result));
        self::assertTrue(self::getContainer()->get(WorkerClient::class)->ready());
    }
}
