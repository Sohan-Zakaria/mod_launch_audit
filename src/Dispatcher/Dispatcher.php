<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  mod_launch_audit
 *
 * @copyright   (C) 2026 Your Name or Company. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace YourVendor\Module\LaunchAudit\Administrator\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

/**
 * Module dispatcher for mod_launch_audit.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the data to be passed to the layout file.
     *
     * @return  array<string, mixed>
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        $this->getApplication()->getDocument()->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('mod_launch_audit');

        $helper = $this->getHelperFactory()->getHelper('LaunchAuditHelper');
        $data['auditData'] = $helper->getAuditResults($data['params'], $this->getApplication());

        return $data;
    }
}
