<?php

namespace Drupal\sirma\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\sirma\Form\CustomExportForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Messenger\MessengerInterface;

class SirmaController extends ControllerBase
{


  /**
   * Page callback.
   */
  public function report()
  {

    $assignments = $this->loadAssignments();
    $pairs = $this->calculatePairs($assignments);
    return [
      '#theme' => 'employee_pairs_report',
      '#pairs' => $pairs,
      '#form' =>  \Drupal::formBuilder()->getForm(CustomExportForm::class, $pairs),
      '#cache' => [
        'max-age' => 0,     // No caching ever
      ],
    ];
  }

  /**
   * Load all project assignment nodes.
   */
  private function loadAssignments()
  {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'project_assignment')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = Node::loadMultiple($nids);
    $assignments = [];
    // dump($assignments);die;
    foreach ($nodes as $node) {

      $assignments[] = [
        'employee' => $node->get('field_employee')->target_id,
        'project'  => $node->get('field_project')->target_id,
        'from'     => $node->get('field_date_from')->value,
        'to'       => $node->get('field_date_to')->value ?: date('Y-m-d'),
      ];
    }

    return $assignments;
  }

  /**
   * Group, match and calculate pairs across projects.
   */
  private function calculatePairs(array $assignments)
  {
    $pairs = [];

    // Group by project.
    $byProject = [];
    foreach ($assignments as $a) {
      $byProject[$a['project']][] = $a;
    }

    // Compare assignments inside each project.
    foreach ($byProject as $projectId => $projectAssignments) {

      for ($i = 0; $i < count($projectAssignments); $i++) {
        for ($j = $i + 1; $j < count($projectAssignments); $j++) {

          $a = $projectAssignments[$i];
          $b = $projectAssignments[$j];

          // If same employee -> skip
          if ($a['employee'] == $b['employee']) {
            continue;
          }

          $days = $this->overlap(
            $a['from'],
            $a['to'],
            $b['from'],
            $b['to']
          );

          $pairs[] = [
            'employee_a' => $a['employee'],
            'employee_b' => $b['employee'],
            'project' => $projectId,
            'days' => $days,
          ];
        }
      }
    }

    return $pairs;
  }

  /**
   * Calculate date range overlap in days.
   */
  private function overlap($from1, $to1, $from2, $to2)
  {
    $start = max(strtotime($from1), strtotime($from2));
    $end = min(strtotime($to1), strtotime($to2));

    if ($start > $end) {
      return 0;
    }

    return floor(($end - $start) / 86400);
  }
}
