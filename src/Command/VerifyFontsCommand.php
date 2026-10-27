<?php

namespace App\Command;

use App\Font\FontLibrary;
use Styleguide\Contracts\SourceBytes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'styleguide:fonts:verify', description: 'Verify registered persistent font and licence bytes without network access.')]
final class VerifyFontsCommand extends Command
{
    public function __construct(private readonly FontLibrary $library) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $families = $this->library->catalogue(); $faces = 0; $bytes = 0; $errors = []; $licenses = [];
        foreach ($families as $family) {
            try {
                foreach ($family['faces'] as $face) {
                    $raw = $this->library->bytes($face['sha256']); SourceBytes::validateFont($raw);
                    if (strlen($raw) !== $face['bytes']) throw new \RuntimeException('Font byte count differs.');
                    ++$faces; $bytes += strlen($raw);
                }
                $text = $this->library->bytes($family['licenseSha256'], 'txt'); $licenses[$family['licenseSha256']] = strlen($text);
                foreach ($family['glyphCoverage'] as $coverage) if (!$coverage['verified']) throw new \RuntimeException('Missing acquisition glyph coverage evidence.');
            } catch (\Throwable $error) { $errors[$family['id']] = $error->getMessage(); }
        }
        $output->writeln(json_encode(['families' => count($families), 'faces' => $faces, 'fontBytes' => $bytes, 'uniqueLicenseFiles' => count($licenses), 'licenseBytes' => array_sum($licenses), 'errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $errors ? Command::FAILURE : Command::SUCCESS;
    }
}
