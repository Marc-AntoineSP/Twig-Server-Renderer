<?php

require __DIR__.'/vendor/autoload.php';

use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\Sandbox;
use Twig\Sandbox\SecurityPolicy;

try {
    $input = stream_get_contents(STDIN, 3145729);
    if (strlen($input) > 3145728) {
        throw new RuntimeException('Render input exceeds 3 MiB.');
    }
    $job = json_decode($input, true, 24, JSON_THROW_ON_ERROR);
    if (!is_array($job['templates'] ?? null) || !is_array($job['props'] ?? null) || !is_string($job['entry'] ?? null) || count($job['templates']) > 100 || strlen(json_encode($job['props'])) > 65536) {
        throw new RuntimeException('Invalid bounded render input.');
    }
    foreach (['app', 'request', 'user', '_self', '_context', '_charset'] as $reserved) {
        if (array_key_exists($reserved, $job['props'])) {
            throw new RuntimeException('Reserved props name: '.$reserved);
        }
    }
    $total = 0;
    foreach ($job['templates'] as $name => $source) {
        if (!is_string($name) || !str_starts_with($name, 'catalogue/') || !str_ends_with($name, '.html.twig') || !preg_match('~^catalogue/[A-Za-z0-9_-]+/[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)+$~D', $name) || str_contains($name, '..') || !is_string($source) || strlen($source) > 1048576 || ($total += strlen($source)) > 2097152) {
            throw new RuntimeException('Invalid finite template map.');
        }
    }
    $environment = new Environment(new ArrayLoader($job['templates']), ['strict_variables' => true, 'autoescape' => 'html', 'cache' => false]);
    $policy = new SecurityPolicy(
        allowedTags: ['if', 'for', 'set', 'include'],
        allowedFilters: ['escape', 'e', 'default', 'length', 'upper', 'lower', 'join', 'json_encode'],
        allowedFunctions: ['range'],
        allowedTests: ['defined', 'empty', 'null', 'none', 'true', 'false', 'same as', 'even', 'odd', 'iterable', 'sequence', 'mapping'],
    );
    $policy->setStrict(true);
    $sandbox = new Sandbox($environment, $policy);
    // Validate includes even in branches that do not execute for the selected props.
    $edges = [];
    $nodeCount = 0;
    $inspect = function (\Twig\Node\Node $node, string $name) use (&$inspect, &$edges, &$nodeCount, $job, $policy): void {
        if (++$nodeCount > 20000) {
            throw new RuntimeException('Template syntax exceeds the node budget.');
        }
        $tags = $node->getNodeTag() ? [$node->getNodeTag()] : [];
        $filters = $node instanceof \Twig\Node\Expression\FilterExpression ? [$node->getAttribute('name')] : [];
        $functions = $node instanceof \Twig\Node\Expression\FunctionExpression ? [$node->getAttribute('name')] : [];
        if ($node->hasAttribute('sandboxed_function_name')) {
            $functions[] = $node->getAttribute('sandboxed_function_name');
        }
        $tests = $node instanceof \Twig\Node\Expression\TestExpression ? [$node->getAttribute('name')] : [];
        try {
            $policy->checkSecurity($tags, $filters, $functions, $tests);
        } catch (\Twig\Sandbox\SecurityError $error) {
            $error->setTemplateLine($node->getTemplateLine());
            $error->setSourceContext(new \Twig\Source($job['templates'][$name], $name));
            throw $error;
        }
        if ($node instanceof \Twig\Node\IncludeNode) {
            $expression = $node->getNode('expr');
            if (!$expression instanceof \Twig\Node\Expression\ConstantExpression || !is_string($expression->getAttribute('value')) || !array_key_exists($expression->getAttribute('value'), $job['templates'])) {
                throw new \Twig\Sandbox\SecurityError('Includes must name a literal template in the locked package closure.', $node->getTemplateLine(), new \Twig\Source($job['templates'][$name], $name));
            }
            $edges[$name][] = $expression->getAttribute('value');
        }
        foreach ($node as $child) {
            $inspect($child, $name);
        }
    };
    foreach ($job['templates'] as $name => $source) {
        $inspect($environment->parse($environment->tokenize(new \Twig\Source($source, $name))), $name);
    }
    $visit = function (string $name, array $stack = []) use (&$visit, $edges): void {
        if (isset($stack[$name]) || count($stack) > 20) {
            throw new RuntimeException('Template include cycle or depth limit.');
        }
        $stack[$name] = true;
        foreach ($edges[$name] ?? [] as $include) {
            $visit($include, $stack);
        }
    };
    foreach (array_keys($job['templates']) as $name) {
        $visit($name);
    }
    $html = '';
    foreach ($sandbox->stream($job['entry'], $job['props']) as $chunk) {
        if (strlen($html) + strlen($chunk) > 1048576) {
            throw new RuntimeException('Rendered HTML exceeds 1 MiB.');
        }
        $html .= $chunk;
    }
    echo json_encode(['ok' => true, 'html' => $html], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (TwigError $error) {
    echo json_encode(['ok' => false, 'error' => ['code' => 'twig_invalid', 'message' => mb_substr($error->getRawMessage(), 0, 1000), 'template' => $error->getSourceContext()?->getName(), 'line' => $error->getTemplateLine()]], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'error' => ['code' => 'render_invalid', 'message' => mb_substr($error->getMessage(), 0, 1000)]], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
}
