<?php

namespace Aplify\Composer\Merge;

use Aplify\Composer\Logger;
use Composer\Composer;
use Composer\Json\JsonFile;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackage;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\RootPackageInterface;
use UnexpectedValueException;

/**
 * Processing for a composer.json file that will be merged into
 * a RootPackageInterface
 *
 */
class ExtraPackage
{
    /**
     * @var Composer $composer
     */
    protected $composer;

    /**
     * @var Logger $logger
     */
    protected $logger;

    /**
     * @var string $path
     */
    protected $path;

    /**
     * @var array $json
     */
    protected $json;

    /**
     * @var CompletePackage $package
     */
    protected $package;

    /**
     * @param string $path Path to composer.json file
     * @param Composer $composer
     * @param Logger $logger
     */
    public function __construct($path, Composer $composer, Logger $logger)
    {
        $this->path = $path;
        $this->composer = $composer;
        $this->logger = $logger;
        $this->json = $this->readPackageJson($path);
        $this->package = $this->loadPackage($this->json);
    }

    /**
     * Read the contents of a composer.json style file into an array.
     *
     * The package contents are fixed up to be usable to create a Package
     * object by providing dummy "name" and "version" values if they have not
     * been provided in the file. This is consistent with the default root
     * package loading behavior of Composer.
     *
     * @param string $path
     * @return array
     */
    protected function readPackageJson($path)
    {
        $file = new JsonFile($path);
        $json = $file->read();
        if (!isset($json['name'])) {
            $json['name'] = 'merge-plugin/' .
                strtr($path, DIRECTORY_SEPARATOR, '-');
        }
        if (!isset($json['version'])) {
            $json['version'] = '1.0.0';
        }
        return $json;
    }

    /**
     * @param array $json
     * @return CompletePackage
     */
    protected function loadPackage(array $json)
    {
        $loader = new ArrayLoader();
        $package = $loader->load($json);
        // @codeCoverageIgnoreStart
        if (!$package instanceof CompletePackage) {
            throw new UnexpectedValueException(
                'Expected instance of CompletePackage, got ' .
                get_class($package)
            );
        }
        // @codeCoverageIgnoreEnd
        return $package;
    }

    /**
     * Merge this package into a RootPackageInterface
     *
     * @param RootPackageInterface $root
     */
    public function mergeInto(RootPackageInterface $root)
    {
        $this->mergeRequires('require', $root);
    }

    /**
     * Merge just the dev portion into a RootPackageInterface
     *
     * @param RootPackageInterface $root
     */
    public function mergeDevInto(RootPackageInterface $root)
    {
        $this->mergeRequires('require-dev', $root);
    }

    /**
     * Merge require or require-dev into a RootPackageInterface
     *
     * @param string $type 'require' or 'require-dev'
     * @param RootPackageInterface $root
     */
    protected function mergeRequires($type, RootPackageInterface $root)
    {
        $linkType = BasePackage::$supportedLinkTypes[$type];
        $getter = 'get' . ucfirst($linkType['method']);
        $setter = 'set' . ucfirst($linkType['method']);

        $requires = $this->package->{$getter}();
        if (empty($requires)) {
            return;
        }

        $root->{$setter}($this->mergeOrDefer(
            $root->{$getter}(),
            $requires
        ));
    }

    /**
     * Merge two collections of package links and collect duplicates for
     * subsequent processing.
     *
     * @param array $origin Primary collection
     * @param array $merge Additional collection
     * @return array Merged collection
     */
    protected function mergeOrDefer( array $origin, array $merge)
    {
        foreach ($merge as $name => $link) {
            $origin[$name] = $link;
        }

        return $origin;
    }
}
