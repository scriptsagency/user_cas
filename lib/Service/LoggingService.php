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

use Psr\Log\LoggerInterface;

/**
 * Class LoggingService
 *
 * @package OCA\UserCAS\Service
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.5.0
 */
class LoggingService {

  /**
   * @since 1.6.1
   */
  public const int DEBUG = 0;

  /**
   * @since 1.6.1
   */
  public const int INFO = 1;

  /**
   * @since 1.6.1
   */
  public const int WARN = 2;

  /**
   * @since 1.6.1
   */
  public const int ERROR = 3;

  /**
   * @since 1.6.1
   */
  public const int FATAL = 4;

  private string $appName;
  private LoggerInterface $logger;

  /**
   * LoggingService constructor.
   *
   * @param string $appName
   * @param LoggerInterface $logger
   */
  public function __construct(string $appName, LoggerInterface $logger) {
    $this->appName = $appName;
    $this->logger = $logger;
  }

  /**
   * @param mixed $level
   * @param string $message
   */
  public function write(mixed $level, string $message): void {

    $this->logger->log($level, $message, ['app' => $this->appName]);
  }
}