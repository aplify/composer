<?php

namespace Aplify\Composer\Installer;

use RuntimeException;
use Composer\Installer\LibraryInstaller;

class Installer extends LibraryInstaller
{
    /**
     * Decides if the installer supports the given type
     *
     * @param  string $packageType
     * @return bool
     */
    public function supports($packageType): bool
    {
        throw new RuntimeException('This method needs to be overridden.'); // @codeCoverageIgnore
    }

}
