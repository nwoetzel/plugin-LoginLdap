<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\LoginLdap\LdapInterop;

use Piwik\Container\StaticContainer;
use Piwik\Plugins\LoginLdap\Settings;
use Psr\Log\LoggerInterface;

/**
 * Uses LDAP groups to determine an LDAP user's Piwik permissions
 * (ie, access to what sites and level of access).
 *
 * Note: This class does not set user access in the DB, it only determines what
 * an LDAP user's access should be.
 *
 * See {@link UserSynchronizer} for more information on LDAP user synchronization.
 */
class UserAccessMapperGroups implements UserAccessMapperInterface
{
    /**
     * The Plugin Settings contain lists of LDAP groups that grant super user access
     * and view and admin access for site ids
     *
     * @var Settings
     */
    private $pluginSettings;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     */
    public function __construct(LoggerInterface $logger = null, Settings $pluginSettings = null)
    {
        $this->logger = $logger ?: StaticContainer::get('Psr\Log\LoggerInterface');
        $this->pluginSettings = $pluginSettings ?: new Settings();
    }

    /**
     * Returns an array describing an LDAP user's access to Piwik sites.
     *
     * The array will either mark the user as a superuser, in which case it will look like
     * this:
     *
     *     array('superuser' => true)
     *
     * Or it will map user access levels with lists of site IDs, for example:
     *
     *     array(
     *         'view' => array(1,2,3),
     *         'admin' => array(3,4,5)
     *     )
     *
     * When nothing us found, it will return
     * array( 'superuser' => false ) otherwise, the access cannot be revoked
     * @param string[] $ldapUser The LDAP entity information.
     * @return array
     */
    public function getPiwikUserAccessForLdapUser($ldapUser)
    {
        // if the user is a superuser, we don't need to check the other attributes
        if ($this->isSuperUserAccessGrantedForLdapUser($ldapUser)) {
            $this->logger->debug("UserAccessMapperGroups::{func}: user '{user}' found to be superuser", array(
                'func' => __FUNCTION__,
                'user' => array_keys($ldapUser)
            ));

            return array('superuser' => true);
        }

        $sitesByAccess = array();

        if( key_exists("groups", $ldapUser)) {
            $groups = $ldapUser["groups"];

            // iterate through all view groups
            foreach( $this->pluginSettings->siteViewGroups as $siteId => $setting) {
                // is there any intersect to the user's groups?
                if( count( array_intersect( $groups, $setting->getValue())) != 0) {                                               
                    $sitesByAccess[$siteId] = 'view';
                }                
            }

            // iterate through all admin groups; will overwrite view access with admin access
            // for the same site since it is of higher priority
            foreach( $this->pluginSettings->siteAdminGroups as $siteId => $setting) {
                // is there any intersect to the user's groups?
                if( count( array_intersect( $groups, $setting->getValue())) != 0) {
                    $sitesByAccess[$siteId] = 'admin';
                }                
            }
        }

        // invert siteByAccess to accessBySite meaning siteId => accesslevel to accessLevel => array(siteIds)
        $accessBySite = array();
        foreach ($sitesByAccess as $site => $access) {
            $accessBySite[$access][] = $site;
        }

        if(empty($accessBySite)) {
            $accessBySite['superuser'] = false;
        }

        return $accessBySite;
    }

    /**
     * ldap group memberships defined in the Settings attribute 'superAccessGroups' defines a super user
     * test if $ldapUser has a key 'groups' and if this contains a group that is also defined in the
     * 'membership_super_access' config param
     *
     * @param array|multikey $ldapUser
     * @return boolean
     */
    private function isSuperUserAccessGrantedForLdapUser($ldapUser)
    {
        // memberships are defined through groups key
        if (!array_key_exists('groups', $ldapUser)) {
            return false;
        }
        // there should be at least one group in the intersect
        return ( count( array_intersect( $ldapUser['groups'], $this->pluginSettings->superAccessGroups->getValue())) != 0);
    }

}
