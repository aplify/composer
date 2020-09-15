<?php

namespace Aplify\Composer\Merge;

use Aplify\Composer\Library;
use Aplify\Composer\Logger;
use Composer\Composer;
use Composer\EventDispatcher\Event as BaseEvent;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event as ScriptEvent;
use Composer\Script\ScriptEvents;

class MergePlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Name of the composer 1.1 init event.
     */
    const COMPAT_PLUGINEVENTS_INIT = 'init';

    /**
     * Priority that plugin uses to register callbacks.
     */
    const CALLBACK_PRIORITY = 50000;

    /**
     * @var Composer $composer
     */
    protected $composer;

    /**
     * @var Library $state
     */
    protected $state;

    /**
     * @var Logger $logger
     */
    protected $logger;

    /**
     * Files that have already been fully processed
     *
     * @var string[] $loaded
     */
    protected $loaded = array();

    /**
     * Files that have already been partially processed
     *
     * @var string[] $loadedNoDev
     */
    protected $loadedNoDev = array();

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->logger = new Logger('library', $io);
        $this->state = new Library($this->composer);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            // Use our own constant to make this event optional. Once
            // composer-1.1 is required, this can use PluginEvents::INIT
            // instead.
            self::COMPAT_PLUGINEVENTS_INIT =>
                array('onInit', self::CALLBACK_PRIORITY),
            ScriptEvents::PRE_AUTOLOAD_DUMP =>
                array('onInstallUpdateOrDump', self::CALLBACK_PRIORITY),
            ScriptEvents::PRE_INSTALL_CMD =>
                array('onInstallUpdateOrDump', self::CALLBACK_PRIORITY),
            ScriptEvents::PRE_UPDATE_CMD =>
                array('onInstallUpdateOrDump', self::CALLBACK_PRIORITY),
        );
    }

    /**
     * Handle an event callback for initialization.
     *
     * @param BaseEvent $event
     */
    public function onInit(BaseEvent $event)
    {
        $this->state->scan();
        $this->mergeFiles($this->state->getPackages());
    }

    /**
     * Handle an event callback for an install, update or dump command by
     * checking for "merge-plugin" in the "extra" data and merging package
     * contents if found.
     *
     * @param ScriptEvent $event
     */
    public function onInstallUpdateOrDump(ScriptEvent $event)
    {
        $this->state->scan();
        $this->state->setDevMode($event->isDevMode());
        $this->mergeFiles($this->state->getPackages());
    }

    /**
     * Find configuration files matching the configured glob patterns and
     * merge their contents with the master package.
     *
     * @param array $packages
     */
    protected function mergeFiles(array $packages)
    {
        $root = $this->composer->getPackage();

        foreach ($packages as $item) {

            $composer = $item['composer'];
            if ($item['active'] || $item['core']  && file_exists($composer)){
                $this->mergeFile($root, $composer);
            }
        }
    }

    /**
     * Read a JSON file and merge its contents
     *
     * @param RootPackageInterface $root
     * @param string $path
     */
    protected function mergeFile(RootPackageInterface $root, $path)
    {
        if (isset($this->loaded[$path]) ||
            (isset($this->loadedNoDev[$path]) && !$this->state->isDevMode())
        ) {
            $this->logger->debug(
                "Already merged <comment>$path</comment> completely"
            );
            return;
        }

        $package = new ExtraPackage($path, $this->composer, $this->logger);

        if (isset($this->loadedNoDev[$path])) {
            $this->logger->info(
                "Loading -dev sections of <comment>{$path}</comment>..."
            );
            $package->mergeDevInto($root);
        } else {
            $this->logger->info("Loading <comment>{$path}</comment>...");
            $package->mergeInto($root);
        }

        if ($this->state->isDevMode()) {
            $this->loaded[$path] = true;
        } else {
            $this->loadedNoDev[$path] = true;
        }

    }
}
