<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginLdap\tests\Integration;

use Piwik\AuthResult;
use Piwik\Common;
use Piwik\Config;
use Piwik\Db;
use Piwik\Plugins\LoginLdap\LdapAuth;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;

/**
 * @group LoginLdap
 * @group LoginLdap_Integration
 * @group LoginLdap_AuthenticationTest
 */
class AuthenticationTest extends LdapIntegrationTest
{
    public function setUp()
    {
        parent::setUp();

        UsersManagerAPI::getInstance()->addUser(self::TEST_SUPERUSER_LOGIN, self::TEST_SUPERUSER_PASS, 'srodgers@aol.com', $alias = false);
        UsersManagerAPI::getInstance()->setSuperUserAccess(self::TEST_SUPERUSER_LOGIN, true);
    }

    public function testLdapAuthSucceedsWithCorrectCredentials()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function testLdapAuthFailsWithIncorrectPassword()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword('slkdjfsd');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testLdapAuthFailsWithNonexistantUser()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin('skldfjsd');
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testLdapAuthChecksMemberOf()
    {
        Config::getInstance()->LoginLdap['memberOf'] = "cn=S.H.I.E.L.D.," . self::SERVER_BASE_DN;

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());

        Config::getInstance()->LoginLdap['memberOf'] = "cn=avengers," . self::SERVER_BASE_DN;

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function testLdapAuthUsesConfiguredFilter()
    {
        Config::getInstance()->LoginLdap['filter'] = "(!(mobile=none))";

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::OTHER_TEST_LOGIN);
        $ldapAuth->setPassword(self::OTHER_TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testWebServerAuthWorksIfUserExistsRegardlessOfPassword()
    {
        Config::getInstance()->LoginLdap['useKerberos'] = 1;

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = new LdapAuth();
        $ldapAuth->setPassword('slkdjfdslf');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());

        $ldapAuth = new LdapAuth();
        $ldapAuth->setPassword(self::TEST_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function testWebServerAuthFailsIfUserDoesNotExist()
    {
        Config::getInstance()->LoginLdap['useKerberos'] = 1;

        $_SERVER['REMOTE_USER'] = 'abcdefghijk';

        $ldapAuth = new LdapAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testWebServerAuthFailsIfUserIsNotPartOfRequiredGroup()
    {
        Config::getInstance()->LoginLdap['useKerberos'] = 1;
        Config::getInstance()->LoginLdap['memberOf'] = "cn=S.H.I.E.L.D.," . self::SERVER_BASE_DN;

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = new LdapAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testWebServerAuthFailsIfUserIsNotMatchedByCustomFilter()
    {
        Config::getInstance()->LoginLdap['useKerberos'] = 1;
        Config::getInstance()->LoginLdap['filter'] = "(mobile=none)";

        $_SERVER['REMOTE_USER'] = self::TEST_LOGIN;

        $ldapAuth = new LdapAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    public function testLdapAuthReturnsCorrectCodeForSuperUsers()
    {
        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_SUPERUSER_LOGIN);
        $ldapAuth->setPassword(self::TEST_SUPERUSER_PASS);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    public function testWebServerAuthReturnsCorrectCodeForSuperUsers()
    {
        Config::getInstance()->LoginLdap['useKerberos'] = 1;

        $_SERVER['REMOTE_USER'] = self::TEST_SUPERUSER_LOGIN;

        $ldapAuth = new LdapAuth();
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    public function testTokenAuthOnlyAuthenticationWorks()
    {
        $this->testLdapAuthSucceedsWithCorrectCredentials();

        $tokenAuth = Db::fetchOne("SELECT token_auth FROM " . Common::prefixTable("user") . " WHERE login = ?", array(self::TEST_LOGIN));

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin(self::TEST_LOGIN);
        $ldapAuth->setTokenAuth($tokenAuth);
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(1, $authResult->getCode());
    }

    public function testAuthenticationWorksWhenAuthenticatingNormalPiwikSuperUser()
    {
        UsersManagerAPI::getInstance()->addUser('zola', 'hydra___', 'zola@shield.org', $alias = false);
        UsersManagerAPI::getInstance()->setSuperUserAccess('zola', true);

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin('zola');
        $ldapAuth->setPassword('hydra___');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
    }

    public function testAuthenticationFailsWhenAuthenticatingNormalPiwikNonSuperUser()
    {
        UsersManagerAPI::getInstance()->addUser('pcoulson', 'vintage', 'pcoulson@shield.org', $alias = false);

        $ldapAuth = new LdapAuth();
        $ldapAuth->setLogin('pcoulson');
        $ldapAuth->setPassword('vintage');
        $authResult = $ldapAuth->authenticate();

        $this->assertEquals(0, $authResult->getCode());
    }

    // TODO: rename kerberos stuff w/ webserver auth
}