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
 */

use OCA\UserCAS\AppInfo\Application;
use OCA\UserCAS\Service\AppService;
use OCA\UserCAS\Service\LoggingService;
use OCA\UserCAS\Service\UserService;

/** @var Application $app */
$app = new Application();
$c = $app->getContainer();

$requestUri = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');

if (\OCP\App::isEnabled($c->getAppName()) && !\OC::$CLI) {

    /** @var UserService $userService */
    $userService = $c->query('UserService');

    /** @var AppService $appService */
    $appService = $c->query('AppService');

    # Check for valid setup, only enable app if we have at least a CAS host, port and path
    if ($appService->isSetupValid()) {

        // Register User Backend
        $userService->registerBackend($c->query('Backend'));

        $loginScreen = (strpos($requestUri, '/login') !== FALSE && strpos($requestUri, '/apps/user_cas/login') === FALSE);
        $publicShare = (strpos($requestUri, '/index.php/s/') !== FALSE && $appService->arePublicSharesProtected());

        if ($requestUri === '/' || $loginScreen || $publicShare) {

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // POST is used for single logout requests

                // Register UserHooks
                $c->query('UserHooks')->register();

                // URL params and redirect_url cookie
                setcookie("user_cas_enforce_authentication", "0", 0, '/');
                $urlParams = '';

                if (isset($_REQUEST['redirect_url'])) {

                    $urlParams = $_REQUEST['redirect_url'];
                    // Save the redirect_rul to a cookie
                    $cookie = setcookie("user_cas_redirect_url", "$urlParams", 0, '/');
                }

                // Register alternative LogIn
                $appService->registerLogIn();

                /** @var boolean $isEnforced */
                $isEnforced = $appService->isEnforceAuthentication($_SERVER['REMOTE_ADDR'], $requestUri);

                // Check if public share, if yes, enforce regardless the enforce-flag
                if($publicShare) {
                    $isEnforced = true;
                }

                // Check for enforced authentication
                if ($isEnforced && (!isset($_COOKIE['user_cas_enforce_authentication']) || (isset($_COOKIE['user_cas_enforce_authentication']) && $_COOKIE['user_cas_enforce_authentication'] === '0'))) {

                    /** @var LoggingService $loggingService */
                    $loggingService = $c->query("LoggingService");

                    $loggingService->write(LoggingService::DEBUG, 'Enforce Authentication was: ' . $isEnforced);
                    setcookie("user_cas_enforce_authentication", '1', 0, '/');

                    // Initialize app
                    if (!$appService->isCasInitialized()) {

                        try {

                            $appService->init();

                            //if (!\phpCAS::isAuthenticated()) {

                            $loggingService->write(LoggingService::DEBUG, 'Enforce Authentication was on and phpCAS is not authenticated. Redirecting to CAS Server.');

                            $cookie = setcookie("user_cas_redirect_url", urlencode($requestUri), 0, '/');

                            header("Location: " . $appService->linkToRouteAbsolute($c->getAppName() . '.authentication.casLogin'));
                            die();
                            //}

                        } catch (\OCA\UserCAS\Exception\PhpCas\PhpUserCasLibraryNotFoundException $e) {

                            $loggingService->write(LoggingService::ERROR, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());
                        }
                    }
                }
            }
        } else {

            # Filter DAV requests
            if(strpos($requestUri, '/remote.php') === FALSE && strpos($requestUri, '/webdav') === FALSE && strpos($requestUri, '/dav') === FALSE) {
                // Register UserHooks
                $c->query('UserHooks')->register();
            }
        }
    } else {

        $appService->unregisterLogIn();
    }
}