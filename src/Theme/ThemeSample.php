<?php

namespace App\Theme;

use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Catalogue\SourceFactory;
use Styleguide\Contracts\PackageValidator;
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
    <div class="mt-6 grid gap-6 md:grid-cols-[12rem_1fr]">
        <aside class="rounded-box bg-base-200 p-3" aria-label="Showcase sidebar"><ul class="menu"><li><a class="menu-active" href="#overview">Overview</a></li><li><a href="#settings">Settings</a></li><li><a href="#activity">Activity</a></li></ul></aside>
        <div class="min-w-0">
            <nav class="navbar rounded-box bg-base-200" aria-label="Showcase navigation"><span class="font-heading text-lg">Studio showcase</span><a class="btn btn-ghost ml-auto" href="#overview">Home</a></nav>
            <h2 id="overview" class="mt-5 font-heading text-xl">English &amp; Français</h2>
            <p class="my-3">The quick brown fox jumps over the lazy dog. Élève, cœur, Œuvre, ça coûte 12 € — « Une interface lisible ! »</p>
            <p class="my-2"><em>Real italic when available.</em> <strong>Bold body text.</strong> 0123456789</p>
            <div class="my-4 flex flex-wrap gap-2"><button class="btn btn-primary" type="button">Continue</button><button class="btn btn-outline" type="button">Outline</button><button class="btn" disabled>Disabled</button><span class="badge badge-primary">Selected</span><span class="badge badge-success">Ready</span></div>
            <label class="form-control block">Email address <input class="input input-bordered my-2 w-full" type="email" value="bonjour@example.test"></label>
            <label class="block">Country <select class="select select-bordered my-2 w-full"><option>France</option><option>United Kingdom</option></select></label>
            <label class="block">Invalid field <input class="input input-error my-2 w-full" aria-invalid="true" aria-describedby="theme-field-error" value="Invalid"></label><p id="theme-field-error" class="text-error">Enter a valid address.</p>
            <div class="card my-5 border border-base-300 bg-base-200"><div class="card-body"><h3 class="card-title font-heading">A reusable card</h3><p>Shared content and theme geometry.</p><div class="card-actions"><button class="btn btn-primary" type="button">Card action</button></div></div></div>
            <div role="tablist" class="tabs tabs-border"><input type="radio" name="showcase-tabs" class="tab" role="tab" aria-label="Overview tab" checked><div class="tab-content p-3">Overview content</div><input type="radio" name="showcase-tabs" class="tab" role="tab" aria-label="Details tab"><div class="tab-content p-3">Details content</div></div>
            <table class="table my-4"><caption>Example catalogue</caption><thead><tr><th>Component</th><th>Status</th></tr></thead><tbody><tr><td>Button</td><td><span class="badge badge-success">Published</span></td></tr><tr><td>Card</td><td>Draft</td></tr></tbody></table>
            <details class="collapse collapse-arrow border border-base-300"><summary class="collapse-title">Accordion details</summary><div class="collapse-content">Keyboard and pointer interaction.</div></details>
            <label class="mt-4 block" for="showcase-progress">Progress</label><progress id="showcase-progress" class="progress progress-primary w-full" value="65" max="100">65%</progress>
            <pre class="my-4 overflow-auto rounded-box bg-neutral p-4 text-neutral-content"><code class="font-mono">const message = "Bonjour, world!"; // 12 €</code></pre>
            <button type="button" class="btn btn-outline my-3" commandfor="showcase-modal" command="show-modal">Open modal example</button><dialog id="showcase-modal" aria-label="Modal example" class="modal"><div class="modal-box border border-base-300"><h3 class="font-heading text-lg">Confirm your selection</h3><p class="my-3">A native modal surface using the same theme.</p><button type="button" class="btn btn-primary" commandfor="showcase-modal" command="close">Close modal</button></div></dialog>
        </div>
    </div>
</section>
TWIG;

    public function __construct(private CatalogueRepository $catalogue, private CatalogueWriter $writer, private SourceFactory $factory, private \Doctrine\ORM\EntityManagerInterface $em)
    {
    }

    public function item(): CatalogueItem
    {
        $name=null;
        for($i=1;$i<=100;$i++){$candidate='studio/theme-showcase-v2'.($i===1?'':'-'.$i);$existing=$this->catalogue->named($candidate);
            if($existing&&$existing->getKind()!=='theme')return $existing;
            if($existing||$this->em->getRepository(\App\Entity\Theme::class)->findOneBy(['name'=>$candidate]))continue;$name=$candidate;break;
        }
        if(null===$name)throw new \Styleguide\Contracts\CatalogueException(409,'No free theme sample name; choose a catalogue component.');
        $input = new CreateItemInput($name, 'component', 'Theme workshop sample', 'Palette, typography and geometry reference.', ['theme', 'example']);
        [$manifest, $sources] = $this->factory->initial($input);
        $sources->{$manifest->entry} = self::TWIG;

        return $this->writer->create($input, null, $manifest, $sources)->getItem();
    }
}
