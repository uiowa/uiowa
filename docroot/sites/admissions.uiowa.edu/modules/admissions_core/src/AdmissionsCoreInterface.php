<?php

namespace Drupal\admissions_core;

/**
 * Provides an interface for admissions_core constants.
 */
interface AdmissionsCoreInterface {
  /**
   * Slug for href construction of 2 plus 2 links.
   */
  const TWO_PLUS_TWO_PATH = '/academics/2plus2/';

  /**
   * Title for 2plus2 content type.
   */
  const TWO_PLUS_TWO_TITLE = '2 Plus 2 Grad Plan';

  /**
   * Overrides for query alters for areas of study.
   */
  const AOS_QUERY_OVERRIDES = [
    'nursing rn bsn' => 'nursing rn-bsn',
    'criminology law and justice' => 'criminology, law, and justice',
    'resilience and trauma informed perspectives certificate' => 'resilience and trauma-informed perspectives certificate',
    'interscholastic athleticactivities administration' => 'interscholastic athletic/activities administration',
  ];

}
