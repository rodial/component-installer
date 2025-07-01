<?php

/*
 * This file is part of Component Installer.
 *
 * (c) Rob Loach (http://robloach.net)
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace ComponentInstaller;

use Composer\Installer\LibraryInstaller;
use Composer\Script\Event;
use Composer\Package\PackageInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

/**
 * Component Installer for Composer.
 */
class Installer extends LibraryInstaller implements EventSubscriberInterface
{
    private static $defaultProcesses = array(
        // Copy the assets to the Components directory.
        "ComponentInstaller\\Process\\CopyProcess",
        // Build the require.js file.
        "ComponentInstaller\\Process\\RequireJsProcess",
        // Build the require.css file.
        "ComponentInstaller\\Process\\RequireCssProcess",
        // Compile the require-built.js file.
        "ComponentInstaller\\Process\\BuildJsProcess",
    );

    /**
     * The location where Components are to be installed.
     */
    protected $componentDir;

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump' => 'postAutoloadDump',
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Components are supported by all packages. This checks wheteher or not the
     * entire package is a "component", as well as injects the script to act
     * on components embedded in packages that are not just "component" types.
     */
    public function supports($packageType)
    {
        // Script injection logic moved to getSubscribedEvents().
        // supports() should only declare supported package types.
        return $packageType == 'component';
    }

    /**
     * Gets the destination Component directory.
     *
     * @param PackageInterface $package
     * @return string
     *   The path to where the final Component should be installed.
     */
    public function getComponentPath(PackageInterface $package)
    {
        // Parse the pretty name for the vendor and package name.
        $name = $prettyName = $package->getPrettyName();
        if (strpos($prettyName, '/') !== false) {
            list($vendor, $name) = explode('/', $prettyName);
            unset($vendor);
        }

        // First look for an override in root package's extra, then try the package's extra
        $rootPackage = $this->composer->getPackage();
        $rootExtras = $rootPackage ? $rootPackage->getExtra() : array();
        $customComponents = isset($rootExtras['component']) ? $rootExtras['component'] : array();

        if (isset($customComponents[$prettyName]) && is_array($customComponents[$prettyName])) {
            $component = $customComponents[$prettyName];
        }
        else {
            $extra = $package->getExtra();
            $component = isset($extra['component']) ? $extra['component'] : array();
        }

        // Allow the component to define its own name.
        if (isset($component['name'])) {
            $name = $component['name'];
        }

        // Find where the package should be located.
        return $this->getComponentDir() . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Initialize the Component directory, as well as the vendor directory.
     */
    protected function initializeVendorDir()
    {
        $this->componentDir = $this->getComponentDir();
        $this->filesystem->ensureDirectoryExists($this->componentDir);
        parent::initializeVendorDir();
    }

    /**
     * Retrieves the Installer's provided component directory.
     */
    public function getComponentDir()
    {
        $config = $this->composer->getConfig();
        return $config->has('component-dir') ? $config->get('component-dir') : 'components';
    }

    /**
     * Remove both the installed code and files from the Component directory.
     *
     * @param PackageInterface $package
     */
    public function removeCode(PackageInterface $package)
    {
        $this->removeComponent($package);
        parent::removeCode($package);
    }

    /**
     * Remove a Component's files from the Component directory.
     *
     * @param PackageInterface $package
     * @return bool
     */
    public function removeComponent(PackageInterface $package)
    {
        $path = $this->getComponentPath($package);
        return $this->filesystem->remove($path);
    }

    /**
     * Before installing the Component, be sure its destination is clear first.
     *
     * @param PackageInterface $package
     */
    public function installCode(PackageInterface $package)
    {
        $this->removeComponent($package);
        parent::installCode($package);
    }

    /**
     * Script callback; Acted on after the autoloader is dumped.
     *
     * @param Event $event
     */
    public static function postAutoloadDump(Event $event)
    {
        // Retrieve basic information about the environment and present a
        // message to the user.
        $composer = $event->getComposer();
        $config = $composer->getConfig();
        $io = $event->getIO();
        $io->write('<info>Compiling component files</info>');

        // Set up all the processes.
        $processes = $config->has('component-processes') ?
            $config->get('component-processes') :
            static::$defaultProcesses;

        // Initialize and execute each process in sequence.
        foreach ($processes as $process) {
            $options = array();

            if (is_array($process)) {
                $options = isset($process['options']) ? $process['options'] : array();
                $class = $process['class'];
            }
            else {
                $class = $process;
            }

            if(!class_exists($class)){
                $io->write("<warning>Process class '$class' not found, skipping this process</warning>");
                continue;
            }

            /** @var \ComponentInstaller\Process\Process $process */
            $process = new $class($composer, $io, $options);
            // When an error occurs during initialization, end the process.
            if (!$process->init()) {
                $io->write("<warning>An error occurred while initializing the '$class' process.</warning>");
                break;
            }
            $process->process();
        }
    }
}
