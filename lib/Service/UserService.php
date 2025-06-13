<?php

/**
 * ownCloud - user_cas
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserCAS\Service;

use OCA\UserCAS\User\UserCasBackendInterface;
use \OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use \OCP\IUserManager;
use \OCP\IGroupManager;
use \OCP\IUserSession;
use OCP\Util;


/**
 * Class UserService
 *
 * @package OCA\UserCAS\Service
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.4
 */
class UserService
{

  private string $appName;
  private IConfig $config;
  private IUserSession $userSession;
  private IUserManager $userManager;
  private IGroupManager $groupManager;
  private AppService $appService;
  private LoggingService $loggingService;


  /**
   * UserService constructor.
   *
   * @param $appName
   * @param IConfig $config
   * @param IUserManager $userManager
   * @param IUserSession $userSession
   * @param IGroupManager $groupManager
   * @param AppService $appService
   * @param LoggingService $loggingService
   */
  public function __construct($appName, IConfig $config, IUserManager $userManager, IUserSession $userSession, IGroupManager $groupManager, AppService $appService, LoggingService $loggingService) {

    $this->appName = $appName;
    $this->config = $config;
    $this->userManager = $userManager;
    $this->userSession = $userSession;
    $this->groupManager = $groupManager;
    $this->appService = $appService;
    $this->loggingService = $loggingService;
  }

  /**
   * Login hook method.
   *
   * @param $request
   * @param string $uid
   * @param string $password
   *
   * @return bool
   */
  public function login($request, string $uid, string $password = ''): bool {
    $this->loggingService->write(LoggingService::DEBUG, 'phpCAS login function step 1.');

    try {
      if (!$this->config->getAppValue($this->appName, 'cas_autocreate') && !$this->userExists($uid)) {
        $this->loggingService->write(LoggingService::DEBUG, 'phpCas autocreate disabled, and OC User does not exist, phpCas based login not possible. Bye.');
        return false;
      }

      # Check if user may be authorized based on groups or not
      $cas_access_allow_groups = $this->config->getAppValue($this->appName, 'cas_access_allow_groups');
      if (is_string($cas_access_allow_groups) && strlen($cas_access_allow_groups) > 0) {
        $cas_access_allow_groups = explode(',', $cas_access_allow_groups);
        $casAttributes = \phpCAS::getAttributes();
        $casGroups = [];
        $isAuthorized = false;

        $groupMapping = $this->config->getAppValue($this->appName, 'cas_group_mapping');

        # Test if an attribute parser added a new dimension to our attribute array
        if (array_key_exists('attributes', $casAttributes)) {
          $newAttributes = $casAttributes['attributes'];
          unset($casAttributes['attributes']);
          $casAttributes = array_merge($casAttributes, $newAttributes);
        }

        # Test for mapped attribute from settings
        if (array_key_exists($groupMapping, $casAttributes)) {
          $casGroups = (array) $casAttributes[$groupMapping];
        } # Test for standard 'groups' attribute
        else {
          if (array_key_exists('groups', $casAttributes)) {
            if ($this->config->getAppValue($this->appName, 'cas_groups_json_decode')) {
              $casGroups = json_decode($casAttributes['groups']);
            }
            else {
              $casGroups = (array) $casAttributes['groups'];
            }
          }
        }

        foreach ($casGroups as $casGroup) {
          if (in_array($casGroup, $cas_access_allow_groups)) {
            $this->loggingService->write(LoggingService::DEBUG, 'phpCas CAS users login has been authorized with group: ' . $casGroup);
            $isAuthorized = true;
          }
          else {
            $this->loggingService->write(LoggingService::DEBUG, 'phpCas CAS users login has not been authorized with group: ' . $casGroup . ', because the group was not in allowedGroups: ' . implode(", ", $cas_access_allow_groups));
          }
        }

        if ($this->groupManager->isInGroup($uid, 'admin')) {
          $this->loggingService->write(LoggingService::DEBUG, 'phpCas CAS users login has been authorized with group: admin');
          $isAuthorized = true;
        }

        if (!$isAuthorized) {
          $this->loggingService->write(LoggingService::DEBUG, 'phpCas CAS user is not authorized to log into ownCloud. Bye.');
          return false;
        }
      }

      $loginSuccessful = $this->userSession->login($uid, $password);
      $this->loggingService->write(LoggingService::DEBUG, 'phpCAS login function result: ' . $loginSuccessful);
      if ($loginSuccessful) {
        return $this->userSession->createSessionToken($request, $this->userSession->getUser()->getUID(), $uid, NULL);
      }

      $this->loggingService->write(LoggingService::DEBUG, 'phpCAS login function not successful.');
      return false;
    } catch (\OC\User\LoginException $e) {
      $this->loggingService->write(LoggingService::ERROR, 'Owncloud could not log in the user with UID: ' . $uid . '. Exception thrown with code: ' . $e->getCode() . ' and message: ' . $e->getMessage() . '.');
      return false;
    }
  }

