<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\Commands;

use Piwik\Access;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\LoginLdap\Settings as LoginLdapSettings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

/**
 * Command to list the LdapLogin plugins settings
 */
class SettingsList extends ConsoleCommand
{
	/**
	 * @var LoginLdapSettings
	 */
	private $loginLdapSettings;
	
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->loginLdapSettings = Access::doAsSuperUser( function() { return new LoginLdapSettings();});
    }

    protected function configure()
    {
        $this->setName('loginldap:settings-list');
        $this->setDescription('List the settings of the plugin.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(array('name','type','value'));
        
        foreach( $this->loginLdapSettings->getSettings() as $setting) {
            $table->addRow( array( $setting->getName(), $setting->type, self::settingToOutputString( $setting)));
        }
        $table->render();

        return 0;
    }
    
    public static function settingToOutputString( $setting) {
    	switch ($setting->type) {
    		case LoginLdapSettings::TYPE_BOOL:
    			return $setting->getValue() ? "true" : "false";
    		case LoginLdapSettings::TYPE_ARRAY:
    			return join(",", $setting->getValue());
    		case LoginLdapSettings::TYPE_STRING:
    			return $setting->getValue();
    		default:
    			return serialize($setting->getValue());
    	}
    }
}