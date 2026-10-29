<?php

namespace App\Controller;

use App\Appearance\AppearanceManager;
use App\Appearance\GuestAppearance;
use App\Appearance\ThemeFamilies;
use App\Entity\AppearanceSnapshot;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AppearanceController extends AbstractController
{
    #[Route('/appearance/reset', name: 'appearance_reset', methods: ['POST'])]
    #[IsCsrfTokenValid('appearance-reset', tokenKey: '_appearance_token')]
    public function reset(Request $request, AppearanceManager $manager, GuestAppearance $guest): Response
    {
        $state = $manager->prepare(['kind' => 'default'], $this->account(), $guest->mode($request));
        $manager->persist($state, $this->account(), $state['revision']);
        $this->addFlash('success', 'Default appearance restored.');
        $response = $this->redirectToRoute($this->account() ? 'studio_home' : 'studio_login', status: 303);
        $response->headers->set('Cache-Control', 'private, no-store');
        if (!$this->account()) $response->headers->setCookie($guest->cookie('system', $request));

        return $response;
    }

    #[Route('/api/v1/appearance', name: 'appearance_read', methods: ['GET'], format: 'json')]
    public function read(Request $request, AppearanceManager $manager, GuestAppearance $guest): JsonResponse
    {
        return $this->jsonPrivate($manager->read($this->account(), $guest->mode($request)));
    }

    #[Route('/api/v1/appearance/families', name: 'appearance_families', methods: ['GET'], format: 'json')]
    public function families(ThemeFamilies $families): JsonResponse
    {
        return $this->jsonPrivate(['families' => $families->choices(defaultOnly: !$this->account())]);
    }

    #[Route('/api/v1/appearance/prepare', name: 'appearance_prepare', methods: ['POST'], format: 'json')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function prepare(Request $request, AppearanceManager $manager, GuestAppearance $guest): JsonResponse
    {
        $data = $this->body($request, ['kind', 'clientId', 'sequence', 'familyId', 'themeId', 'themeRevision', 'mode', 'snapshots']); [$client, $sequence] = $this->operation($data);
        $operations = $request->getSession()->get('appearance_operations', []);
        if (isset($operations[$client]) && $sequence < $operations[$client]['sequence']) throw new CatalogueException(409, 'This appearance request is obsolete.', [], 'appearance_obsolete');
        $state = $manager->prepare($data, $this->account(), $guest->mode($request));
        $token = bin2hex(random_bytes(24));
        unset($operations[$client]);
        $operations[$client] = ['sequence' => $sequence, 'token' => $token, 'state' => $state, 'expires' => time() + 300, 'scope' => $this->account()?->getId() ?? 'guest'];
        $request->getSession()->set('appearance_operations', array_slice($operations, -8, null, true));
        return $this->jsonPrivate(['selection' => $state, 'token' => $token]);
    }

    #[Route('/api/v1/appearance/commit', name: 'appearance_commit', methods: ['POST'], format: 'json')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function commit(Request $request, AppearanceManager $manager, GuestAppearance $guest): JsonResponse
    {
        $data = $this->body($request, ['clientId', 'sequence', 'token', 'expectedRevision']); [$client, $sequence] = $this->operation($data);
        $operation = $request->getSession()->get('appearance_operations', [])[$client] ?? null;
        if (!$operation || $operation['sequence'] !== $sequence || !is_string($data['token'] ?? null) || !hash_equals($operation['token'], $data['token']) || $operation['expires'] < time() || $operation['scope'] !== ($this->account()?->getId() ?? 'guest')) throw new CatalogueException(409, 'Prepare the current selection again before applying it.', [], 'appearance_obsolete');
        if (!is_int($data['expectedRevision'] ?? null) || $data['expectedRevision'] < 0) throw new CatalogueException(422, 'Appearance revision is required.');
        $state = $manager->persist($operation['state'], $this->account(), $data['expectedRevision']);
        $response = $this->jsonPrivate($state);
        if (!$this->account()) $response->headers->setCookie($guest->cookie($state['mode'], $request));
        return $response;
    }

    #[Route('/api/v1/appearance/trial', name: 'appearance_trial', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function trial(Request $request, AppearanceManager $manager): JsonResponse
    {
        $data = $this->body($request, ['document']);
        $document = json_decode($request->getContent(), false, 24, JSON_THROW_ON_ERROR)->document ?? null;
        if (!$document instanceof \stdClass) throw new CatalogueException(422, 'A validated theme document is required for a trial.');
        return $this->jsonPrivate($manager->trial($document, $this->account()));
    }

    #[Route('/appearance/assets/{id}-{hash}.css', name: 'appearance_stylesheet', methods: ['GET'], requirements: ['id' => '[a-f0-9]{64}', 'hash' => '[a-f0-9]{64}'])]
    public function stylesheet(string $id, string $hash, Request $request, EntityManagerInterface $em): Response
    {
        $snapshot = $em->find(AppearanceSnapshot::class, $id);
        if (!$snapshot || !hash_equals($snapshot->getCssHash(), $hash)) throw $this->createNotFoundException('Appearance stylesheet is unavailable.');
        if ('default' !== $snapshot->getKind() && !$this->isGranted('ROLE_READER')) return new Response('', 403, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        if ('trial' === $snapshot->getKind() && ($snapshot->getOwner()?->getId() !== $this->account()?->getId() || $snapshot->getExpiresAt() < new \DateTimeImmutable())) throw $this->createNotFoundException('Trial stylesheet is unavailable.');
        if ('saved' === $snapshot->getKind() && !$snapshot->getTheme()) throw $this->createNotFoundException('Source theme is unavailable.');
        $response = new Response($snapshot->getCss(), headers: ['Content-Type' => 'text/css; charset=UTF-8', 'Cache-Control' => 'trial' === $snapshot->getKind() ? 'private, no-store' : 'private, max-age=31536000, immutable', 'X-Content-Type-Options' => 'nosniff', 'X-Content-SHA256' => $hash]);
        $response->setEtag($hash); $response->isNotModified($request);
        return $response;
    }

    private function account(): ?User { $user = $this->getUser(); return $user instanceof User ? $user : null; }
    private function jsonPrivate(array $data): JsonResponse { return new JsonResponse($data, headers: ['Cache-Control' => 'private, no-store']); }
    private function body(Request $request, array $allowed): array
    {
        if (strlen($request->getContent()) > 262144) throw new CatalogueException(413, 'Appearance input exceeds 256 KiB.');
        try { $data = json_decode($request->getContent(), true, 24, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new CatalogueException(400, 'Invalid appearance JSON.'); }
        if (!is_array($data) || array_is_list($data)) throw new CatalogueException(400, 'Appearance input must be an object.');
        if (array_diff(array_keys($data), $allowed)) throw new CatalogueException(422, 'Appearance input contains unsupported fields. Only validated theme tokens and selection IDs are accepted.');
        return $data;
    }
    private function operation(array $data): array
    {
        if (!is_string($data['clientId'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $data['clientId']) || !is_int($data['sequence'] ?? null) || $data['sequence'] < 1 || $data['sequence'] > 2147483647) throw new CatalogueException(422, 'A bounded appearance client and sequence are required.');
        return [$data['clientId'], $data['sequence']];
    }
}
