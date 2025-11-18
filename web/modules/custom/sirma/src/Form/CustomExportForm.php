<?php

namespace Drupal\sirma\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Response;

class CustomExportForm extends FormBase
{

  public function getFormId()
  {
    return 'employees_export_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, array $pairs = [])
  {

    $options = [];
    foreach ($pairs as $key => $row) {
      $options[$key] = $key; 
    }

    $form['pairs'] = [
      '#type' => 'checkboxes',
      '#options' => array_map(fn($r) => '', $options),
      '#theme_wrappers' => [],
      '#return_value' => 1,
    ];

    $form['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export Selected as CSV'),
    ];
//  var_dump($form['pairs']);die;
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $selected = array_filter($form_state->getValue('pairs'));

    if (empty($selected)) {
      $this->messenger()->addError($this->t('No rows selected.'));
      return;
    }

    $rows = [];
    $rows[] = ['Employee ID 1', 'Employee ID 2', 'Project ID', 'Days worked'];

    foreach ($selected as $key => $checked) {
      $row = $form_state->getBuildInfo()['args'][0][$key];

      $rows[] = [
        $row['employee_a'],
        $row['employee_b'],
        $row['project'],
        $row['days'],
      ];
    }

    // Build CSV
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $line) {
      fputcsv($handle, $line);
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set(
      'Content-Disposition',
      'attachment; filename="employees_worked_together.csv"'
    );

    $form_state->setResponse($response);
  }
}
