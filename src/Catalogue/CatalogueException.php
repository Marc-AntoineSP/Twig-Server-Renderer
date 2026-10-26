<?php

namespace App\Catalogue;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class CatalogueException extends HttpException
{
    public function __construct(
        int $status,
        string $message,
        public readonly array $fieldErrors = [],
        public readonly string $errorCode = 'invalid_catalogue_data',
    ) {
        parent::__construct($status, $message);
    }
}
