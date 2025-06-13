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


namespace OCA\UserCAS\AppInfo;

use OCA\UserCAS\Exception\PhpCas\PhpUserCasLibraryNotFoundException;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;

use Psr\Log\LoggerInterface;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\App\IAppManager;
use OCP\IRequest;

use OCA\UserCAS\Service\UserService;
use OCA\UserCAS\Service\AppService;
use OCA\UserCAS\Hooks\UserHooks;
use OCA\UserCAS\Controller\SettingsController;
use OCA\UserCAS\Controller\AuthenticationController;
use OCA\UserCAS\User\NextBackend;
use OCA\UserCAS\Service\LoggingService;
use OCA\UserCAS\Login\CasLoginProvider;

class Application extends App implements IBootstrap {

  public const string APP_ID = 'user_cas';

  public function __construct(array $urlParams = []) {
    parent::__construct(self::APP_ID, $urlParams);
  }

  public function register(IRegistrationContext $context): void {

    // Register LoggingService
    $context->registerService(LoggingService::class, function ($c) {
      return new LoggingService(
        self::APP_ID,
        $c->get(LoggerInterface::class),
      );
    });

    // Register AppService
    $context->registerService(AppService::class, function ($c) {
      return new AppService(
        self::APP_ID,
        $c->get(IConfig::class),
        $c->get(LoggingService::class),
        $c->get(IUserManager::class),
        $c->get(IUserSession::class),
        $c->get(IURLGenerator::class),
        $c->get(IAppManager::class)
      );
    });

    // Register UserService
    $context->registerService(UserService::class, function ($c) {
      return new UserService(
        self::APP_ID,
        $c->get(IConfig::class),
        $c->get(IUserManager::class),
        $c->get(IUserSession::class),
        $c->get(IGroupManager::class),
        $c->get(AppService::class),
        $c->get(LoggingService::class)
      );
    });

    // Register NextBackend
    $context->registerService(NextBackend::class, function ($c) {
      return new NextBackend(
        self::APP_ID,
        $c->get(IConfig::class),
        $c->get(LoggingService::class),
        $c->get(AppService::class),
        $c->get(IUserManager::class),
        $c->get(UserService::class)
      );
    });

    // Register SettingsController
    $context->registerService(SettingsController::class, function ($c) {
      return new SettingsController(
        self::APP_ID,
        $c->get(IRequest::class),
        $c->get(IConfig::class)
      );
    });

    // Register AuthenticationController
    $context->registerService(AuthenticationController::class, function ($c) {
      return new AuthenticationController(
        self::APP_ID,
        $c->get(IRequest::class),
        $c->get(IConfig::class),
        $c->get(UserService::class),
        $c->get(AppService::class),
        $c->get(LoggingService::class)
      );
    });

    // Register UserHooks
    $context->registerService(UserHooks::class, function ($c) {
      return new UserHooks(
        self::APP_ID,
        $c->get(IUserManager::class),
        $c->get(IUserSession::class),
        $c->get(IConfig::class),
        $c->get(UserService::class),
        $c->get(AppService::class),
        $c->get(LoggingService::class),
        $c->get(NextBackend::class)
      );
    });

    // Register the alternative login button
    $context->registerAlternativeLogin(CasLoginProvider::class);
  }

