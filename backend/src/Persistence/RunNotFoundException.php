<?php

declare(strict_types=1);

namespace App\Persistence;

final class RunNotFoundException extends \RuntimeException
{
    public function __construct(string $runId)
    {
        parent::__construct(sprintf('No run found for id "%s".', $runId));
    }
}
