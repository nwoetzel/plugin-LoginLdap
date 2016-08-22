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
use Piwik\Plugins\LoginLdap\Commands\SettingsList;
use Piwik\Plugins\LoginLdap\Settings as LoginLdapSettings;
use Piwik\Settings\Setting;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to modify (clear, add-to, remove-from, change) the LdapLogin plugins settings
 */
class SettingsModify extends ConsoleCommand
{
	const ADD    = "add";
	const SET    = "set";
	const REMOVE = "remove";
	const RESET  = "reset";
	
	public static $modes = array(
			self::ADD    => "add to array; prepend to string; mathematical add for float or int", 
			self::SET    => "overwrite with given value",
			self::REMOVE => "for array, remove given value; for string remove substring; mathematical sub for float or int",
			self::RESET  => "reset value to default"
	);
	
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
        $this->setName('loginldap:settings-modify');
        $this->setDescription('Modify the settings of the plugin.');
        $this->addArgument('setting', InputArgument::REQUIRED, "The name of the setting to modify<br>");
        $this->addArgument('mode', InputArgument::REQUIRED, "The modus, one of: " . join(", ", static::$modes));
        $this->addArgument('value', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, "The values to add, set, remove");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$mode         = $input->getArgument('mode');
    	$setting_name = $input->getArgument('setting');
    	$values       = $input->getArgument('value');

        $setting = $this->loginLdapSettings->getSetting( $setting_name);
        
        if( is_null( $setting) ) {
        	$output->writeln("<error>No setting with name: " . $setting_name . "</error>");
        	
        	return 1;
        }

        $error = 0;
        switch ($mode) {
        	case self::ADD:
        	case self::SET:
        	case self::REMOVE:
        	case self::RESET:
        		$error = call_user_func_array( array( 'self', $mode), array( &$setting, $output, $values));
        		break;
        	default:
        		$output->writeln("<error>No valid mode: " . $mode . "</error>");
        		$error = 1;
        		break;
        }
        
        if ($error) {
        	$output->writeln("<error>error occured, setting was not changed</error>");
        	return 1;
        }
        // persist
        $this->loginLdapSettings->save();
        $output->writeln("<info>Setting \"". $setting->getName() ."\" changed to: " . SettingsList::settingToOutputString($setting) . "</info>");
        
        return 0;
    }

    static function add( Setting &$setting, OutputInterface $output, array $values){
    	$value = $setting->getValue();
    	switch ($setting->type) {
    		case LoginLdapSettings::TYPE_FLOAT:
				$value += array_sum( array_map( 'floatval', $values));
				break;
 			case LoginLdapSettings::TYPE_INT:
 				$value += array_sum( array_map( 'intval', $values));
				break;
 			case LoginLdapSettings::TYPE_STRING:
 				$value .= join("", $values);
				break;
 			case LoginLdapSettings::TYPE_ARRAY:
 				$value = array_merge($value, $values);
 				break;
 			default:
 				$output->writeln("<error>unable to add for type: " . $setting->type . "</error>");
 				return 1;
    	}
    	$setting->setValue($value);
    	return 0;
    }
    
    static function set( Setting &$setting, OutputInterface $output, array $values){
    	if( empty($values)) {
    		$output->writeln("<error>unable to set if no value is given</error>");
    		return 1;
    	}
    	
    	$value = $setting->getValue();
    	switch ($setting->type) {
    		case LoginLdapSettings::TYPE_FLOAT:
   				$value = floatval( $values[0]);
    			break;
    		case LoginLdapSettings::TYPE_INT:
   				$value = intval( $values[0]);
    			break;
    		case LoginLdapSettings::TYPE_STRING:
    			$value = join( "", $values);
    			break;
    		case LoginLdapSettings::TYPE_ARRAY:
    			$value = $values;
    			break;
    		case LoginLdapSettings::TYPE_BOOL:
    			if( strtolower( $values[0]) == "true") {
    				$value = true;
    			} elseif ( strtolower( $values[0]) == "false") {
    				$value = false;
    			} else {
    				$value = intval($values[0]);
    			}
    			break;
    		default:
    			$output->writeln("<error>unable to set for type: " . $setting->type . "</error>");
    			return 1;
    	}
    	$setting->setValue($value);
    	return 0;
    }
    
    static function remove( Setting &$setting, OutputInterface $output, array $values){
    	$value = $setting->getValue();
    	switch ($setting->type) {
    		case LoginLdapSettings::TYPE_FLOAT:
    			$value -= array_sum( array_map( 'floatval', $values));
    			break;
    		case LoginLdapSettings::TYPE_INT:
    			$value -= array_sum( array_map( 'intval', $values));
    			break;
    		case LoginLdapSettings::TYPE_STRING:
    			$value = str_replace($values, "", $value);
    			break;
    		case LoginLdapSettings::TYPE_ARRAY:
    			$value = array_diff($value, $values);
    			break;
    		default:
    			$output->writeln("<error>unable to remove for type: " . $setting->type . "</error>");
    			return 1;
    	}
    	$setting->setValue($value);
    	return 0;
    }

    static function reset( Setting &$setting, OutputInterface $output, array $values){
        $setting->setValue( $setting->defaultValue);
    	return 0;
    }
    
}
