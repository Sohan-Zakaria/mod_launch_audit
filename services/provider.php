<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  mod_launch_audit
 *
 * @copyright   (C) 2026 Your Name or Company. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new ModuleDispatcherFactory('\\YourVendor\\Module\\LaunchAudit'));
        $container->registerServiceProvider(new HelperFactory('\\YourVendor\\Module\\LaunchAudit\\Administrator\\Helper'));
        $container->registerServiceProvider(new Module());
    }
};