  /**
   * Logout function
   *
   * @return void
   */
  public function logout(): void {
    $this->userSession->logout();
  }

  /**
   * IsLoggedIn method.
   *
   * @return boolean
   */
  public function isLoggedIn(): bool {
    return $this->userSession->isLoggedIn();
  }

  /**
   * @param string $userId
   * @param UserCasBackendInterface $backend
   *
   * @return boolean|\OCP\IUser the created user or false
   * @throws \Exception
   */
  public function create(string $userId, UserCasBackendInterface $backend): bool|IUser {
    $randomPassword = $this->getNewPassword();
    if ($backend->implementsActions(\OC\User\Backend::CREATE_USER)) {
      return $this->userManager->createUserFromBackend($userId, $randomPassword, $backend);
    }
    return false;
  }

  /**
   * @param string $userId
   *
   * @return bool
   */
  public function userExists(string $userId): bool {
    return $this->userManager->userExists($userId);
  }

  /**
   * @return string
   */
  public function getUserId(): string {
    $uid = \phpCAS::getUser();
    $casAttributes = \phpCAS::getAttributes();
    if ($this->config->getAppValue($this->appName, 'cas_userid_mapping') && strlen($this->config->getAppValue($this->appName, 'cas_userid_mapping')) > 0) {
      $userIdAttribute = $this->config->getAppValue($this->appName, 'cas_userid_mapping');
      if (isset($casAttributes[$userIdAttribute])) {
        $uid = $casAttributes[$userIdAttribute];
      }
    }

    return $uid;
  }

  /**
   * Update the user
   *
   * @param \OCP\IUser $user
   * @param array $attributes
   */
  public function updateUser(IUser $user, array $attributes): void {
    $userId = $user->getUID();
    $newGroupQuota = NULL;
    $this->loggingService->write(LoggingService::DEBUG, 'Updating data of the user: ' . $userId);
    if (isset($attributes['cas_email']) && is_object($user)) {
      $this->updateMail($user, $attributes['cas_email']);
    }
    if (isset($attributes['cas_name']) && is_object($user)) {
      $this->updateName($user, $attributes['cas_name']);
    }
    if (isset($attributes['cas_groups']) && is_object($user)) {
      $this->updateGroups($user, $attributes['cas_groups'], $this->config->getAppValue($this->appName, 'cas_protected_groups'));
    }
    if (isset($attributes['cas_group_quota']) && is_object($user)) {
      $newGroupQuota = $this->updateGroupQuota($user, $attributes['cas_group_quota']);
    }
    if (isset($attributes['cas_quota']) && is_object($user)) {
      $this->updateQuota($user, $newGroupQuota, $attributes['cas_quota']);
    }

    $this->loggingService->write(LoggingService::DEBUG, 'Updating data finished.');
  }

  /**
   * Update the eMail address
   *
   * @param \OCP\IUser $user
   * @param string|array $email
   */
  private function updateMail(IUser $user, string|array $email): void {
    if (is_array($email)) {
      $email = $email[0];
    }

    if ($email !== $user->getEMailAddress()) {
      $user->setEMailAddress($email);
      $this->loggingService->write(LoggingService::DEBUG, 'Set email "' . $email . '" for the user: ' . $user->getUID());
    }
  }

