<?php

namespace Drupal\registrar_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Course Deadlines' block.
 *
 * @Block(
 *   id = "course_deadlines_block",
 *   admin_label = @Translation("Course Deadlines"),
 *   category = @Translation("Site custom")
 * )
 */
class CourseDeadlinesBlock extends BlockBase implements ContainerFactoryPluginInterface, FormInterface {
  use StringTranslationTrait;

  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new CourseDeadlinesBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maui = $maui;
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uiowa_maui.api'),
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'course_deadlines_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Add any validation if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle form submission if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['course_deadlines_description'] = [
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      '#markup' => $this->t('This block displays course deadline information.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = $this->formBuilder->getForm($this);

    $build = [
      'wrapper' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['list-container__inner'],
        ],
        'form' => $form,
      ],
    ];

    return $build;
  }

  /**
   * Builds the form elements for the block.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    // If the form has been interacted with, we'll have a triggering element
    // to deal with and determine what should be reset or not.
    if ($trigger = $form_state->getTriggeringElement()) {
      $trigger = $trigger['#name'];
    }
    // For each form interaction, we want to reset any fields
    // below the most recent interaction.
    $session = $form_state->getValue('session');
    $department = ($trigger === 'session') ? NULL : $form_state->getValue('department');
    $course = (in_array($trigger, ['session', 'department'])) ? NULL : $form_state->getValue('course');
    $section = (in_array($trigger, ['session', 'department', 'course'])) ? NULL : $form_state->getValue('section');

    $form['#id'] = 'uiowa-maui-course-deadlines-form';

    $form['deadlines'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'uiowa-maui-course-deadlines',
      ],
    ];

    $form['deadlines']['session'] = [
      '#type' => 'select',
      '#title' => $this->t('Session'),
      '#description' => $this->t('Select a session to auto-populate the department dropdown options.'),
      '#empty_option' => '- Session -',
      '#options' => $this->sessionOptions(4, 4, TRUE),
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'uiowa-maui-course-deadlines',
      ],
      '#prefix' => '<div id="uiowa-maui-course-deadlines-session-dropdown" class="uiowa-maui-form-wrapper">',
      '#suffix' => '</div>',
      '#validated' => TRUE,
    ];

    $form['deadlines']['department'] = [
      '#type' => 'select',
      '#title' => $this->t('Department'),
      '#description' => $this->t('Select a department to auto-populate the course dropdown options.'),
      '#empty_option' => '- Department -',
      '#options' => $this->departmentOptions(),
      '#default_value' => $department ?? NULL,
      '#prefix' => '<div id="uiowa-maui-course-deadlines-department-dropdown" class="uiowa-maui-form-wrapper">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'uiowa-maui-course-deadlines',
      ],
      '#disabled' => empty($session),
      '#validated' => TRUE,
    ];

    $form['deadlines']['course'] = [
      '#type' => 'select',
      '#title' => $this->t('Course'),
      '#description' => $this->t('Select a course to auto-populate the section dropdown options.'),
      '#empty_option' => '- Course -',
      '#options' => $this->courseOptions($session, $department),
      '#default_value' => $course ?? NULL,
      '#prefix' => '<div id="uiowa-maui-course-deadlines-course-dropdown" class="uiowa-maui-form-wrapper">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'uiowa-maui-course-deadlines',
      ],
      '#disabled' => empty($department),
      '#validated' => TRUE,
    ];

    // If there is only a single section,
    // we want to go ahead and auto-select it.
    $section_options = $this->sectionOptions($session, $department, $course);
    if (count($section_options) === 1) {
      $section = key($section_options);
      // Get the current user input and overwrite the section
      // and then re-set into the form as faux input.
      $values = $form_state->getUserInput();
      $values['section'] = $section;
      $form_state->setUserInput($values);
    }

    $form['deadlines']['section'] = [
      '#type' => 'select',
      '#title' => $this->t('Section'),
      '#description' => $this->t('Select a section to display course deadline information.'),
      '#empty_option' => '- Section -',
      '#options' => $section_options,
      '#default_value' => $section,
      '#prefix' => '<div id="uiowa-maui-course-deadlines-section-dropdown" class="uiowa-maui-form-wrapper">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'uiowa-maui-course-deadlines',
      ],
      '#disabled' => empty($course),
      '#validated' => TRUE,
    ];

    $form['deadlines']['deadlines'] = [
      '#prefix' => '<div id="uiowa-maui-course-deadlines-content" aria-live="assertive" aria-describedby="uiowa-maui-course-deadlines-session-dropdown uiowa-maui-course-deadlines-department-dropdown uiowa-maui-course-deadlines-course-dropdown uiowa-maui-course-deadlines-section-dropdown">',
      '#suffix' => '</div>',
      'deadlines' => $this->deadlinesMarkup($session, $department, $course, $section),
    ];

    return $form;
  }

  /**
   * AJAX callback for the form.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Helper function to generate select list options for sessions.
   *
   * @param int $previous
   *   How many sessions to go backwards.
   * @param int $future
   *   How many sessions to go forwards.
   * @param bool $legacy
   *   Whether the id or legacyCode key should be used.
   *
   * @return array
   *   Array of select list options.
   */
  private function sessionOptions(int $previous = 4, int $future = 4, bool $legacy = TRUE): array {
    $sessions = $this->maui->getSessionsBounded($previous, $future);
    $options = [];

    $key = ($legacy) ? 'legacyCode' : 'id';

    foreach ($sessions as $session) {
      $options[$session->$key] = $session->shortDescription;
    }

    return $options;
  }

  /**
   * Helper function to generate select list options for departments.
   */
  private function departmentOptions() : array {
    $departments = $this->maui->getCourseSubjects();
    if (empty($departments)) {
      return [];
    }

    $options = [];
    foreach ($departments as $department) {
      $options[$department->naturalKey] = $department->naturalKey;
    }

    return $options;
  }

  /**
   * Course dropdown option callback.
   *
   * GET /pub/registrar/course/dropdown/{session}/{subject}.
   *
   * @param string $session
   *   No documentation in MAUI.
   * @param string $department
   *   No documentation in MAUI.
   *
   * @return array
   *   JSON decoded array of response data.
   */
  private function courseOptions($session, $department): array {
    $options = [];

    if (empty($session) || empty($department)) {
      return $options;
    }
    // In MAUI, department and subject refer to the same thing here.
    $endpoint = "pub/registrar/course/dropdown/{$session}/{$department}";
    if ($data = $this->maui->request('GET', $endpoint)) {
      sort($data);
      foreach ($data as $course) {
        $options[$course] = $course;
      }
    }
    return $options;
  }

  /**
   * Section dropdown option callback.
   *
   * GET /pub/registrar/sections/dropdown/{session}/{subject}/{course}.
   *
   * @param string $session
   *   No documentation in MAUI.
   * @param string $department
   *   No documentation in MAUI.
   * @param string $course
   *   No documentation in MAUI.
   *
   * @return array
   *   JSON decoded array of response data.
   */
  private function sectionOptions($session, $department, $course): array {
    $options = [];
    if (empty($session) || empty($department) || empty($course)) {
      return $options;
    }
    $endpoint = "pub/registrar/sections/dropdown/{$session}/{$department}/{$course}";
    if ($data = $this->maui->request('GET', $endpoint)) {
      foreach ($data as $section) {
        $options[$section->sectionId] = $section->sectionNumber;
      }
    }
    return $options;
  }

  /**
   * Deadlines markup callback.
   */
  public function deadlinesMarkup($session, $department, $course, $section): array {
    $deadlines = [];

    if (!empty($session) && !empty($department) && !empty($course) && !empty($section)) {
      $exclude = [
        'lastDays' => FALSE,
        'prereq' => TRUE,
        'enrollments' => TRUE,
        'restrictions' => TRUE,
      ];

      $data = $this->maui->getSection($section, $exclude);
      if (empty($data)) {
        return $deadlines;
      }

      $deadlines['title_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['uiowa-maui-course-deadlines-wrapper', 'title-wrapper'],
        ],
        'subject_course_section' => [
          '#prefix' => '<span class="uiowa-maui-subject-course-section">',
          '#suffix' => '</span>',
          '#markup' => $this->t('@subject_course:@section_number', [
            '@subject_course' => $data->subjectCourse,
            '@section_number' => $data->sectionNumber,
          ]),
        ],
        'title' => [
          '#prefix' => '<span class="uiowa-maui-course-title">',
          '#suffix' => '</span>',
          '#markup' => $this->t('@title', [
            '@title' => $data->courseTitle ?? '',
          ]),
        ],
      ];

      if ($data->subTitle) {
        $deadlines['title_wrapper']['subtitle'] = [
          '#prefix' => '<span class="uiowa-maui-course-subtitle">',
          '#suffix' => '</span>',
          '#markup' => $this->t('@subtitle', [
            '@subtitle' => $data->subTitle,
          ]),
        ];
      }

      if ($data->isIndependentStudySection) {
        $deadlines['independent_study_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['uiowa-maui-course-deadlines-wrapper', 'independent-study-wrapper'],
          ],
          'independent_study' => [
            '#prefix' => '<span class="uiowa-maui-independent-study">',
            '#suffix' => '</span>',
            '#markup' => $this->t('This is an independent study course.'),
          ],
        ];
      }

      if ($data->offcycle) {
        $deadlines['offcycle_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['uiowa-maui-course-deadlines-wrapper', 'offcycle-wrapper'],
          ],
          'offcycle' => [
            '#prefix' => '<span class="uiowa-maui-offcycle">',
            '#suffix' => '</span>',
            '#markup' => $this->t('This is an off-cycle course.'),
          ],
        ];
      }

      $items = [];

      $dates = [
        'beginDate' => 'Begin date',
        'endDate' => 'End date',
        'lastDayToDropOrReduceHoursWithTuitionReduction' => 'Last day for tuition & fee reduction if you drop the course or reduce hours (See Note below)',
        'lastDayToAddDropNoFee' => 'Last day to add or drop the course without $12 charge',
        'lastDayToAddWithoutDeanApproval' => "Last day to add without collegiate approval",
        'lastDayToDropWithoutW' => 'Last day to drop without a "W"',
        'lastDayToDropWithoutDeanApprovalUndergrad' => "Last day to drop without collegiate approval, undergraduate",
        'lastDayToDropWithoutDeanApprovalGrad' => "Last day to drop without collegiate approval, graduate",
      ];

      // Remove $12 charge field for sessions after Fall 2016.
      // This field is still being populated in MAUI
      // but is no longer valid.
      if ($session >= '20163') {
        unset($dates['lastDayToAddDropNoFee']);
      }

      foreach ($dates as $key => $label) {
        if ($data->$key) {
          $items[] = [
            '#markup' => $this->t('<span class="uiowa-maui-deadline-label">@label</span> <span class="uiowa-maui-deadline-date">@date</span>', [
              '@label' => $label,
              '@date' => date('m/d/Y', strtotime($data->$key)),
            ]),
          ];
        }
      }

      $deadlines['deadlines_wrapper'] = [
        '#type' => 'container',
        '#suffix' => '<p style="text-align: center;"><a href="https://registrar.uiowa.edu/collegiate-office-contact-information-students">Collegiate Office Contact Information</a> to obtain collegiate approval.</p>',
        '#attributes' => [
          'class' => ['uiowa-maui-course-deadlines-wrapper', 'deadlines-wrapper'],
        ],
        'deadlines' => [
          '#title' => 'Deadlines',
          '#items' => $items,
          '#theme' => 'item_list',
          '#attributes' => [
            'class' => 'uiowa-maui-deadlines',
          ],
        ],
      ];

      $times_and_locations = [];

      foreach ($data->timeAndLocations as $key => $value) {
        if ($value->arrangedTime) {
          $time = 'Time is to be arranged.';
        }
        else {
          $time = $this->t('@days @start@end', [
            '@days' => $value->days ?? '',
            '@start' => $value->startTime ?? '',
            '@end' => ($value->endTime) ? '-' . $value->endTime : '',
          ]);
        }

        $location = NULL;

        if ($value->arrangedLocation) {
          $location = 'Location is to be arranged.';
        }
        elseif ($value->offsite) {
          $location = $this->t('@building @street @city @country', [
            '@building' => $value->offsiteBuilding ?? '',
            '@street' => $value->offsiteStreet ?? '',
            '@city' => $value->offsiteCity ?? '',
            '@country' => $value->offsiteCountry ?? '',
          ]);
        }
        else {
          if ($value->building) {
            // @todo Update this to use a source other than data.its.
            $lookup = json_decode(file_get_contents('https://data.its.uiowa.edu/maps/number-lookup'));
            $building = strtolower($value->building);

            // Create a link if we were able to pull a building number,
            // else just output as plain text.
            if ($lookup?->$building) {
              $location = $this->t('@room <a href="@url">@building</a>', [
                '@room' => $value->room ?? '',
                '@url' => Url::fromUri('https://www.facilities.uiowa.edu/building/' . $lookup->$building)->toString(),
                '@building' => $value->building,
              ]);
            }
            else {
              $location = $this->t('@room @building', [
                '@room' => $value->room ?? '',
                '@building' => $value->building,
              ]);
            }
          }
        }

        $times_and_locations[] = ['#markup' => implode(' ', [$time, $location])];
      }

      if (!empty($times_and_locations)) {
        $deadlines['times_and_locations_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['uiowa-maui-course-deadlines-wrapper', 'times-and-locations-wrapper'],
          ],
          'times_and_locations' => [
            '#title' => 'Times and Locations',
            '#items' => $times_and_locations,
            '#theme' => 'item_list',
            '#attributes' => [
              'class' => 'uiowa-maui-times-and-locations',
            ],
          ],
        ];
      }

      $instructors = [];

      foreach ($data->instructors as $instructor) {
        $instructors[] = $this->t('@name (@role)', [
          '@name' => $instructor->name,
          '@role' => $instructor->role,
        ]);
      }

      if (!empty($instructors)) {
        $deadlines['instructors_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['uiowa-maui-course-deadlines-wrapper', 'instructors-wrapper'],
          ],
          'instructors' => [
            '#title' => 'Instructors',
            '#items' => $instructors,
            '#theme' => 'item_list',
            '#attributes' => [
              'class' => 'uiowa-maui-instructors',
            ],
          ],
        ];
      }
    }

    return $deadlines;
  }

}
