<?php

namespace App\Controller;

use App\Testing\BrowserRunGuard;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;

final class BrowserRunController
{
    #[Route('/health/browser-run', methods: ['GET'])]
    public function __invoke(Request $request, BrowserRunGuard $guard): JsonResponse
    {
        try {
            $owner = $guard->verify();
            $challenge = $request->query->getString('challenge');
            if (!preg_match('/^[a-f0-9]{64}$/D', $challenge)) throw new \RuntimeException();
        } catch (\Throwable) {
            return new JsonResponse(['status' => 'unavailable'], 404, ['Cache-Control' => 'no-store']);
        }
        return new JsonResponse(['runId' => $owner['runId'], 'proof' => hash_hmac('sha256', $challenge, $owner['secret'])], 200, ['Cache-Control' => 'no-store']);
    }
}
