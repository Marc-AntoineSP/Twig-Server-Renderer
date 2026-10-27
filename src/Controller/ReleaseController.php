<?php
namespace App\Controller;
use App\Entity\CatalogueItem;
use App\Packaging\PublishInput;
use App\Packaging\ReleasePublisher;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
#[IsGranted('ROLE_EDITOR')]
final class ReleaseController extends AbstractController
{
    #[Route('/api/v1/items/{id}/releases', name: 'release_publish_api', methods: ['POST'], format: 'json')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function api(#[MapEntity(id: 'id')] CatalogueItem $item, #[MapRequestPayload(acceptFormat: 'json', serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] PublishInput $input, ReleasePublisher $publisher): JsonResponse
    {
        $release = $publisher->publish($item, $input);
        return new JsonResponse(['id' => $release->getId(), 'version' => $release->getVersion(), 'archiveHash' => $release->getArchiveHash()], 201);
    }
    #[Route('/catalogue/{id}/publish', name: 'release_publish', methods: ['POST'])]
    #[IsCsrfTokenValid('publish', tokenKey: '_token')]
    public function form(Request $request, #[MapEntity(id: 'id')] CatalogueItem $item, ReleasePublisher $publisher): Response
    {
        $publisher->publish($item, new PublishInput($request->request->getString('version'), $request->request->getInt('revision')));
        $this->addFlash('success', 'Release published immutably.');
        return $this->redirectToRoute('catalogue_show', ['id' => $item->getId()], 303);
    }
}
