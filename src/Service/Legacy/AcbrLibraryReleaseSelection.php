<?php

declare(strict_types=1);

namespace App\Service\Legacy;

final readonly class AcbrLibraryReleaseSelection
{
    public function __construct(
        public int $id,
        public string $module,
        public string $environment,
        public string $profile,
        public string $release,
        public string $path,
        public string $sha256,
    ) {
    }
}
