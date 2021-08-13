<?php
namespace Bookly\Backend\Components\Dialogs\Staff\Edit\Proxy;

use Bookly\Lib;

/**
 * Class Pro
 * @package Bookly\Backend\Components\Dialogs\Staff\Edit\Proxy
 *
 * @method static void   enqueueAssets() Enqueue assets for staff edit dialog.
 * @method static void   renderAdvancedTab() Render advanced tab.
 * @method static string renderArchivingComponents()
 * @method static string getAdvancedHtml( Lib\Entities\Staff $staff, array $tpl_data, bool $for_backend ) Render Advanced settings.
 */
abstract class Pro extends Lib\Base\Proxy
{

}