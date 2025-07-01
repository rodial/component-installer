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

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capable; // For Composer 2.2+

/**
 * Composer Plugin to install Components.
 *
 * Adds the ComponentInstaller Plugin to the Composer instance.
 *
 * @see \ComponentInstaller\Installer
 */
class ComponentInstallerPlugin implements PluginInterface, Capable
{
    protected $installer;

    /**
     * Called when the plugin is activated.
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->installer = new Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
        // Register the Installer as an event subscriber for post-autoload-dump
        $composer->getEventDispatcher()->addSubscriber($this->installer);
    }

    /**
     * Remove any hooks from Composer
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        if ($this->installer) {
            $composer->getInstallationManager()->removeInstaller($this->installer);
        }
    }

    /**
     * Uninstall the plugin
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
        // No specific cleanup logic beyond deactivation for now.
        // If there were global files to remove that are not tied to specific packages,
        // they would be removed here.
    }

    /**
     * Returns an array of capabilities this plugin provides.
     *
     * @return string[]
     */
    public function getCapabilities()
    {
        return [
            'Composer\Plugin\Capability\Installer' => 'ComponentInstaller\Installer',
        ];
    }
}