  public function boot(IBootContext $context): void {
    $container = $this->getContainer();

    if (\OC::$CLI) {
      return;
    }

    /**
     * @var IAppManager $appManager
     */
    $appManager = $context->getAppContainer()->get(IAppManager::class);
    if (!$appManager->isInstalled(self::APP_ID)) {
      return;
    }

    /** @var \OCP\IRequest $request */
    $request = $container->get(IRequest::class);
    /** @var AppService $appService */
    $appService = $container->get(AppService::class);
    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    /** @var LoggingService $loggingService */
    $loggingService = $container->get(LoggingService::class);
    /** @var IURLGenerator $urlGenerator */
    $urlGenerator = $container->get(IURLGenerator::class);

    $linkToRoot = $urlGenerator->linkTo(self::APP_ID, '', []);
    $basePath = $linkToRoot;
    if (substr($linkToRoot, -10) === '/index.php') {
      $basePath = substr($linkToRoot, 0, -10);
    } elseif (substr($linkToRoot, -9) === 'index.php') {
      $basePath = substr($linkToRoot, 0, -9);
    }
    $cookiePath = rtrim($basePath, '/') . '/';
    if (empty($cookiePath) || $cookiePath === '//') {
      $cookiePath = '/';
    }

    $secureCookie = $request->getServerProtocol() === 'https';

    // Common options for setcookie()
    $cookieOptions = [
      'expires' => 0, // Session cookie
      'path' => $cookiePath,
      'domain' => '', // Current domain
      'secure' => $secureCookie, // True if HTTPS
      'httponly' => true,
      'samesite' => 'Lax' // Good default: 'Lax', 'Strict', or 'None' (if 'None', 'secure' must be true)
    ];

    if ($appService->isSetupValid()) {
      $userBackend = $container->get(NextBackend::class);
      $userService->registerBackend($userBackend);

      $requestUri = $request->getRequestUri();
      $onCasAppRoute = strpos($requestUri, '/apps/' . self::APP_ID) !== false;
      $loginScreen = (strpos($requestUri, '/login') !== false && !$onCasAppRoute);
      $publicShare = (strpos($requestUri, '/index.php/s/') !== false && $appService->arePublicSharesProtected());

      if ($requestUri === '/' || $loginScreen || $publicShare) {
        if (strtoupper($request->getMethod()) !== 'POST') {
          $userHooks = $container->get(UserHooks::class);
          if (method_exists($userHooks, 'register')) {
            $userHooks->register();
          }

          setcookie("user_cas_enforce_authentication", "0", $cookieOptions);

          $redirectUrlParam = $request->getParam('redirect_url');
          if ($redirectUrlParam !== null) {
            setcookie("user_cas_redirect_url", $redirectUrlParam, $cookieOptions);
          }

          $isEnforced = $appService->isEnforceAuthentication($request->getRemoteAddress(), $requestUri);
          if ($publicShare) {
            $isEnforced = true;
          }

          $enforceAuthCookie = $request->getCookie('user_cas_enforce_authentication');

          if ($isEnforced && ($enforceAuthCookie === null || $enforceAuthCookie === '0')) {
            $loggingService->write(LoggingService::DEBUG, 'Enforce Authentication is active for URI: ' . $requestUri);
            setcookie("user_cas_enforce_authentication", '1', $cookieOptions);

            if (!$appService->isCasInitialized()) {
              try {
                $appService->init();

                $loggingService->write(LoggingService::DEBUG, 'CAS not initialized and enforce is on. Redirecting to CAS Server auth trigger for URI: ' . $requestUri);
                setcookie("user_cas_redirect_url", urlencode($requestUri), $cookieOptions);

                $casLoginUrl = $urlGenerator->linkToRouteAbsolute(self::APP_ID . '.authentication.casLogin');
                header("Location: " . $casLoginUrl);
                exit();

              } catch (PhpUserCasLibraryNotFoundException $e) {
                $loggingService->write(LoggingService::ERROR, 'Fatal error during CAS init (phpCAS library not found): ' . $e->getMessage() . ' Code: ' . $e->getCode());
              } catch (\Exception $e) {
                $loggingService->write(LoggingService::ERROR, 'Generic error during CAS init: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
              }
            }
          }
        }
      } else {
        if (strpos($requestUri, '/remote.php') === false &&
          strpos($requestUri, '/webdav') === false &&
          strpos($requestUri, '/dav') === false) {
          $userHooks = $container->get(UserHooks::class);
          if (method_exists($userHooks, 'register')) {
            $userHooks->register();
          }
        }
      }
    } else {
      $loggingService->write(LoggingService::INFO, 'User_CAS setup is not valid. Unregistering login page mechanism.');
    }
  }
}
