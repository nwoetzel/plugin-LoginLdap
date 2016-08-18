<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LoginLdap;

use Exception;

use Piwik\Access;
use Piwik\Piwik;
use Piwik\Settings\SystemSetting;
use Piwik\Settings\UserSetting;
use Piwik\Plugins\SitesManager\API as APISitesManager;
use Piwik\Plugins\LoginLdap\Model\LdapUsers;

/**
 * Defines Settings for LoginLdap.
 *
 * Usage like this:
 * $settings = new Settings('LoginLdap');
 * $settings->superAccessGroups->getValue();
 */
class Settings extends \Piwik\Plugin\Settings
{
    /**
     * The LdapUsers instance to use when executing LDAP logic regarding LDAP users.
     * @var LdapUsers
     */
    private $ldapUsers;

    /** @var string[] */
    private $groups;

    /** @var SystemSetting */
    public $accessByLdapGroups;

    /** @var SystemSetting */
    public $superAccessGroups;

    /** @var SystemSetting[] */
    public $siteAdminGroups;

    /** @var SystemSetting[] */
    public $siteViewGroups;

    protected function init()
    {
        $this->ldapUsers = LdapUsers::makeConfigured();

        $this->setIntroduction('Configure Ldap groups with Super Access rights and for each site LDAP groups with admin or view access.');

        // System setting --> enable configuring access by LDAP groups
        $this->createAccessByLdapGroupSetting();

        // System setting --> multiselect of groups
        $this->createSuperAccessGroupsSetting();
        $this->createSiteAdminGroupsSettings();
        $this->createSiteViewGroupsSettings();
    }

    private function createAccessByLdapGroupSetting()
    {
        $this->accessByLdapGroups = new SystemSetting('accessByLdapGroups', 'Access can be configured by LDAP user groups');
        $this->accessByLdapGroups->type  = static::TYPE_BOOL;
        $this->accessByLdapGroups->uiControlType = static::CONTROL_CHECKBOX;
        $this->accessByLdapGroups->description   = 'If enabled, the group access settings will be used for LDAP users';
        $this->accessByLdapGroups->defaultValue  = false;

        $this->addSetting($this->accessByLdapGroups);
    }

    private function createSuperAccessGroupsSetting()
    {
        $this->superAccessGroups                  = new SystemSetting('superAccessGroups', 'Super User Acces by LDAP group');
        $this->groupSettingsDefaults($this->superAccessGroups);
        $this->superAccessGroups->description     = 'If the user is directly or recursivly member of any of the groups, super user access will be granted';

        $this->addSetting($this->superAccessGroups);
    }

    private function createSiteAdminGroupsSettings()
    {
        $this->siteAdminGroups = array();

        foreach ( self::getAllSites() as $id => $site)
        {
          $this->siteAdminGroups[$id] = new SystemSetting( $id . '_adminGroups', 'Admin access of LDAP groups to site: ' . $id . ' | ' . $site["name"] . ' | ' . $site["main_url"]);
          $adminGroup = &$this->siteAdminGroups[$id];
          $this->groupSettingsDefaults($adminGroup);
          $adminGroup->description     = 'If the user is directly or recursivly member of any of the groups, admin access will be granted to site: ' . $id;

          $this->addSetting($adminGroup);
        }
    }

    private function createSiteViewGroupsSettings()
    {
        $this->siteViewGroups = array();

        foreach ( self::getAllSites() as $id => $site)
        {
            $this->siteViewGroups[$id] = new SystemSetting( $id . '_viewGroups', 'View access of LDAP groups to site: ' . $id . ' | ' . $site["name"] . ' | ' . $site["main_url"]);
            $viewGroup = &$this->siteViewGroups[$id];
            $this->groupSettingsDefaults($viewGroup);
            $viewGroup->description     = 'If the user is directly or recursivly member of any of the groups, view access will be granted to site: ' . $id;

            $this->addSetting($viewGroup);
        }
    }

    private function groupSettingsDefaults( &$groupSetting)
    {
        $groupSetting->type            = static::TYPE_ARRAY;
        $groupSetting->uiControlType   = static::CONTROL_MULTI_SELECT;
        $groupSetting->availableValues = $this->getGroups();
        $groupSetting->defaultValue    = array();

        // an empty selection should not be null, but an empty array
        $groupSetting->transform       = function ($value,$setting) {
            if( $value == NULL) {
                $value = array();
            } else {
                settype($value, $setting->type);
            }
            return $value;
        };

        // the default validate function does not allow an empty array
        $groupSetting->validate = function ($value,$setting) {
            $errorMsg = Piwik::translate('CoreAdminHome_PluginSettingsValueNotAllowed',
                array($setting->title, 'LoginLdap'));

            // an empty array is also permitted
            if ($value == NULL) return;

            foreach ($value as $val) {
                if (!array_key_exists($val, $setting->availableValues)) {
                    throw new \Exception($errorMsg);
                }
            }
        };
    }

    private function getGroups()
    {
        if ($this->groups == null) {
            $ldapUsers = $this->ldapUsers;
            $groupNames = array();
            try {
                $groupNames = Access::doAsSuperUser(function () use ($ldapUsers) {
                    return $ldapUsers->getAllGroupNames();
                });
            } catch (Exception $e) {
            }
            $this->groups = array_combine($groupNames,$groupNames);
            asort($this->groups);
        }
        return $this->groups;
    }

    private static function getAllSites() {
        return Access::doAsSuperUser(function () {
            return APISitesManager::getInstance()->getAllSites();
        });
    }
}
