<?php

namespace Drupal\sirma\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

/**
 * CSV Upload Form to create project_assignment nodes.
 */
class CsvUploadForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'sirma_csv_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    // Required for file uploads.
    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CSV'),
      '#upload_location' => 'public://csv_uploads/',
      '#description' => $this->t('Format: EmpID,ProjectID,DateFrom,DateTo'),
      '#required' => TRUE,
      '#upload_validators' => [
        'FileExtension' => ['csv'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import CSV'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $file_ids = $form_state->getValue('csv_file');

    if (empty($file_ids[0])) {
      $form_state->setErrorByName('csv_file', $this->t('Please upload a CSV file.'));
      return;
    }

    $file = File::load($file_ids[0]);
    if (!$file) {
      $form_state->setErrorByName('csv_file', $this->t('Cannot load uploaded file.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $file_ids = $form_state->getValue('csv_file');
    $file = File::load($file_ids[0]);

    // Make file permanent.
    $file->setPermanent();
    $file->save();

    $path = $file->getFileUri();
    $real_path = \Drupal::service('file_system')->realpath($path);

    if (($handle = fopen($real_path, 'r')) !== FALSE) {

      $rowNumber = 0;

      while (($row = fgetcsv($handle)) !== FALSE) {

        $rowNumber++;

        // Skip header line.
        if ($rowNumber === 1) {
          continue;
        }

        if (count($row) < 4) {
          \Drupal::messenger()->addError("Invalid row at line {$rowNumber}");
          continue;
        }

        list($empId, $projectId, $dateFrom, $dateTo) = $row;

        $dateFrom = $this->convertDate($dateFrom);
        $dateTo   = $this->convertDate($dateTo);

        if (!is_numeric($empId) || !is_numeric($projectId)) {
          \Drupal::messenger()->addWarning("Invalid employee/project ID at line {$rowNumber}");
          continue;
        }

        // Create node.
        $node = Node::create([
          'type' => 'project_assignment',
          'title' => "Assignment $empId-$projectId",
          'field_employee' => ['target_id' => $empId],
          'field_project'  => ['target_id' => $projectId],
          'field_date_from' => $dateFrom,
          'field_date_to'   => $dateTo,
          'status' => 1,
        ]);

        $node->save();
      }

      fclose($handle);

      \Drupal::messenger()->addStatus($this->t('CSV imported successfully.'));
    } else {
      \Drupal::messenger()->addError($this->t('Could not open the uploaded CSV file.'));
    }
  }

  private function convertDate($value)
  {
    $value = trim($value);

    // Handle empty or NULL values
    if ($value === '' || strtoupper($value) === 'NULL') {
      return NULL;
    }

    try {
      // Create a DateTime from d-m-Y format
      $date = \DateTime::createFromFormat('d-m-Y', $value);

      // Validate date (checks for parsing errors)
      $errors = \DateTime::getLastErrors();
      if ($errors['warning_count'] > 0 || $errors['error_count'] > 0) {
        return NULL;
      }
    } catch (\Exception $e) {
      return NULL;
    }

    // For DATE field → return Y-m-d
    return $date->format('Y-m-d');
  }
}
