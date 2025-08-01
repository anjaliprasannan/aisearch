<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\Exception\BrokenPostRequestException;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @coversDefaultClass \Drupal\Core\Form\FormBuilder
 * @group Form
 */
class FormBuilderTest extends FormTestBase {

  /**
   * The dependency injection container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();
    $cache_contexts_manager = $this->prophesize(CacheContextsManager::class)->reveal();
    $this->container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($this->container);
  }

  /**
   * Tests the getFormId() method with a string based form ID.
   *
   * @covers ::getFormId
   */
  public function testGetFormIdWithString(): void {
    $form_arg = 'foo';
    $form_state = new FormState();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The form class foo could not be found or loaded.');
    $this->formBuilder->getFormId($form_arg, $form_state);
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormIdWithNonFormClass(): void {
    $form_arg = \stdClass::class;
    $form_state = new FormState();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("The form argument $form_arg must be an instance of \Drupal\Core\Form\FormInterface.");
    $this->formBuilder->getFormId($form_arg, $form_state);
  }

  /**
   * Tests the getFormId() method with a class name form ID.
   */
  public function testGetFormIdWithClassName(): void {
    $form_arg = 'Drupal\Tests\Core\Form\TestForm';

    $form_state = new FormState();
    $form_id = $this->formBuilder->getFormId($form_arg, $form_state);

    $this->assertSame('test_form', $form_id);
    $this->assertSame($form_arg, get_class($form_state->getFormObject()));
  }

  /**
   * Tests the getFormId() method with an injected class name form ID.
   */
  public function testGetFormIdWithInjectedClassName(): void {
    $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
    \Drupal::setContainer($container);

    $form_arg = 'Drupal\Tests\Core\Form\TestFormInjected';

    $form_state = new FormState();
    $form_id = $this->formBuilder->getFormId($form_arg, $form_state);

    $this->assertSame('test_form', $form_id);
    $this->assertSame($form_arg, get_class($form_state->getFormObject()));
  }

  /**
   * Tests the getFormId() method with a form object.
   */
  public function testGetFormIdWithObject(): void {
    $expected_form_id = 'my_module_form_id';

    $form_arg = $this->getMockForm($expected_form_id);

    $form_state = new FormState();
    $form_id = $this->formBuilder->getFormId($form_arg, $form_state);

    $this->assertSame($expected_form_id, $form_id);
    $this->assertSame($form_arg, $form_state->getFormObject());
  }

  /**
   * Tests the getFormId() method with a base form object.
   */
  public function testGetFormIdWithBaseForm(): void {
    $expected_form_id = 'my_module_form_id';
    $base_form_id = 'my_module';

    $form_arg = $this->createMock('Drupal\Core\Form\BaseFormIdInterface');
    $form_arg->expects($this->once())
      ->method('getFormId')
      ->willReturn($expected_form_id);
    $form_arg->expects($this->once())
      ->method('getBaseFormId')
      ->willReturn($base_form_id);

    $form_state = new FormState();
    $form_id = $this->formBuilder->getFormId($form_arg, $form_state);

    $this->assertSame($expected_form_id, $form_id);
    $this->assertSame($form_arg, $form_state->getFormObject());
    $this->assertSame($base_form_id, $form_state->getBuildInfo()['base_form_id']);
  }

  /**
   * Tests the handling of FormStateInterface::$response.
   *
   * @dataProvider formStateResponseProvider
   */
  public function testHandleFormStateResponse($class, $form_state_key): void {
    $form_id = 'test_form_id';
    $expected_form = $form_id();

    $response = $this->getMockBuilder($class)
      ->disableOriginalConstructor()
      ->getMock();

    $form_arg = $this->getMockForm($form_id, $expected_form);
    $form_arg->expects($this->any())
      ->method('submitForm')
      ->willReturnCallback(function ($form, FormStateInterface $form_state) use ($response, $form_state_key) {
        $form_state->setFormState([$form_state_key => $response]);
      });

    $form_state = new FormState();
    try {
      $input['form_id'] = $form_id;
      $form_state->setUserInput($input);
      $this->simulateFormSubmission($form_id, $form_arg, $form_state, FALSE);
      $this->fail('EnforcedResponseException was not thrown.');
    }
    catch (EnforcedResponseException $e) {
      $this->assertSame($response, $e->getResponse());
    }
    $this->assertSame($response, $form_state->getResponse());
  }

  /**
   * Provides test data for testHandleFormStateResponse().
   */
  public static function formStateResponseProvider() {
    return [
      ['Symfony\Component\HttpFoundation\Response', 'response'],
      ['Symfony\Component\HttpFoundation\RedirectResponse', 'redirect'],
    ];
  }

  /**
   * Tests the handling of a redirect when FormStateInterface::$response exists.
   */
  public function testHandleRedirectWithResponse(): void {
    $form_id = 'test_form_id';
    $expected_form = $form_id();

    // Set up a response that will be used.
    $response = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')
      ->disableOriginalConstructor()
      ->getMock();

    // Set up a redirect that will not be called.
    $redirect = $this->getMockBuilder('Symfony\Component\HttpFoundation\RedirectResponse')
      ->disableOriginalConstructor()
      ->getMock();

    $form_arg = $this->getMockForm($form_id, $expected_form);
    $form_arg->expects($this->any())
      ->method('submitForm')
      ->willReturnCallback(function ($form, FormStateInterface $form_state) use ($response, $redirect) {
        // Set both the response and the redirect.
        $form_state->setResponse($response);
        $form_state->set('redirect', $redirect);
      });

    $form_state = new FormState();
    try {
      $input['form_id'] = $form_id;
      $form_state->setUserInput($input);
      $this->simulateFormSubmission($form_id, $form_arg, $form_state, FALSE);
      $this->fail('EnforcedResponseException was not thrown.');
    }
    catch (EnforcedResponseException $e) {
      $this->assertSame($response, $e->getResponse());
    }
    $this->assertSame($response, $form_state->getResponse());
  }

  /**
   * Tests the getForm() method with a string based form ID.
   */
  public function testGetFormWithString(): void {
    $form_id = 'test_form_id';
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The form class test_form_id could not be found or loaded.');
    $this->formBuilder->getForm($form_id);
  }

  /**
   * Tests the getForm() method with a form object.
   */
  public function testGetFormWithObject(): void {
    $form_id = 'test_form_id';
    $expected_form = $form_id();

    $form_arg = $this->getMockForm($form_id, $expected_form);

    $form = $this->formBuilder->getForm($form_arg);
    $this->assertFormElement($expected_form, $form, 'test');
    $this->assertArrayHasKey('#id', $form);
  }

  /**
   * Tests the getForm() method with a class name based form ID.
   */
  public function testGetFormWithClassString(): void {
    $form_id = '\Drupal\Tests\Core\Form\TestForm';
    $object = new TestForm();
    $form = [];
    $form_state = new FormState();
    $expected_form = $object->buildForm($form, $form_state);

    $form = $this->formBuilder->getForm($form_id);
    $this->assertFormElement($expected_form, $form, 'test');
    $this->assertSame('test-form', $form['#id']);
  }

  /**
   * Tests the buildForm() method with a string based form ID.
   */
  public function testBuildFormWithString(): void {
    $form_id = 'test_form_id';
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The form class test_form_id could not be found or loaded.');
    $this->formBuilder->getForm($form_id);
  }

  /**
   * Tests the buildForm() method with a class name based form ID.
   */
  public function testBuildFormWithClassString(): void {
    $form_id = '\Drupal\Tests\Core\Form\TestForm';
    $object = new TestForm();
    $form = [];
    $form_state = new FormState();
    $expected_form = $object->buildForm($form, $form_state);

    $form = $this->formBuilder->buildForm($form_id, $form_state);
    $this->assertFormElement($expected_form, $form, 'test');
    $this->assertSame('test-form', $form['#id']);
  }

  /**
   * Tests the buildForm() method with a form object.
   */
  public function testBuildFormWithObject(): void {
    $form_id = 'test_form_id';
    $expected_form = $form_id();

    $form_arg = $this->getMockForm($form_id, $expected_form);

    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($form_arg, $form_state);
    $this->assertFormElement($expected_form, $form, 'test');
    $this->assertSame($form_id, $form_state->getBuildInfo()['form_id']);
    $this->assertArrayHasKey('#id', $form);
  }

  /**
   * Tests whether the triggering element is properly identified.
   *
   * @param string $element_value
   *   The input element "#value" value.
   * @param string $input_value
   *   The corresponding submitted input value.
   *
   * @covers ::buildForm
   *
   * @dataProvider providerTestBuildFormWithTriggeringElement
   */
  public function testBuildFormWithTriggeringElement($element_value, $input_value): void {
    $form_id = 'test_form_id';
    $expected_form = $form_id();

    $expected_form['actions']['other_submit'] = [
      '#type' => 'submit',
      '#value' => $element_value,
    ];

    $form_arg = $this->getMockForm($form_id, $expected_form, 2);
    $form_state = new FormState();
    $form_state->setProcessInput();
    $form_state->setUserInput(['form_id' => $form_id, 'op' => $input_value]);
    $this->request->setMethod('POST');
    $this->formBuilder->buildForm($form_arg, $form_state);

    $this->assertEquals($expected_form['actions']['other_submit']['#value'], $form_state->getTriggeringElement()['#value']);
  }

  /**
   * Data provider for ::testBuildFormWithTriggeringElement().
   */
  public static function providerTestBuildFormWithTriggeringElement() {
    $plain_text = 'Other submit value';
    $markup = 'Other submit <input> value';
    return [
      'plain-text' => [$plain_text, $plain_text],
      'markup' => [$markup, $markup],
      // Note: The input is always decoded, see
      // \Drupal\Core\Form\FormBuilder::buttonWasClicked, so we do not need to
      // escape the input.
      'escaped-markup' => [Html::escape($markup), $markup],
    ];
  }

  /**
   * Tests the rebuildForm() method for a POST submission.
   */
  public function testRebuildForm(): void {
    $form_id = 'test_form_id';
    $expected_form = $form_id();

    // The form will be built four times.
    $form_arg = $this->createMock('Drupal\Core\Form\FormInterface');
    $form_arg->expects($this->exactly(2))
      ->method('getFormId')
      ->willReturn($form_id);
    $form_arg->expects($this->exactly(4))
      ->method('buildForm')
      ->willReturn($expected_form);

    // Do an initial build of the form and track the build ID.
    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($form_arg, $form_state);
    $original_build_id = $form['#build_id'];

    $this->request->setMethod('POST');
    $form_state->setRequestMethod('POST');

    // Rebuild the form, and assert that the build ID has not changed.
    $form_state->setRebuild();
    $input['form_id'] = $form_id;
    $form_state->setUserInput($input);
    $form_state->addRebuildInfo('copy', ['#build_id' => TRUE]);
    $this->formBuilder->processForm($form_id, $form, $form_state);
    $this->assertSame($original_build_id, $form['#build_id']);
    $this->assertTrue($form_state->isCached());

    // Rebuild the form again, and assert that there is a new build ID.
    $form_state->setRebuildInfo([]);
    $form = $this->formBuilder->buildForm($form_arg, $form_state);
    $this->assertNotSame($original_build_id, $form['#build_id']);
    $this->assertTrue($form_state->isCached());
  }

  /**
   * Tests the rebuildForm() method for a GET submission.
   */
  public function testRebuildFormOnGetRequest(): void {
    $form_id = 'test_form_id';
    $expected_form = $form_id();

    // The form will be built four times.
    $form_arg = $this->createMock('Drupal\Core\Form\FormInterface');
    $form_arg->expects($this->exactly(2))
      ->method('getFormId')
      ->willReturn($form_id);
    $form_arg->expects($this->exactly(4))
      ->method('buildForm')
      ->willReturn($expected_form);

    // Do an initial build of the form and track the build ID.
    $form_state = new FormState();
    $form_state->setMethod('GET');
    $form = $this->formBuilder->buildForm($form_arg, $form_state);
    $original_build_id = $form['#build_id'];

    // Rebuild the form, and assert that the build ID has not changed.
    $form_state->setRebuild();
    $input['form_id'] = $form_id;
    $form_state->setUserInput($input);
    $form_state->addRebuildInfo('copy', ['#build_id' => TRUE]);
    $this->formBuilder->processForm($form_id, $form, $form_state);
    $this->assertSame($original_build_id, $form['#build_id']);
    $this->assertFalse($form_state->isCached());

    // Rebuild the form again, and assert that there is a new build ID.
    $form_state->setRebuildInfo([]);
    $form = $this->formBuilder->buildForm($form_arg, $form_state);
    $this->assertNotSame($original_build_id, $form['#build_id']);
    $this->assertFalse($form_state->isCached());
  }

  /**
   * Tests the getCache() method.
   */
  public function testGetCache(): void {
    $form_id = 'test_form_id';
    $expected_form = $form_id();
    $expected_form['#token'] = FALSE;

    // FormBuilder::buildForm() will be called twice, but the form object will
    // only be called once due to caching.
    $form_arg = $this->createMock('Drupal\Core\Form\FormInterface');
    $form_arg->expects($this->exactly(2))
      ->method('getFormId')
      ->willReturn($form_id);
    $form_arg->expects($this->once())
      ->method('buildForm')
      ->willReturn($expected_form);

    // Do an initial build of the form and track the build ID.
    $form_state = (new FormState())
      ->addBuildInfo('files', [['module' => 'node', 'type' => 'pages.inc']])
      ->setRequestMethod('POST')
      ->setCached();
    $form = $this->formBuilder->buildForm($form_arg, $form_state);

    $cached_form = $form;
    $cached_form['#cache_token'] = 'csrf_token';
    // The form cache, form_state cache, and CSRF token validation will only be
    // called on the cached form.
    $this->formCache->expects($this->once())
      ->method('getCache')
      ->willReturn($form);

    // The final form build will not trigger any actual form building, but will
    // use the form cache.
    $form_state->setExecuted();
    $input['form_id'] = $form_id;
    $input['form_build_id'] = $form['#build_id'];
    $form_state->setUserInput($input);
    $this->formBuilder->buildForm($form_arg, $form_state);
    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Tests that HTML IDs are unique when rebuilding a form with errors.
   */
  public function testUniqueHtmlId(): void {
    $form_id = 'test_form_id';
    $expected_form = $form_id();
    $expected_form['test']['#required'] = TRUE;

    // Mock a form object that will be built two times.
    $form_arg = $this->createMock('Drupal\Core\Form\FormInterface');
    $form_arg->expects($this->exactly(2))
      ->method('getFormId')
      ->willReturn($form_id);
    $form_arg->expects($this->exactly(2))
      ->method('buildForm')
      ->willReturn($expected_form);

    $form_state = new FormState();
    $form = $this->simulateFormSubmission($form_id, $form_arg, $form_state);
    $this->assertSame('test-form-id', $form['#id']);

    $form_state = new FormState();
    $form = $this->simulateFormSubmission($form_id, $form_arg, $form_state);
    $this->assertSame('test-form-id--2', $form['#id']);
  }

  /**
   * Tests that HTML IDs are unique between 2 forms with the same element names.
   */
  public function testUniqueElementHtmlId(): void {
    $form_id_1 = 'test_form_id';
    $form_id_2 = 'test_form_id_2';
    $expected_form = $form_id_1();

    // Mock 2 form objects that will be built once each.
    $form_arg_1 = $this->createMock('Drupal\Core\Form\FormInterface');
    $form_arg_1->expects($this->exactly(1))
      ->method('getFormId')
      ->willReturn($form_id_1);
    $form_arg_1->expects($this->exactly(1))
      ->method('buildForm')
      ->willReturn($expected_form);
    $form_arg_2 = $this->createMock('Drupal\Core\Form\FormInterface');
    $form_arg_2->expects($this->exactly(1))
      ->method('getFormId')
      ->willReturn($form_id_2);
    $form_arg_2->expects($this->exactly(1))
      ->method('buildForm')
      ->willReturn($expected_form);
    $form_state = new FormState();
    $form_1 = $this->simulateFormSubmission($form_id_1, $form_arg_1, $form_state);
    $form_state = new FormState();
    $form_2 = $this->simulateFormSubmission($form_id_2, $form_arg_2, $form_state);
    $this->assertNotSame($form_1['actions']["#id"], $form_2['actions']["#id"]);
  }

  /**
   * Tests that a cached form is deleted after submit.
   */
  public function testFormCacheDeletionCached(): void {
    $form_id = 'test_form_id';
    $form_build_id = $this->randomMachineName();

    $expected_form = $form_id();
    $expected_form['#build_id'] = $form_build_id;
    $form_arg = $this->getMockForm($form_id, $expected_form);
    $form_arg->expects($this->once())
      ->method('submitForm')
      ->willReturnCallback(function (array &$form, FormStateInterface $form_state) {
        // Mimic EntityForm by cleaning the $form_state upon submit.
        $form_state->cleanValues();
      });

    $this->formCache->expects($this->once())
      ->method('deleteCache')
      ->with($form_build_id);

    $form_state = new FormState();
    $form_state->setRequestMethod('POST');
    $form_state->setCached();
    $this->simulateFormSubmission($form_id, $form_arg, $form_state);
  }

  /**
   * Tests that an uncached form does not trigger cache set or delete.
   */
  public function testFormCacheDeletionUncached(): void {
    $form_id = 'test_form_id';
    $form_build_id = $this->randomMachineName();

    $expected_form = $form_id();
    $expected_form['#build_id'] = $form_build_id;
    $form_arg = $this->getMockForm($form_id, $expected_form);

    $this->formCache->expects($this->never())
      ->method('deleteCache');

    $form_state = new FormState();
    $this->simulateFormSubmission($form_id, $form_arg, $form_state);
  }

  /**
   * @covers ::buildForm
   */
  public function testExceededFileSize(): void {
    $request = new Request([FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request_stack = new RequestStack();
    $request_stack->push($request);
    $this->formBuilder = $this->getMockBuilder('\Drupal\Core\Form\FormBuilder')
      ->setConstructorArgs([$this->formValidator, $this->formSubmitter, $this->formCache, $this->moduleHandler, $this->eventDispatcher, $request_stack, $this->classResolver, $this->elementInfo, $this->themeManager, $this->csrfToken])
      ->onlyMethods(['getFileUploadMaxSize'])
      ->getMock();
    $this->formBuilder->expects($this->once())
      ->method('getFileUploadMaxSize')
      ->willReturn(33554432);

    $form_arg = $this->getMockForm('test_form_id');
    $form_state = new FormState();

    $this->expectException(BrokenPostRequestException::class);
    $this->formBuilder->buildForm($form_arg, $form_state);
  }

  /**
   * @covers ::buildForm
   */
  public function testPostAjaxRequest(): void {
    $request = new Request([FormBuilderInterface::AJAX_FORM_REQUEST => TRUE], ['form_id' => 'different_form_id']);
    $request->setMethod('POST');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->requestStack->push($request);

    $form_state = (new FormState())
      ->setUserInput([FormBuilderInterface::AJAX_FORM_REQUEST => TRUE])
      ->setMethod('get')
      ->setAlwaysProcess()
      ->disableRedirect()
      ->set('ajax', TRUE);

    $form_id = '\Drupal\Tests\Core\Form\TestForm';
    $expected_form = (new TestForm())->buildForm([], $form_state);

    $form = $this->formBuilder->buildForm($form_id, $form_state);
    $this->assertFormElement($expected_form, $form, 'test');
    $this->assertSame('test-form', $form['#id']);
  }

  /**
   * @covers ::buildForm
   */
  public function testGetAjaxRequest(): void {
    $request = new Request([FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]);
    $request->query->set('form_id', 'different_form_id');
    $request->setMethod('GET');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->requestStack->push($request);

    $form_state = (new FormState())
      ->setUserInput([FormBuilderInterface::AJAX_FORM_REQUEST => TRUE])
      ->setMethod('get')
      ->setAlwaysProcess()
      ->disableRedirect()
      ->set('ajax', TRUE);

    $form_id = '\Drupal\Tests\Core\Form\TestForm';
    $expected_form = (new TestForm())->buildForm([], $form_state);

    $form = $this->formBuilder->buildForm($form_id, $form_state);
    $this->assertFormElement($expected_form, $form, 'test');
    $this->assertSame('test-form', $form['#id']);
  }

  /**
   * @covers ::buildForm
   *
   * @dataProvider providerTestChildAccessInheritance
   */
  public function testChildAccessInheritance($element, $access_checks): void {
    $form_arg = new TestFormWithPredefinedForm();
    $form_arg->setForm($element);

    $form_state = new FormState();

    $form = $this->formBuilder->buildForm($form_arg, $form_state);

    $actual_access_structure = [];
    $expected_access_structure = [];

    // Ensure that the expected access checks are set.
    foreach ($access_checks as $access_check) {
      $parents = $access_check[0];
      $parents[] = '#access';

      $actual_access = NestedArray::getValue($form, $parents);
      $actual_access_structure[] = [$parents, $actual_access];
      $expected_access_structure[] = [$parents, $access_check[1]];
    }

    $this->assertEquals($expected_access_structure, $actual_access_structure);
  }

  /**
   * Data provider for testChildAccessInheritance.
   *
   * @return array
   *   An array of test cases, each containing a form element structure and
   *   its expected access results.
   */
  public static function providerTestChildAccessInheritance() {
    $data = [];

    $element = [
      'child0' => [
        '#type' => 'checkbox',
      ],
      'child1' => [
        '#type' => 'checkbox',
      ],
      'child2' => [
        '#type' => 'fieldset',
        'child2.0' => [
          '#type' => 'checkbox',
        ],
        'child2.1' => [
          '#type' => 'checkbox',
        ],
        'child2.2' => [
          '#type' => 'checkbox',
        ],
      ],
    ];

    // Sets access FALSE on the root level, this should be inherited completely.
    $clone = $element;
    $clone['#access'] = FALSE;

    $expected_access = [];
    $expected_access[] = [[], FALSE];
    $expected_access[] = [['child0'], FALSE];
    $expected_access[] = [['child1'], FALSE];
    $expected_access[] = [['child2'], FALSE];
    $expected_access[] = [['child2', 'child2.0'], FALSE];
    $expected_access[] = [['child2', 'child2.1'], FALSE];
    $expected_access[] = [['child2', 'child2.2'], FALSE];

    $data['access-false-root'] = [$clone, $expected_access];

    $clone = $element;
    $access_result = AccessResult::forbidden();
    $clone['#access'] = $access_result;

    $expected_access = [];
    $expected_access[] = [[], $access_result];
    $expected_access[] = [['child0'], $access_result];
    $expected_access[] = [['child1'], $access_result];
    $expected_access[] = [['child2'], $access_result];
    $expected_access[] = [['child2', 'child2.0'], $access_result];
    $expected_access[] = [['child2', 'child2.1'], $access_result];
    $expected_access[] = [['child2', 'child2.2'], $access_result];

    $data['access-forbidden-root'] = [$clone, $expected_access];

    // Allow access on the most outer level but set FALSE otherwise.
    $clone = $element;
    $clone['#access'] = TRUE;
    $clone['child0']['#access'] = FALSE;

    $expected_access = [];
    $expected_access[] = [[], TRUE];
    $expected_access[] = [['child0'], FALSE];
    $expected_access[] = [['child1'], NULL];
    $expected_access[] = [['child2'], NULL];
    $expected_access[] = [['child2', 'child2.0'], NULL];
    $expected_access[] = [['child2', 'child2.1'], NULL];
    $expected_access[] = [['child2', 'child2.2'], NULL];

    $data['access-true-root'] = [$clone, $expected_access];

    // Allow access on the most outer level but forbid otherwise.
    $clone = $element;
    $access_result_allowed = AccessResult::allowed();
    $clone['#access'] = $access_result_allowed;
    $access_result_forbidden = AccessResult::forbidden();
    $clone['child0']['#access'] = $access_result_forbidden;

    $expected_access = [];
    $expected_access[] = [[], $access_result_allowed];
    $expected_access[] = [['child0'], $access_result_forbidden];
    $expected_access[] = [['child1'], NULL];
    $expected_access[] = [['child2'], NULL];
    $expected_access[] = [['child2', 'child2.0'], NULL];
    $expected_access[] = [['child2', 'child2.1'], NULL];
    $expected_access[] = [['child2', 'child2.2'], NULL];

    $data['access-allowed-root'] = [$clone, $expected_access];

    // Allow access on the most outer level, deny access on a parent, and allow
    // on a child. The denying should be inherited.
    $clone = $element;
    $clone['#access'] = TRUE;
    $clone['child2']['#access'] = FALSE;
    $clone['child2.0']['#access'] = TRUE;
    $clone['child2.1']['#access'] = TRUE;
    $clone['child2.2']['#access'] = TRUE;

    $expected_access = [];
    $expected_access[] = [[], TRUE];
    $expected_access[] = [['child0'], NULL];
    $expected_access[] = [['child1'], NULL];
    $expected_access[] = [['child2'], FALSE];
    $expected_access[] = [['child2', 'child2.0'], FALSE];
    $expected_access[] = [['child2', 'child2.1'], FALSE];
    $expected_access[] = [['child2', 'child2.2'], FALSE];

    $data['access-mixed-parents'] = [$clone, $expected_access];

    $clone = $element;
    $clone['#access'] = $access_result_allowed;
    $clone['child2']['#access'] = $access_result_forbidden;
    $clone['child2.0']['#access'] = $access_result_allowed;
    $clone['child2.1']['#access'] = $access_result_allowed;
    $clone['child2.2']['#access'] = $access_result_allowed;

    $expected_access = [];
    $expected_access[] = [[], $access_result_allowed];
    $expected_access[] = [['child0'], NULL];
    $expected_access[] = [['child1'], NULL];
    $expected_access[] = [['child2'], $access_result_forbidden];
    $expected_access[] = [['child2', 'child2.0'], $access_result_forbidden];
    $expected_access[] = [['child2', 'child2.1'], $access_result_forbidden];
    $expected_access[] = [['child2', 'child2.2'], $access_result_forbidden];

    $data['access-mixed-parents-object'] = [$clone, $expected_access];

    return $data;
  }

  /**
   * @covers ::valueCallableIsSafe
   *
   * @dataProvider providerTestValueCallableIsSafe
   */
  public function testValueCallableIsSafe($callback, $expected): void {
    $method = new \ReflectionMethod(FormBuilder::class, 'valueCallableIsSafe');
    $is_safe = $method->invoke($this->formBuilder, $callback);
    $this->assertSame($expected, $is_safe);
  }

  public static function providerTestValueCallableIsSafe() {
    $data = [];
    $data['string_no_slash'] = [
      'Drupal\Core\Render\Element\Token::valueCallback',
      TRUE,
    ];
    $data['string_with_slash'] = [
      '\Drupal\Core\Render\Element\Token::valueCallback',
      TRUE,
    ];
    $data['array_no_slash'] = [
      ['Drupal\Core\Render\Element\Token', 'valueCallback'],
      TRUE,
    ];
    $data['array_with_slash'] = [
      ['\Drupal\Core\Render\Element\Token', 'valueCallback'],
      TRUE,
    ];
    $data['closure'] = [
      function () {},
      FALSE,
    ];
    return $data;
  }

  /**
   * @covers ::doBuildForm
   *
   * @dataProvider providerTestInvalidToken
   */
  public function testInvalidToken($expected, $valid_token, $user_is_authenticated): void {
    $form_token = 'the_form_token';
    $form_id = 'test_form_id';

    if (is_bool($valid_token)) {
      $this->csrfToken->expects($this->any())
        ->method('get')
        ->willReturnArgument(0);
      $this->csrfToken->expects($this->atLeastOnce())
        ->method('validate')
        ->willReturn($valid_token);
    }

    $current_user = $this->prophesize(AccountInterface::class);
    $current_user->isAuthenticated()->willReturn($user_is_authenticated);
    $property = new \ReflectionProperty(FormBuilder::class, 'currentUser');
    $property->setValue($this->formBuilder, $current_user->reveal());

    $expected_form = $form_id();
    $form_arg = $this->getMockForm($form_id, $expected_form);

    // Set up some request data so we can be sure it is removed when a token is
    // invalid.
    $this->request->request->set('foo', 'bar');
    $_POST['foo'] = 'bar';

    $form_state = new FormState();
    $input['form_id'] = $form_id;
    $input['form_token'] = $form_token;
    $input['test'] = 'example-value';
    $form_state->setUserInput($input);
    $form = $this->simulateFormSubmission($form_id, $form_arg, $form_state, FALSE);
    $this->assertSame($expected, $form_state->hasInvalidToken());
    if ($expected) {
      $this->assertEmpty($form['test']['#value']);
      $this->assertEmpty($form_state->getValue('test'));
      $this->assertEmpty($_POST);
      $this->assertEmpty(iterator_to_array($this->request->request->getIterator()));
    }
    else {
      $this->assertEquals('example-value', $form['test']['#value']);
      $this->assertEquals('example-value', $form_state->getValue('test'));
      $this->assertEquals('bar', $_POST['foo']);
      $this->assertEquals('bar', $this->request->request->get('foo'));
    }
  }

  public static function providerTestInvalidToken() {
    $data = [];
    $data['authenticated_invalid'] = [TRUE, FALSE, TRUE];
    $data['authenticated_valid'] = [FALSE, TRUE, TRUE];
    // If the user is not authenticated, we will not have a token.
    $data['anonymous'] = [FALSE, NULL, FALSE];
    return $data;
  }

  /**
   * @covers ::prepareForm
   *
   * @dataProvider providerTestFormTokenCacheability
   */
  public function testFormTokenCacheability($token, $is_authenticated, $method, $opted_in_for_cache): void {
    $user = $this->prophesize(AccountProxyInterface::class);
    $user->isAuthenticated()
      ->willReturn($is_authenticated);
    $this->container->set('current_user', $user->reveal());
    \Drupal::setContainer($this->container);

    $form_id = 'test_form_id';
    $form = $form_id();
    $form['#method'] = $method;

    if (isset($token)) {
      $form['#token'] = $token;
    }

    if ($opted_in_for_cache) {
      $form['#cache']['max-age'] = Cache::PERMANENT;
    }

    $form_arg = $this->createMock('Drupal\Core\Form\FormInterface');
    $form_arg->expects($this->once())
      ->method('getFormId')
      ->willReturn($form_id);
    $form_arg->expects($this->once())
      ->method('buildForm')
      ->willReturn($form);

    $form_state = new FormState();
    $built_form = $this->formBuilder->buildForm($form_arg, $form_state);

    // FormBuilder does not set a form token when:
    // - #token is set to FALSE.
    // - #method is set to 'GET' and #token is not a string. This means the GET
    //   form did not get a form token by default, and the form did not
    //   explicitly opt in.
    if ($token === FALSE || ($method == 'get' && !is_string($token))) {
      $this->assertEquals($built_form['#cache'], ['tags' => ['CACHE_MISS_IF_UNCACHEABLE_HTTP_METHOD:form']]);
      $this->assertFalse(isset($built_form['form_token']));
    }
    // Otherwise, a form token is set, but only if the user is logged in. It is
    // impossible (and unnecessary) to set a form token if the user is not
    // logged in, because there is no session, and hence no CSRF token.
    else {
      // For forms that are eligible for form tokens, a cache context must be
      // set that indicates the form token only exists for logged in users.
      $this->assertTrue(isset($built_form['#cache']));
      $expected_cacheability_metadata = [
        'tags' => ['CACHE_MISS_IF_UNCACHEABLE_HTTP_METHOD:form'],
        'contexts' => ['user.roles:authenticated'],
      ];
      if ($opted_in_for_cache) {
        $expected_cacheability_metadata['max-age'] = Cache::PERMANENT;
      }
      $this->assertEquals($expected_cacheability_metadata, $built_form['#cache']);
      // Finally, verify that a form token is generated when appropriate, with
      // the expected cacheability metadata (or lack thereof).
      if (!$is_authenticated) {
        $this->assertFalse(isset($built_form['form_token']));
      }
      else {
        $this->assertTrue(isset($built_form['form_token']));
        if ($opted_in_for_cache) {
          $this->assertFalse(isset($built_form['form_token']['#cache']));
        }
        else {
          $this->assertEquals(['max-age' => 0], $built_form['form_token']['#cache']);
        }
      }
    }
  }

  /**
   * Data provider for testFormTokenCacheability.
   *
   * @return array
   *   An array of test cases, each containing a form token, the authentication,
   *   request method, and expected cacheability outcome.
   */
  public static function providerTestFormTokenCacheability() {
    return [
      'token:none,authenticated:true' => [NULL, TRUE, 'post', FALSE],
      'token:none,authenticated:true,opted_in_for_cache' => [NULL, TRUE, 'post', TRUE],
      'token:none,authenticated:false' => [NULL, FALSE, 'post', FALSE],
      'token:false,authenticated:false' => [FALSE, FALSE, 'post', FALSE],
      'token:false,authenticated:true' => [FALSE, TRUE, 'post', FALSE],
      'token:none,authenticated:false,method:get' => [NULL, FALSE, 'get', FALSE],
      'token:test_form_id,authenticated:false,method:get' => ['test_form_id', TRUE, 'get', FALSE],
    ];
  }

  /**
   * Tests the detection of the triggering element.
   */
  public function testTriggeringElement(): void {
    $form_arg = 'Drupal\Tests\Core\Form\TestForm';

    // No triggering element.
    $form_state = new FormState();
    $this->formBuilder->buildForm($form_arg, $form_state);
    $this->assertNull($form_state->getTriggeringElement());

    // When no op is provided, default to the first button element.
    $form_state = new FormState();
    $form_state->setMethod('GET');
    $form_state->setUserInput(['form_id' => 'test_form']);
    $this->formBuilder->buildForm($form_arg, $form_state);
    $triggeringElement = $form_state->getTriggeringElement();
    $this->assertIsArray($triggeringElement);
    $this->assertSame('op', $triggeringElement['#name']);
    $this->assertSame('Submit', $triggeringElement['#value']);

    // A single triggering element.
    $form_state = new FormState();
    $form_state->setMethod('GET');
    $form_state->setUserInput(['form_id' => 'test_form', 'op' => 'Submit']);
    $this->formBuilder->buildForm($form_arg, $form_state);
    $triggeringElement = $form_state->getTriggeringElement();
    $this->assertIsArray($triggeringElement);
    $this->assertSame('op', $triggeringElement['#name']);

    // A different triggering element.
    $form_state = new FormState();
    $form_state->setMethod('GET');
    $form_state->setUserInput(['form_id' => 'test_form', 'other_action' => 'Other action']);
    $this->formBuilder->buildForm($form_arg, $form_state);
    $triggeringElement = $form_state->getTriggeringElement();
    $this->assertIsArray($triggeringElement);
    $this->assertSame('other_action', $triggeringElement['#name']);

    // Two triggering elements.
    $form_state = new FormState();
    $form_state->setMethod('GET');
    $form_state->setUserInput(['form_id' => 'test_form', 'op' => 'Submit', 'other_action' => 'Other action']);
    $this->formBuilder->buildForm($form_arg, $form_state);

    // Verify that only the first triggering element is respected.
    $triggeringElement = $form_state->getTriggeringElement();
    $this->assertIsArray($triggeringElement);
    $this->assertSame('op', $triggeringElement['#name']);
  }

}

/**
 * Basic test form with interface implemented.
 */
class TestForm implements FormInterface {

  public function getFormId() {
    return 'test_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    return test_form_id();
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {}

  public function submitForm(array &$form, FormStateInterface $form_state) {}

}

/**
 * Basic test form with container injection interface implemented.
 */
class TestFormInjected extends TestForm implements ContainerInjectionInterface {

  public static function create(ContainerInterface $container) {
    return new static();
  }

}

/**
 * Basic test form with predefined form set.
 */
class TestFormWithPredefinedForm extends TestForm {

  /**
   * @var array
   */
  protected $form;

  public function setForm($form): void {
    $this->form = $form;
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    return $this->form;
  }

}
