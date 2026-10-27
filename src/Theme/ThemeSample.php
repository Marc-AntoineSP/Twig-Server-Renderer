<?php

namespace App\Theme;

use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Catalogue\SourceFactory;
use App\Catalogue\Validation\PackageValidator;
use App\Entity\CatalogueItem;
use App\Repository\CatalogueRepository;

final readonly class ThemeSample
{
    public const TWIG = <<<'TWIG'
<section class="rounded-box bg-base-100 p-6 text-base-content">
    <h1 class="font-heading text-2xl font-bold">Theme workshop</h1>
    <p class="my-4">A portable palette, geometry and typography.</p>
    <div class="flex flex-wrap gap-3">
        <button class="btn btn-primary" type="button">Primary</button>
        <button class="btn btn-secondary" type="button">Secondary</button>
        <button class="btn btn-accent" type="button">Accent</button>
        <button class="btn btn-neutral" type="button">Neutral</button>
    </div>
    <div class="mt-5 grid gap-3 md:grid-cols-2">
        <div class="alert alert-info">Information</div>
        <div class="alert alert-success">Success</div>
        <div class="alert alert-warning">Warning</div>
        <div class="alert alert-error">Error</div>
    </div>
    <input class="input input-bordered mt-5 w-full" aria-label="Sample input" placeholder="A field using your theme">
</section>
TWIG;

    public function __construct(private CatalogueRepository $catalogue, private CatalogueWriter $writer, private SourceFactory $factory)
    {
    }

    public function item(): CatalogueItem
    {
        $existing = $this->catalogue->named('studio/theme-sample');
        if ($existing) { return $existing; }
        $input = new CreateItemInput('studio/theme-sample', 'component', 'Theme workshop sample', 'Palette, typography and geometry reference.', ['theme', 'example']);
        [$manifest, $sources] = $this->factory->initial($input);
        $sources->{$manifest->entry} = self::TWIG;

        return $this->writer->create($input, null, $manifest, $sources)->getItem();
    }
}
