<?php

namespace Drupal\sirma\Controller;

use Drupal\Core\Controller\ControllerBase;

class SirmaController extends ControllerBase {

  public function hello() {
    return [
      '#type' => 'markup',
      '#markup' => 'Hello from Sirma module!',
    ];
  }

}