  /**
   * Update the display name
   *
   * @param \OCP\IUser $user
   * @param string|array $name
   */
  private function updateName(IUser $user, string|array $name): void {
    if (is_array($name)) {
      $name = $name[0];
    }

    if ($name !== $user->getDisplayName() && $name !== '') {
      $user->setDisplayName($name);
      $this->loggingService->write(LoggingService::DEBUG, 'Set Name: ' . $name . ' for the user: ' . $user->getUID());
    }
  }

  /**
   * Gets an array of groups and will try to add the group to OC and then add
   * the user to the groups.
   *
   * @param \OCP\IUser $user
   * @param string|array $groups
   * @param string|array $protectedGroups
   * @param bool $justCreated
   */
  public function updateGroups(IUser $user, string|array $groups, string|array $protectedGroups = '', bool $justCreated = FALSE): void {
    if (is_string($groups)) {
      $groups = explode(",", $groups);
    }
    if (is_string($protectedGroups)) {
      $protectedGroups = explode(",", $protectedGroups);
    }

    $uid = $user->getUID();

    foreach ($groups as $group) {
      $groupObject = NULL;
      # Replace umlauts
      if ($this->config->getAppValue($this->appName, 'cas_groups_letter_umlauts')) {
        $group = str_replace("Ä", "Ae", $group);
        $group = str_replace("Ö", "Oe", $group);
        $group = str_replace("Ü", "Ue", $group);
        $group = str_replace("ä", "ae", $group);
        $group = str_replace("ö", "oe", $group);
        $group = str_replace("ü", "ue", $group);
        $group = str_replace("ß", "ss", $group);
      }

      # Filter unwanted characters
      $nameFilter = $this->config->getAppValue($this->appName, 'cas_groups_letter_filter');
      if (strlen($nameFilter) > 0) {
        $group = preg_replace("/[^" . $nameFilter . "]+/", "", $group);
      }
      else { # Use default filter
        $group = preg_replace("/[^a-zA-Z0-9\.\-_ @\/]+/", "", $group);
      }

      # Filter length to max 64 chars
      if (strlen($group) > 64) {
        $group = substr($group, 0, 63) . "…";
      }

      if (!$this->groupManager->isInGroup($uid, $group)) {
        if (!$this->groupManager->groupExists($group)) {
          $groupObject = $this->groupManager->createGroup($group);
          $this->loggingService->write(LoggingService::DEBUG, 'New group created: ' . $group);
        }
        else {
          $groupObject = $this->groupManager->get($group);
        }

        $groupObject->addUser($user);
        $this->loggingService->write(LoggingService::DEBUG, "Added '" . $uid . "' to the group '" . $group . "'");
      }
    }
  }

  /**
   * @param \OCP\IUser $user
   * @param int|bool $newGroupQuota
   * @param string $quota
   */
  public function updateQuota(IUser $user, int|bool $newGroupQuota, string $quota = 'default'): void {
    $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, 'phpCas new UserQuota contents: ' . $quota . ' | New GroupQuota was: ' . $newGroupQuota);
    $defaultQuota = $this->config->getAppValue('files', 'default_quota');
    if ($defaultQuota === '') {
      $defaultQuota = 'none';
    }

    $uid = $user->getUID();

    if ($quota === 'none') {
      $newQuota = PHP_INT_MAX;
    }
    elseif ($quota === 'default') {
      $newQuota = Util::computerFileSize($defaultQuota);
    }
    else {
      $newQuota = Util::computerFileSize($quota);
    }

    $usersOldQuota = $user->getQuota();
    if ($usersOldQuota === 'none') {
      $usersOldQuota = PHP_INT_MAX;
    }
    elseif ($usersOldQuota === 'default') {
      $usersOldQuota = Util::computerFileSize($defaultQuota);
    }
    else {
      $usersOldQuota = Util::computerFileSize($usersOldQuota);
    }

    $this->loggingService->write(LoggingService::DEBUG, "Default System Quota is: '" . $defaultQuota . "'");
    $this->loggingService->write(LoggingService::DEBUG, "User '" . $uid . "' old computerized Quota is: '" . $usersOldQuota . "'");
    $this->loggingService->write(LoggingService::DEBUG, "User '" . $uid . "' new computerized User Quota would be: '" . $newQuota . "'");

