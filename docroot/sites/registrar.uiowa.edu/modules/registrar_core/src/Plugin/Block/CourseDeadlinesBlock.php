<?php

namespace Drupal\registrar_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
  public function getFormId() {
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
  public function defaultConfiguration() {
    // @todo Get rid of this if we don't end up
    //   needing block configuration.
    return [] + parent::defaultConfiguration();
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
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    // Attach the library for the deadlines block.
    $build['#attached']['library'][] = 'registrar_core/course-deadlines';

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

    $session = $form_state->getValue('session');
    $department = $form_state->getValue('department');
    $course = $form_state->getValue('course');
    $section = $form_state->getValue('section');

    $form['#id'] = 'uiowa-maui-course-deadlines-form';

    $form['session'] = [
      '#type' => 'select',
      '#title' => $this->t('Session'),
      '#description' => $this->t('Select a session to auto-populate the department dropdown options.'),
      '#empty_option' => '- Session -',
      '#options' => $this->sessionOptions(4, 4, TRUE),
      '#ajax' => [
        'callback' => [$this, 'sessionDropdownCallback'],
        'wrapper' => 'uiowa-maui-course-deadlines-department-dropdown',
      ],
      '#prefix' => '<div id="uiowa-maui-course-deadlines-session-dropdown" class="uiowa-maui-form-wrapper">',
      '#suffix' => '</div>',
      '#validated' => TRUE,
    ];

    $form['department'] = [
      '#type' => 'select',
      '#title' => $this->t('Department'),
      '#description' => $this->t('Select a department to auto-populate the course dropdown options.'),
      '#empty_option' => '- Department -',
      '#options' => $this->departmentOptions(),
      '#default_value' => $department ?? NULL,
      '#prefix' => '<div id="uiowa-maui-course-deadlines-department-dropdown" class="uiowa-maui-form-wrapper">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'departmentDropdownCallback'],
        'wrapper' => 'uiowa-maui-course-deadlines-course-dropdown',
      ],
      '#disabled' => !isset($session),
      '#validated' => TRUE,
    ];

    $form['course'] = [
      '#type' => 'select',
      '#title' => $this->t('Course'),
      '#description' => $this->t('Select a course to auto-populate the section dropdown options.'),
      '#empty_option' => '- Course -',
      '#options' => $this->courseOptions($form_state),
      '#default_value' => $course ?? NULL,
      '#prefix' => '<div id="uiowa-maui-course-deadlines-course-dropdown" class="uiowa-maui-form-wrapper">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'courseDropdownCallback'],
        'wrapper' => 'uiowa-maui-course-deadlines-section-dropdown',
      ],
      '#disabled' => !isset($department),
      '#validated' => TRUE,
    ];

    $form['section'] = [
      '#type' => 'select',
      '#title' => $this->t('Section'),
      '#description' => $this->t('Select a section to display course deadline information.'),
      '#empty_option' => '- Section -',
      '#options' => $this->sectionOptions($session, $department, $course),
      '#default_value' => $section ?? NULL,
      '#prefix' => '<div id="uiowa-maui-course-deadlines-section-dropdown" class="uiowa-maui-form-wrapper">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'deadlinesCallback'],
        'wrapper' => 'uiowa-maui-course-deadlines',
      ],
      '#disabled' => !isset($course),
      '#validated' => TRUE,
    ];

    $form['deadlines'] = [
      '#prefix' => '<div id="uiowa-maui-course-deadlines" aria-live="assertive" aria-describedby="uiowa-maui-course-deadlines-session-dropdown uiowa-maui-course-deadlines-department-dropdown uiowa-maui-course-deadlines-course-dropdown uiowa-maui-course-deadlines-section-dropdown">',
      '#suffix' => '</div>',
      'deadlines' => $this->deadlinesMarkup($session, $department, $course, $section),
    ];

    return $form;
  }

  /**
   * Sessions dropdown AJAX callback.
   */
  public function sessionDropdownCallback($form, $form_state) {
    return $form['department'];
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
  private function sessionOptions($previous = 4, $future = 4, $legacy = TRUE) {
    $sessions = $this->maui->getSessionsBounded($previous, $future);
    $options = [];

    $key = ($legacy) ? 'legacyCode' : 'id';

    foreach ($sessions as $session) {
      $options[$session->$key] = $session->shortDescription;
    }

    return $options;
  }

  /**
   * Department dropdown callback.
   */
  public function departmentDropdownCallback($form, $form_state) {
    return $form['course'];
  }

  /**
   * Helper function to generate select list options for departments.
   */
  private function departmentOptions() {
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
   */
  private function courseOptions($form_state) {
    $session = $form_state->getValue('session');
    $department = $form_state->getValue('department');
    $options = [];

    if (!empty($session) && !empty($department)) {
      if ($courses = $this->fetchCourseDropdown($session, $department)) {
        foreach ($courses as $course) {
          $options[$course] = $course;
        }
      }
    }

    return $options;
  }

  /**
   * Provide a list of values for a course number dropdown.
   *
   * GET /pub/registrar/course/dropdown/{session}/{subject}.
   *
   * @param string $session
   *   No documentation in MAUI - looks like session legacyCode.
   * @param string $subject
   *   No documentation in MAUI.
   *
   * @return array
   *   JSON decoded array of response data.
   */
  private function fetchCourseDropdown($session, $subject) {
    $data = [];
    if (!empty($session) && !empty($subject)) {
      $endpoint = 'pub/registrar/course/dropdown/' . $session . '/' . $subject;
      if ($data = $this->maui->request('GET', $endpoint)) {
        sort($data);
      }
    }
    return $data;
  }

  /**
   * Course dropdown callback.
   */
  public function courseDropdownCallback($form, $form_state) {
    return $form['section'];
  }

  /**
   * Section dropdown option callback.
   */
  private function sectionOptions($session, $department, $course) {
    $options = [];

    if (!empty($session) && !empty($department) && !empty($course)) {
      $sections = $this->fetchSectionsDropdown($session, $department, $course);
      foreach ($sections as $section) {
        $options[$section->sectionId] = $section->sectionNumber;
      }
    }

    return $options;
  }

  /**
   * Provide a list of values for a section number dropdown.
   *
   * GET /pub/registrar/sections/dropdown/{session}/{subject}/{course}.
   *
   * @param string $session
   *   No documentation in MAUI.
   * @param string $subject
   *   No documentation in MAUI.
   * @param string $course
   *   No documentation in MAUI.
   *
   * @return array
   *   JSON decoded array of response data.
   */
  private function fetchSectionsDropdown($session, $subject, $course) {
    $data = [];
    if (!empty($session) && !empty($subject) && !empty($course)) {
      $endpoint = 'pub/registrar/sections/dropdown/' . $session . '/' . $subject . '/' . $course;
      $data = $this->maui->request('GET', $endpoint);
    }
    return is_array($data) ? $data : [];
  }

  /**
   * Deadlines AJAX callback.
   */
  public function deadlinesCallback($form, $form_state) {
    return $form['deadlines'];
  }

  /**
   * Deadlines markup callback.
   */
  public function deadlinesMarkup($session, $department, $course, $section) {
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
            'data' => $this->t('<span class="uiowa-maui-deadline-label">@label</span> <span class="uiowa-maui-deadline-date">@date</span>', [
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
            $lookup = json_decode(file_get_contents('https://data.its.uiowa.edu/maps/number-lookup'));
            $building = strtolower($value->building);

            if ($lookup?->$building) {
              $location = $this->t('@room <a href="@url">@building</a>', [
                '@room' => $value->room ?? '',
                '@url' => urlencode('https://www.facilities.uiowa.edu/building/' . $lookup->$building),
                '@building' => $value->building,
              ]);
            }
          }
        }

        $times_and_locations[] = implode(' ', [$time, $location]);
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
