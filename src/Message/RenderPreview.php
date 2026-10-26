<?php

namespace App\Message;

final readonly class RenderPreview
{
    public function __construct(public string $jobId)
    {
    }
}