    if ($usersOldQuota < $newQuota || ($usersOldQuota > $newQuota && $newGroupQuota != NULL)) {
      $user->setQuota($newQuota);
      $this->loggingService->write(LoggingService::DEBUG, "User '" . $uid . "' has new Quota: '" . $newQuota . "'");
    }
  }

  /**
   * @param \OCP\IUser $user
   * @param array $groupQuotas
   *
   * @return int New Quota
   */
  private function updateGroupQuota(IUser $user, array $groupQuotas): int {
    $defaultQuota = $this->config->getAppValue('files', 'default_quota');

    if ($defaultQuota === '') {
      $defaultQuota = 'none';
    }

    $uid = $user->getUID();
    $collectedQuotas = [];

    foreach ($groupQuotas as $groupName => $groupQuota) {
      if ($this->groupManager->isInGroup($uid, $groupName)) {
        if ($groupQuota === 'none') {
          $collectedQuotas[PHP_INT_MAX] = $groupQuota;        }
        elseif ($groupQuota === 'default') {
          $defaultQuotaFilesize = Util::computerFileSize($defaultQuota);
          $collectedQuotas[$defaultQuotaFilesize] = $groupQuota;
        }
        else {
          $groupQuotaComputerFilesize = Util::computerFileSize($groupQuota);
          $collectedQuotas[$groupQuotaComputerFilesize] = $groupQuota;
        }
      }
    }

    # Sort descending by key
    krsort($collectedQuotas);
    $newQuota = Util::computerFileSize(array_shift($collectedQuotas));
    $usersOldQuota = $user->getQuota();
    if ($usersOldQuota === 'none') {
      $usersOldQuota = PHP_INT_MAX;
    }
    elseif ($usersOldQuota === 'default') {
      $usersOldQuota = Util::computerFileSize($defaultQuota);
    }
    else {
      $usersOldQuota = Util::computerFileSize($usersOldQuota);
    }

    $this->loggingService->write(LoggingService::DEBUG, "Default System Quota is: '" . $defaultQuota . "'");
    $this->loggingService->write(LoggingService::DEBUG, "User '" . $uid . "' old computerized Quota is: '" . $usersOldQuota . "'");
    $this->loggingService->write(LoggingService::DEBUG, "User '" . $uid . "' new computerized Group Quota would be: '" . $newQuota . "'");

    if ($usersOldQuota < $newQuota) {
      $user->setQuota($newQuota);
      $this->loggingService->write(LoggingService::DEBUG, "User '" . $uid . "' has new Quota: '" . $newQuota . "'");
      return $newQuota;
    }

    return $usersOldQuota;
  }

  /**
   * @param \OCP\IUser $user
   * @param string $userType
   */
  private function updateUserType(IUser $user, string $userType): void {
    if ($userType && method_exists($user, 'setUserType')) {
      $user->setUserType($userType);

      $this->loggingService->write(ILogger::DEBUG, 'Set User Type: ' . $userType . ' for the user: ' . $user->getUID());
    }
  }

  /**
   * Register User Backend.
   *
   * @param UserCasBackendInterface $backend
   */
  public function registerBackend(UserCasBackendInterface $backend): void {
    $this->userManager->registerBackend($backend);
  }

  /**
   * Generate a random PW with special char symbol characters
   *
   * @return string New Password
   */
  protected function getNewPassword(): string {
    $newPasswordCharsLower = \OC::$server->getSecureRandom()
      ->generate(8, \OCP\Security\ISecureRandom::CHAR_LOWER);
    $newPasswordCharsUpper = \OC::$server->getSecureRandom()
      ->generate(4, \OCP\Security\ISecureRandom::CHAR_UPPER);
    $newPasswordNumbers = \OC::$server->getSecureRandom()
      ->generate(4, \OCP\Security\ISecureRandom::CHAR_DIGITS);
    $newPasswordSymbols = \OC::$server->getSecureRandom()
      ->generate(4, \OCP\Security\ISecureRandom::CHAR_SYMBOLS);

    return str_shuffle($newPasswordCharsLower . $newPasswordCharsUpper . $newPasswordNumbers . $newPasswordSymbols);
  }
}
