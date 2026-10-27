<?php

namespace App\Twig;

use App\Appearance\AppearanceManager;
use App\Appearance\GuestAppearance;
use App\Appearance\ThemeFamilies;
use App\Entity\Theme;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

final class AppearanceRuntime implements RuntimeExtensionInterface
{
    public function __construct(private readonly AppearanceManager $manager, private readonly ThemeFamilies $families, private readonly GuestAppearance $guest, private readonly Security $security, private readonly RequestStack $requests) {}
    public function family(Theme $theme): array { return $this->families->describe($this->families->forTheme($theme)); }
    public function appearance(): array
    {
        $request = $this->requests->getCurrentRequest();
        if ($request->attributes->has('_application_appearance')) return $request->attributes->get('_application_appearance');
        $user = $this->security->getUser();
        $state = $this->manager->read($user instanceof User ? $user : null, $this->guest->mode($request));
        $request->attributes->set('_application_appearance', $state);
        return $state;
    }
    public function choices(): array
    {
        return array_values(array_filter($this->families->choices(), fn ($family) => $family['default'] || $this->security->isGranted('ROLE_READER')));
    }
}
