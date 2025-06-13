<?php


namespace OCA\UserCAS\Command;

use OC\User\Manager;
use OCA\UserCAS\Service\Import\AdImporter;
use OCA\UserCAS\Service\Import\ImporterInterface;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ImportUsersAd
 *
 * @package OCA\UserCAS\Command
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp
 * @deprecated not maintained
 *
 * @since 1.0.0
 */
class ImportUsersAd extends Command {

  private Manager $userManager;
  private IConfig $config;

  /**
   * ImportUsersAd constructor.
   */
  public function __construct() {
    parent::__construct();

    $this->userManager = \OC::$server->getUserManager();
    $this->config = \OC::$server->getConfig();
  }

  /**
   * Configure method
   */
  protected function configure(): void {
    $this
      ->setName('cas:import-users-ad')
      ->setDescription('Imports users from an ActiveDirectory LDAP.')
      ->addOption(
        'delta-update',
        'd',
        InputOption::VALUE_OPTIONAL,
        'Activate updates on existing accounts'
      )
      ->addOption(
        'convert-backend',
        'c',
        InputOption::VALUE_OPTIONAL,
        'Convert the backend to CAS (on update only)'
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output): void {
    try {
      /**
       * @var LoggerInterface $logger
       */
      $logger = new ConsoleLogger($output);

      # Check for ldap extension
      if (extension_loaded("ldap")) {
        $output->writeln('Start account import from ActiveDirectory.');

        /**
         * @var ImporterInterface $importer
         */
        $importer = new AdImporter($this->config);
        $importer->init($logger);
        $allUsers = $importer->getUsers();
        $importer->close();
        $output->writeln('Account import from ActiveDirectory finished.');
        $output->writeln('Start account import to database.');
        $progressBar = new ProgressBar($output, count($allUsers));

        # Convert backend
        $convertBackend = $input->getOption('convert-backend');
        if ($convertBackend) {
          $logger->info("Backend conversion: Backends will be converted to CAS-Backend.");
        }

        # Delta Update
        $deltaUpdate = $input->getOption('delta-update');
        if ($deltaUpdate) {
          $logger->info("Delta updates: Existing users will be updated.");
        }

        $createCommand = $this->getApplication()->find('cas:create-user');
        $updateCommand = $this->getApplication()->find('cas:update-user');

        foreach ($allUsers as $user) {
          $arguments = [
            'command' => 'cas:create-user',
            'uid' => $user["uid"],
            '--display-name' => $user["displayName"],
            '--email' => $user["email"],
            '--quota' => $user["quota"],
            '--enabled' => $user["enable"],
            '--group' => $user["groups"],
          ];

          # Create user if he does not exist
          if (!$this->userManager->userExists($user["uid"])) {
            $input = new ArrayInput($arguments);
            $createCommand->run($input, new NullOutput());
          } # Update user if he already exists and delta update is true
          else {
            if ($this->userManager->userExists($user["uid"]) && $deltaUpdate) {
              $arguments['command'] = 'cas:update-user';
              if ($convertBackend) {
                $arguments["--convert-backend"] = 1;
              }
              $input = new ArrayInput($arguments);
              $updateCommand->run($input, new NullOutput());
            }
          }

          $progressBar->advance();
        }

        $progressBar->finish();
        $progressBar->clear();

        $output->writeln('Account import to database finished.');

      }
      else {
        throw new \Exception("User import failed. PHP extension 'ldap' is not loaded.");      }
    } catch (\Exception $e) {
      $logger->critical("Fatal Error: " . $e->getMessage());
    }
  }
}