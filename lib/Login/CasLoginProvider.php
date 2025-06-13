<?php

namespace OCA\UserCAS\Login;

use OCP\Authentication\IAlternativeLogin;
use OCP\IURLGenerator;
use OCP\IConfig;

class CasLoginProvider implements IAlternativeLogin {
  private IURLGenerator $urlGenerator;
  private IConfig $config;

  public function __construct(IURLGenerator $urlGenerator, IConfig $config) {
    $this->urlGenerator = $urlGenerator;
    $this->config = $config;
  }

  public function getName(): string {
    $loginButtonLabel = $this->config->getAppValue('user_cas', 'cas_login_button_label', 'CAS');

    if (strlen($loginButtonLabel) <= 0) {
      $loginButtonLabel = 'CAS';
    }

    return $loginButtonLabel;
  }

  public function getLink(): string {
    return $this->urlGenerator->linkToRouteAbsolute('user_cas.authentication.casLogin');
  }

  public function getIcon(): ?string {
    return null;
  }

  public function getDescription(): ?string {
    return null;
  }

  public function getLabel(): string {
    $loginButtonLabel = $this->config->getAppValue('user_cas', 'cas_login_button_label', 'CAS');

    if (strlen($loginButtonLabel) <= 0) {
      $loginButtonLabel = 'CAS';
    }

    return $loginButtonLabel;
  }

  public function getClass(): string {
    return 'login-button-cas user-cas-login-button';
  }

  public function load(): void {
    // Load necessary resources to present the login option, e.g. style-file to style the getClass()
  }
}
