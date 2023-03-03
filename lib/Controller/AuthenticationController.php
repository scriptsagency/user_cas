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

namespace OCA\UserCAS\Controller;


use OCP\AppFramework\Http\TemplateResponse;
use \OCP\IRequest;
use \OCP\AppFramework\Http\RedirectResponse;
use \OCP\AppFramework\Controller;
use \OCP\IConfig;
use \OCP\IUserSession;

use OCA\UserCAS\Service\AppService;
use OCA\UserCAS\Service\UserService;
use OCA\UserCAS\Service\LoggingService;
use OCA\UserCAS\Exception\PhpCas\PhpUserCasLibraryNotFoundException;


/**
 * Class AuthenticationController
 *
 * @package OCA\UserCAS\Controller
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.4.0
 */
class AuthenticationController extends Controller
{

    /**
     * @var string $appName
     */
    protected $appName;

    /**
     * @var \OCP\IConfig $config
     */
    private $config;

    /**
     * @var \OCA\UserCAS\Service\UserService $userService
     */
    private $userService;

    /**
     * @var \OCA\UserCAS\Service\AppService $appService
     */
    private $appService;

    /**
     * @var IUserSession $userSession
     */
    private $userSession;

    /**
     * @var \OCA\UserCAS\Service\LoggingService $loggingService
     */
    private $loggingService;

    /**
     * AuthenticationController constructor.
     * @param $appName
     * @param IRequest $request
     * @param IConfig $config
     * @param UserService $userService
     * @param AppService $appService
     * @param IUserSession $userSession
     * @param LoggingService $loggingService
     */
    public function __construct($appName, IRequest $request, IConfig $config, UserService $userService, AppService $appService, IUserSession $userSession, LoggingService $loggingService)
    {
        $this->appName = $appName;
        $this->config = $config;
        $this->userService = $userService;
        $this->appService = $appService;
        $this->userSession = $userSession;
        $this->loggingService = $loggingService;
        parent::__construct($appName, $request);
    }

    /**
     * Login method.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @UseSession
     * @OnlyUnauthenticatedUsers
     *
     * @return RedirectResponse|TemplateResponse
     */
    public function casLogin()
    {

        if (!$this->appService->isCasInitialized()) {

            try {

                $this->appService->init();
            } catch (PhpUserCasLibraryNotFoundException $e) {

                $this->loggingService->write(\OCA\UserCas\Service\LoggingService::FATAL, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());

                header("Location: " . $this->appService->getAbsoluteURL('/'));
                die();
            }
        }

        # Handle redirect based on cookie value
        if (isset($_COOKIE['user_cas_redirect_url'])) {

            $url = urldecode($_COOKIE['user_cas_redirect_url']);

            if (strpos($url, 'http') !== FALSE || strpos($url, 'https') !== FALSE) {

                $location = $url;
            } else {

                $location = $this->appService->getAbsoluteURL($url);
            }
        } else {

            $location = $this->appService->getAbsoluteURL("/");
        }

        $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, 'The Redirect URL Parameter in Login Action was: ' . $location);

        if (!$this->userService->isLoggedIn()) {

            try {

                if (\phpCAS::isAuthenticated()) {

                    #$userName = \phpCAS::getUser();
                    $userName = $this->userService->getUserId();

                    $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, "phpCAS user " . $userName . " has been authenticated.");

                    $isLoggedIn = $this->userService->login($this->request, $userName);

                    if ($isLoggedIn) {

                        $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, "phpCAS user has been authenticated against owncloud.");

                        # Reset cookie
                        setcookie("user_cas_redirect_url", '/', 0, '/');

                        return new RedirectResponse($location);
                    } else { # Not authenticated against owncloud

                        $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, "phpCAS user has not been authenticated against owncloud.");

                        return $this->casError(null, \OCP\AppFramework\Http::STATUS_FORBIDDEN);
                    }
                } else { # Not authenticated against CAS

                    $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, "phpCAS user is not authenticated, redirect to CAS server.");

                    \phpCAS::forceAuthentication();
                }
            } catch (\CAS_Exception $e) {

                $this->loggingService->write(\OCA\UserCas\Service\LoggingService::ERROR, "phpCAS has thrown an exception with code: " . $e->getCode() . " and message: " . $e->getMessage() . ".");

                return $this->casError(null, \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR);
            }
        } else {

            $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, "phpCAS user is already authenticated against owncloud.");

            # Reset cookie
            setcookie("user_cas_redirect_url", '/', 0, '/');

            return new RedirectResponse($location);
        }
    }


    /**
     * Logout method for CAS Single-Logout-Feature.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @UseSession
     */
    public function casLogout()
    {
        if (!$this->appService->isCasInitialized()) {

            try {

                $this->appService->init();
            } catch (PhpUserCasLibraryNotFoundException $e) {

                $this->loggingService->write(\OCA\UserCas\Service\LoggingService::FATAL, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());

                header("Location: " . $this->appService->getAbsoluteURL('/'));
                die();
            }
        }

        // Logout oC/NC user
        if ($this->userService->isLoggedIn()) {

            $this->userService->logout();
        }
    }


    /**
     * Render error view
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @UseSession
     * @OnlyUnauthenticatedUsers
     *
     * @param \Exception|null $exception
     * @param int $additionalErrorCode
     *
     * @return TemplateResponse
     */
    private function casError(\Exception $exception = NULL, $additionalErrorCode = 0)
    {
        $params = [];

        if ($additionalErrorCode !== 0) {

            if ($additionalErrorCode === \OCP\AppFramework\Http::STATUS_FORBIDDEN) {

                if (boolval($this->config->getAppValue('user_cas', 'cas_ecas_attributeparserenabled', false))) {

                    $params['errorCode'] = '';
                    $params['errorMessage'] = "You do not have access to the JRCbox application. Please contact the JRCbox administrator if something feels wrong to you.";
                } else {

                    $params['errorCode'] = $additionalErrorCode;
                    $params['errorMessage'] = "Forbidden. You do not have access to this application. Please refer to your administrator if something feels wrong to you.";
                }
            }

            if ($additionalErrorCode === \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR) {

                $params['errorCode'] = $additionalErrorCode;
                $params['errorMessage'] = "Internal Server Error. The server encountered an error. Please try again.";
            }
        } else if ($exception instanceof \Exception) {

            $params['errorCode'] = $exception->getCode();
            $params['errorMessage'] = $exception->getMessage();
        }

        /*if ($this->config->getAppValue($this->appName, 'cas_force_login') === '1') {

            $newProtocol = 'http://';

            if (intval($this->config->getAppValue($this->appName, 'cas_server_port')) === 443) {

                $newProtocol = 'https://';
            }

            $params['backUrl'] = $newProtocol . $this->config->getAppValue($this->appName, 'cas_server_hostname') . $this->config->getAppValue($this->appName, 'cas_server_path');
        } else {*/

            $params['backUrl'] = $this->appService->getAbsoluteURL('/');
        //}

        $response = new TemplateResponse($this->appName, 'cas-error', $params, 'guest');

        return $response;
    }
}
