<?php


namespace OCA\UserCAS\Service\Import;

use Psr\Log\LoggerInterface;


/**
 * Interface ImporterInterface
 *
 * @package OCA\UserCAS\Service\Import
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp
 *
 * @since 1.0.0
 */
interface ImporterInterface {

  /**
   * @param LoggerInterface $logger
   */
  public function init(LoggerInterface $logger);

  public function close();

  public function getUsers();

  /**
   * @param array $userData
   */
  public function exportAsCsv(array $userData);
}