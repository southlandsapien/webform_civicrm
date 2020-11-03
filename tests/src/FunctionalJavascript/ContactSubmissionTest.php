<?php

namespace Drupal\Tests\webform_civicrm\FunctionalJavascript;

/**
 * Tests submitting a Webform with CiviCRM and a single contact.
 *
 * @group webform_civicrm
 */
final class ContactSubmissionTest extends WebformCivicrmTestBase {

  /**
   * Test submitting a contact.
   *
   * @dataProvider dataContactValues
   */
  public function testSubmitWebform($contact_type, array $contact_values) {
    $this->assertArrayHasKey('contact', $contact_values, 'Test data must contain contact');
    $this->assertArrayHasKey('first_name', $contact_values['contact'], 'Test contact data must contain first_name');
    $this->assertArrayHasKey('last_name', $contact_values['contact'], 'Test confact data must contain last_name');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->webform->toUrl('settings'));
    $this->getSession()->getPage()->clickLink('CiviCRM');
    // @todo Randomly this fails saying that the checkbox does not exist.
    $this->assertSession()->waitForField('Enable CiviCRM Processing');
    $this->htmlOutput();
    $this->getSession()->getPage()->checkField('Enable CiviCRM Processing');
    $this->getSession()->getPage()->selectFieldOption('1_contact_type', strtolower($contact_type));
    $this->assertSession()->assertWaitOnAjaxRequest();

    // @todo this should be refactored as its duplicated for each other type.
    if (isset($contact_values['email'])) {
      $this->assertTrue(is_array($contact_values['email']));
      $this->assertTrue(isset($contact_values['email'][0]));
      $this->getSession()->getPage()->selectFieldOption('contact_1_number_of_email', count($contact_values['email'][0]));
      $this->assertSession()->assertWaitOnAjaxRequest();
      $this->htmlOutput();
      $this->assertSession()->checkboxChecked('civicrm_1_contact_1_email_email');
    }
    if (isset($contact_values['website'])) {
      $this->assertTrue(is_array($contact_values['website']));
      $this->assertTrue(isset($contact_values['website'][0]));
      $this->getSession()->getPage()->selectFieldOption('contact_1_number_of_website', count($contact_values['website'][0]));
      $this->assertSession()->assertWaitOnAjaxRequest();
      $this->htmlOutput();
      $this->assertSession()->checkboxChecked('civicrm_1_contact_1_website_url');
    }

    $this->getSession()->getPage()->pressButton('Save Settings');
    $this->assertSession()->pageTextContains('Saved CiviCRM settings');

    $this->drupalLogout();
    $this->drupalGet($this->webform->toUrl('canonical'));
    $error_messages = $this->getSession()->getPage()->findAll('css', '.messages.messages--error');
    $this->assertCount(0, $error_messages);

    foreach ($contact_values as $entity_type => $field_values) {
      foreach ($field_values as $field_name => $field_value) {
        if (is_array($field_value)) {
          foreach ($field_value as $key => $value) {
            $selector = "civicrm_1_contact_1_{$entity_type}_{$key}";
            $this->getSession()->getPage()->fillField($selector, $value);
          }
        }
        else {
          $selector = "civicrm_1_contact_1_{$entity_type}_{$field_name}";
          $this->getSession()->getPage()->fillField($selector, $field_value);
        }
      }
    }
    $this->getSession()->getPage()->pressButton('Submit');
    $this->assertSession()->pageTextContains('New submission added to CiviCRM Webform Test.');
    $contact_result = wf_civicrm_api('contact', 'get', [
      'first_name' => $contact_values['contact']['first_name'],
      'last_name' => $contact_values['contact']['last_name'],
    ]);
    $result_debug = var_export($contact_result, TRUE);

    $this->assertArrayHasKey('count', $contact_result, $result_debug);
    $this->assertEquals(1, $contact_result['count'], $result_debug);
    $contact = reset($contact_result['values']);
    $this->assertEquals($contact_type, $contact['contact_type']);

    foreach ($contact_values['contact'] as $field_name => $field_value) {
      $this->assertEquals($field_value, $contact[$field_name], $result_debug);
    }

    if (isset($contact_values['email'])) {
      $this->assertEquals($contact_values['email'][0]['email'], $contact['email']);
      $email_result = wf_civicrm_api('email', 'get', [
        'contact_id' => $contact['contact_id'],
      ]);
      $this->assertEquals(count($contact_values['email']), $email_result['count']);
      foreach ($email_result['values'] as $key => $email_entity) {
        $this->assertEquals($contact_values['email'][$key]['email'], $email_entity['email']);
      }
    }
    if (isset($contact_values['website'])) {
      $website_result = wf_civicrm_api('website', 'get', [
        'contact_id' => $contact['contact_id'],
      ]);
      $this->assertEquals(count($contact_values['website']), $website_result['count'], var_export($website_result, TRUE));
      foreach ($website_result['values'] as $key => $website_entity) {
        $this->assertEquals($contact_values['website'][$key]['url'], $website_entity['url']);
      }
    }
  }

  /**
   * Data for the test.
   *
   * Each test returns the Contact type and array of contact values.
   *
   * It is setup that there is one contact, but there may be multiple values
   * for email, website, etc.
   *
   * @todo determine what "type" each email could be.
   *
   * contact_values:
   *  contact:
   *    first_name: foo
   *    last_name: bar
   *    nickname: baz
   *  email:
   *    - email: foo@example.com
   *      type: main
   *  website:
   *    - url: https://example.com
   *
   * @return \Generator
   *   The test data.
   */
  public function dataContactValues() {
    yield [
      'Individual',
      [
        'contact' => [
          'first_name' => 'Frederick',
          'last_name' => 'Pabst',
        ]
    ]];
    yield [
      'Individual',
      [
        'contact' => [
          'first_name' => 'Frederick',
          'last_name' => 'Pabst',
        ],
        'email' => [
          [
            'email' => 'fred@example.com',
          ]
        ],
    ]];
    yield [
      'Individual',
      [
        'contact' => [
          'first_name' => 'Frederick',
          'last_name' => 'Pabst',
        ],
        'website' => [
          [
            'url' => 'https://example.com',
          ]
        ],
    ]];
  }

}
