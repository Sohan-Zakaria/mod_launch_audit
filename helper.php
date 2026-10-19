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

use YourVendor\Module\LaunchAudit\Administrator\Helper\LaunchAuditHelper;

// Ensure the namespaced helper class is loaded
require_once __DIR__ . '/src/Helper/LaunchAuditHelper.php';

/**
 * Legacy and com_ajax bridge helper for mod_launch_audit.
 */
class ModLaunchAuditHelper
{
    /**
     * Proxies AJAX fixConfig request to the namespaced LaunchAuditHelper.
     *
     * @return  array<string, mixed>
     */
    public static function fixConfigAjax(): array
    {
        return LaunchAuditHelper::fixConfigAjax();
    }
}

// Aliases for com_ajax module string variations
if (!class_exists('ModLaunch_auditHelper', false)) {
    class ModLaunch_auditHelper extends ModLaunchAuditHelper {}
}

if (!class_exists('modLaunch_auditHelper', false)) {
    class modLaunch_auditHelper extends ModLaunchAuditHelper {}
}

if (!class_exists('modLaunchAuditHelper', false)) {
    class modLaunchAuditHelper extends ModLaunchAuditHelper {}
}
