<?php

namespace OCA\UserCAS\Settings;

use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection {

  private IURLGenerator $url;

  /**
   * @param IURLGenerator $url
   */
  public function __construct( IURLGenerator $url) {
    $this->url = $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getID() {
    return 'caslogin';
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'CAS authentication';
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority() {
    return 75;
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return $this->url->imagePath('user_cas', 'app-dark.svg');
  }
}
