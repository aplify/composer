<?php

namespace Aplify\Composer;

use Composer\Composer;
use Symfony\Component\Finder\Finder;

class Library
{
    /**
     * @var Composer $composer
     */
    protected $composer;

    /**
     * @var string $path
     */
    protected $path = 'common';

    /**
     * @var string $filename
     */
    protected $filename = 'apply.json';

    /**
     * $packages array.
     */
    protected $packages = [];

    /**
     * @var bool $devMode
     */
    protected $devMode = false;

    /**
     * @param Composer $composer
     */
    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
        $this->loadSettings();
    }

    /**
     * Load plugin settings
     */
    public function loadSettings()
    {
        $extra = $this->composer->getPackage()->getExtra();
        $this->path = $extra['aplify']['library']['path'] ?? $this->path;
        $this->filename = $extra['aplify']['library']['filename'] ?? $this->filename;

        if (!is_dir($this->path)){
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * Gets the filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Gets the path library
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Gets the Packages library
     *
     * @return array
     */
    public function getPackages()
    {
        return $this->packages;
    }

    /**
     * Set the devMode flag
     *
     * @param bool $flag
     */
    public function setDevMode($flag)
    {
        $this->devMode = (bool)$flag;
    }

    /**
     * Should devMode settings be processed?
     *
     * @return bool
     */
    public function isDevMode()
    {
        return $this->devMode;
    }

    /**
     * Scan folder and generate item.
     */
    public function scan()
    {
        $files = Finder::create()->files()
            ->in($this->getPath())
            ->name($this->getFilename());

        foreach ($files as $file) {
            $this->packages[] = $this->generatePackage($file);
        }

        return $this;
    }

    /**
     * Generate structure package item.
     * @param $file
     * @return mixed
     */
    public function generatePackage($file)
    {
        $item = json_decode($file->getContents(), true);
        $item['composer'] = $file->getPath().'/composer.json';
        return $item;

    }
}
