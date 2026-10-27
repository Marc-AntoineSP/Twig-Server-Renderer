<?php

namespace App\Controller;

use Styleguide\Contracts\CatalogueException;
use App\Entity\Theme;
use App\Entity\ThemeRelease;
use Styleguide\Contracts\CanonicalJson;
use App\Theme\ThemeWriter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Yaml;

#[IsGranted('ROLE_READER')]
final class ThemeExportController extends AbstractController
{
    #[Route('/themes/{id}/export/{file}', name: 'theme_export', methods: ['GET'], requirements: ['file' => 'app\.css|theme\.fragment\.css|typography\.css|preview\.css|theme\.json|theme\.yaml|bundle\.zip'])]
    public function draft(#[MapEntity(id: 'id')] Theme $theme, string $file, ThemeWriter $writer, CanonicalJson $json): Response
    {
        $document = $theme->getDocument();
        if ('theme.json' === $file) { return $this->attachment($json->encode($document, true)."\n", $file, 'application/json'); }
        if ('theme.yaml' === $file) {
            return $this->attachment(Yaml::dump(json_decode($json->encode($document), true), 8, 2), $file, 'application/yaml');
        }

        return $this->output($document, $writer->outputs($document), $file);
    }

    #[Route('/theme-releases/{id}/export/{file}', name: 'theme_release_export', methods: ['GET'], requirements: ['file' => 'app\.css|theme\.fragment\.css|typography\.css|preview\.css|theme\.json|bundle\.zip'])]
    public function release(#[MapEntity(id: 'id')] ThemeRelease $release, string $file): Response
    {
        return $this->output($release->getDocument(), $release->getFiles(), $file);
    }

    private function output(\stdClass $document, array $files, string $file): Response
    {
        if ('bundle.zip' !== $file) {
            if (!isset($files[$file])) { throw new CatalogueException(404, 'Theme output not found.'); }

            return $this->attachment($files[$file], $file, 'theme.json' === $file ? 'application/json' : 'text/css');
        }
        $path = tempnam(sys_get_temp_dir(), 'studio-theme-');
        $zip = new \ZipArchive();
        try {
            if (true !== $zip->open($path, \ZipArchive::OVERWRITE)) { throw new \RuntimeException('Cannot create theme archive.'); }
            $prefix = 'assets/styles/catalogue/'.$document->name.'/';
            foreach ($files as $name => $contents) { $zip->addFromString('theme.json' === $name ? 'meta/theme.json' : $prefix.$name, $contents); }
            $zip->addFromString('README.md', "Theme output bundle for ".$document->name.".\napp.css, theme.fragment.css and typography.css are Tailwind SOURCE; compile them.\npreview.css is COMPILED standalone CSS for the sample.\nPlace sources under ".$prefix.". Merge a fragment/typography into an existing Tailwind entry, once.\nImport meta/theme.json to round-trip the exact tokens. No font files or remote font dependency.\nThese tokens are not assigned a distribution licence by the studio.\n");
            $zip->close();
            $bytes = file_get_contents($path);
        } finally { unlink($path); }

        return $this->attachment($bytes, str_replace('/', '-', $document->name).'.zip', 'application/zip');
    }

    private function attachment(string $body, string $name, string $type): Response
    {
        return new Response($body, headers: ['Content-Type' => $type, 'Content-Disposition' => 'attachment; filename="'.$name.'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
