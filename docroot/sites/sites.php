<?php

/**
 * @file
 * Multisite directory aliases.
 *
 * These may need to be more restrictive depending on how acquia_purge
 * determines the purge domains for multisites.
 *
 * @see https://www.drupal.org/project/acquia_purge/issues/3050554
 * @see example.sites.php
 */

// Require local sites.php if available.
if (file_exists(__DIR__ . '/sites.local.php')) {
  require __DIR__ . '/sites.local.php';
}

// Directory aliases for the default site.
$sites['demo.dev.drupal.uiowa.edu'] = 'default';
$sites['demo.stage.drupal.uiowa.edu'] = 'default';
$sites['demo.prod.drupal.uiowa.edu'] = 'default';

// Directory aliases for hr.uiowa.edu.
$sites['hr.dev.drupal.uiowa.edu'] = 'hr.uiowa.edu';
$sites['hr.stage.drupal.uiowa.edu'] = 'hr.uiowa.edu';
$sites['hr.prod.drupal.uiowa.edu'] = 'hr.uiowa.edu';
$sites['hr.uiowa.edu'] = 'hr.uiowa.edu';

// Directory aliases for callctr.dentistry.uiowa.edu.
$sites['dentistrycallctr.dev.drupal.uiowa.edu'] = 'callctr.dentistry.uiowa.edu';
$sites['dentistrycallctr.stage.drupal.uiowa.edu'] = 'callctr.dentistry.uiowa.edu';
$sites['dentistrycallctr.prod.drupal.uiowa.edu'] = 'callctr.dentistry.uiowa.edu';
$sites['callctr.dentistry.uiowa.edu'] = 'callctr.dentistry.uiowa.edu';

// Directory aliases for gis.sites.uiowa.edu.
$sites['sitesgis.dev.drupal.uiowa.edu'] = 'gis.sites.uiowa.edu';
$sites['sitesgis.stage.drupal.uiowa.edu'] = 'gis.sites.uiowa.edu';
$sites['sitesgis.prod.drupal.uiowa.edu'] = 'gis.sites.uiowa.edu';

// Directory aliases for protostudios.uiowa.edu.
$sites['protostudios.dev.drupal.uiowa.edu'] = 'protostudios.uiowa.edu';
$sites['protostudios.stage.drupal.uiowa.edu'] = 'protostudios.uiowa.edu';
$sites['protostudios.prod.drupal.uiowa.edu'] = 'protostudios.uiowa.edu';

// Directory aliases for uiventures.uiowa.edu.
$sites['uiventures.dev.drupal.uiowa.edu'] = 'uiventures.uiowa.edu';
$sites['uiventures.stage.drupal.uiowa.edu'] = 'uiventures.uiowa.edu';
$sites['uiventures.prod.drupal.uiowa.edu'] = 'uiventures.uiowa.edu';

// Directory aliases for brand.uiowa.edu.
$sites['brand.dev.drupal.uiowa.edu'] = 'brand.uiowa.edu';
$sites['brand.stage.drupal.uiowa.edu'] = 'brand.uiowa.edu';
$sites['brand.prod.drupal.uiowa.edu'] = 'brand.uiowa.edu';

// Directory aliases for careers.uiowa.edu.
$sites['careers.dev.drupal.uiowa.edu'] = 'careers.uiowa.edu';
$sites['careers.stage.drupal.uiowa.edu'] = 'careers.uiowa.edu';
$sites['careers.prod.drupal.uiowa.edu'] = 'careers.uiowa.edu';

// Directory aliases for veterans.uiowa.edu.
$sites['veterans.dev.drupal.uiowa.edu'] = 'veterans.uiowa.edu';
$sites['veterans.stage.drupal.uiowa.edu'] = 'veterans.uiowa.edu';
$sites['veterans.prod.drupal.uiowa.edu'] = 'veterans.uiowa.edu';

// Directory aliases for sitenow.uiowa.edu.
$sites['sitenow.dev.drupal.uiowa.edu'] = 'sitenow.uiowa.edu';
$sites['sitenow.stage.drupal.uiowa.edu'] = 'sitenow.uiowa.edu';
$sites['sitenow.prod.drupal.uiowa.edu'] = 'sitenow.uiowa.edu';

// Directory aliases for mnh.uiowa.edu.
$sites['mnh.dev.drupal.uiowa.edu'] = 'mnh.uiowa.edu';
$sites['mnh.stage.drupal.uiowa.edu'] = 'mnh.uiowa.edu';
$sites['mnh.prod.drupal.uiowa.edu'] = 'mnh.uiowa.edu';

// Directory aliases for oldcap.uiowa.edu.
$sites['oldcap.dev.drupal.uiowa.edu'] = 'oldcap.uiowa.edu';
$sites['oldcap.stage.drupal.uiowa.edu'] = 'oldcap.uiowa.edu';
$sites['oldcap.prod.drupal.uiowa.edu'] = 'oldcap.uiowa.edu';

// Directory aliases for assessment.uiowa.edu.
$sites['assessment.dev.drupal.uiowa.edu'] = 'assessment.uiowa.edu';
$sites['assessment.stage.drupal.uiowa.edu'] = 'assessment.uiowa.edu';
$sites['assessment.prod.drupal.uiowa.edu'] = 'assessment.uiowa.edu';

// Directory aliases for etc.engineering.uiowa.edu.
$sites['engineeringetc.dev.drupal.uiowa.edu'] = 'etc.engineering.uiowa.edu';
$sites['engineeringetc.stage.drupal.uiowa.edu'] = 'etc.engineering.uiowa.edu';
$sites['engineeringetc.prod.drupal.uiowa.edu'] = 'etc.engineering.uiowa.edu';

// Directory aliases for law.uiowa.edu.
$sites['law.dev.drupal.uiowa.edu'] = 'law.uiowa.edu';
$sites['law.stage.drupal.uiowa.edu'] = 'law.uiowa.edu';
$sites['law.prod.drupal.uiowa.edu'] = 'law.uiowa.edu';

// Directory aliases for diversity.uiowa.edu.
$sites['diversity.dev.drupal.uiowa.edu'] = 'diversity.uiowa.edu';
$sites['diversity.stage.drupal.uiowa.edu'] = 'diversity.uiowa.edu';
$sites['diversity.prod.drupal.uiowa.edu'] = 'diversity.uiowa.edu';

// Directory aliases for billing.uiowa.edu.
$sites['billing.dev.drupal.uiowa.edu'] = 'billing.uiowa.edu';
$sites['billing.stage.drupal.uiowa.edu'] = 'billing.uiowa.edu';
$sites['billing.prod.drupal.uiowa.edu'] = 'billing.uiowa.edu';

// Directory aliases for bme.engineering.uiowa.edu.
$sites['engineeringbme.dev.drupal.uiowa.edu'] = 'bme.engineering.uiowa.edu';
$sites['engineeringbme.stage.drupal.uiowa.edu'] = 'bme.engineering.uiowa.edu';
$sites['engineeringbme.prod.drupal.uiowa.edu'] = 'bme.engineering.uiowa.edu';

// Directory aliases for staff-council.uiowa.edu.
$sites['staff-council.dev.drupal.uiowa.edu'] = 'staff-council.uiowa.edu';
$sites['staff-council.stage.drupal.uiowa.edu'] = 'staff-council.uiowa.edu';
$sites['staff-council.prod.drupal.uiowa.edu'] = 'staff-council.uiowa.edu';

// Directory aliases for pentacrestmuseums.uiowa.edu.
$sites['pentacrestmuseums.dev.drupal.uiowa.edu'] = 'pentacrestmuseums.uiowa.edu';
$sites['pentacrestmuseums.stage.drupal.uiowa.edu'] = 'pentacrestmuseums.uiowa.edu';
$sites['pentacrestmuseums.prod.drupal.uiowa.edu'] = 'pentacrestmuseums.uiowa.edu';

// Directory aliases for uiservicecenter.uiowa.edu.
$sites['uiservicecenter.dev.drupal.uiowa.edu'] = 'uiservicecenter.uiowa.edu';
$sites['uiservicecenter.stage.drupal.uiowa.edu'] = 'uiservicecenter.uiowa.edu';
$sites['uiservicecenter.prod.drupal.uiowa.edu'] = 'uiservicecenter.uiowa.edu';

// Directory aliases for health-research-network.sites.uiowa.edu.
$sites['siteshealth-research-network.dev.drupal.uiowa.edu'] = 'health-research-network.sites.uiowa.edu';
$sites['siteshealth-research-network.stage.drupal.uiowa.edu'] = 'health-research-network.sites.uiowa.edu';
$sites['siteshealth-research-network.prod.drupal.uiowa.edu'] = 'health-research-network.sites.uiowa.edu';

// Directory aliases for classrooms.uiowa.edu.
$sites['classrooms.dev.drupal.uiowa.edu'] = 'classrooms.uiowa.edu';
$sites['classrooms.stage.drupal.uiowa.edu'] = 'classrooms.uiowa.edu';
$sites['classrooms.prod.drupal.uiowa.edu'] = 'classrooms.uiowa.edu';

// Directory aliases for rdmevents.sites.uiowa.edu.
$sites['sitesrdmevents.dev.drupal.uiowa.edu'] = 'rdmevents.sites.uiowa.edu';
$sites['sitesrdmevents.stage.drupal.uiowa.edu'] = 'rdmevents.sites.uiowa.edu';
$sites['sitesrdmevents.prod.drupal.uiowa.edu'] = 'rdmevents.sites.uiowa.edu';

// Directory aliases for faculty-senate.uiowa.edu.
$sites['faculty-senate.dev.drupal.uiowa.edu'] = 'faculty-senate.uiowa.edu';
$sites['faculty-senate.stage.drupal.uiowa.edu'] = 'faculty-senate.uiowa.edu';
$sites['faculty-senate.prod.drupal.uiowa.edu'] = 'faculty-senate.uiowa.edu';

// Directory aliases for honorary-degrees.sites.uiowa.edu.
$sites['siteshonorary-degrees.dev.drupal.uiowa.edu'] = 'honorary-degrees.sites.uiowa.edu';
$sites['siteshonorary-degrees.stage.drupal.uiowa.edu'] = 'honorary-degrees.sites.uiowa.edu';
$sites['siteshonorary-degrees.prod.drupal.uiowa.edu'] = 'honorary-degrees.sites.uiowa.edu';

// Directory aliases for honors.uiowa.edu.
$sites['honors.dev.drupal.uiowa.edu'] = 'honors.uiowa.edu';
$sites['honors.stage.drupal.uiowa.edu'] = 'honors.uiowa.edu';
$sites['honors.prod.drupal.uiowa.edu'] = 'honors.uiowa.edu';

// Directory aliases for cot.org.uiowa.edu.
$sites['orgcot.dev.drupal.uiowa.edu'] = 'cot.org.uiowa.edu';
$sites['orgcot.stage.drupal.uiowa.edu'] = 'cot.org.uiowa.edu';
$sites['orgcot.prod.drupal.uiowa.edu'] = 'cot.org.uiowa.edu';

// Directory aliases for registrar.uiowa.edu.
$sites['registrar.dev.drupal.uiowa.edu'] = 'registrar.uiowa.edu';
$sites['registrar.stage.drupal.uiowa.edu'] = 'registrar.uiowa.edu';
$sites['registrar.prod.drupal.uiowa.edu'] = 'registrar.uiowa.edu';

// Directory aliases for cbig.engineering.uiowa.edu.
$sites['engineeringcbig.dev.drupal.uiowa.edu'] = 'cbig.engineering.uiowa.edu';
$sites['engineeringcbig.stage.drupal.uiowa.edu'] = 'cbig.engineering.uiowa.edu';
$sites['engineeringcbig.prod.drupal.uiowa.edu'] = 'cbig.engineering.uiowa.edu';

// Directory aliases for looc.course.uiowa.edu.
$sites['courselooc.dev.drupal.uiowa.edu'] = 'looc.course.uiowa.edu';
$sites['courselooc.stage.drupal.uiowa.edu'] = 'looc.course.uiowa.edu';
$sites['courselooc.prod.drupal.uiowa.edu'] = 'looc.course.uiowa.edu';

// Directory aliases for biomost.engineering.uiowa.edu.
$sites['engineeringbiomost.dev.drupal.uiowa.edu'] = 'biomost.engineering.uiowa.edu';
$sites['engineeringbiomost.stage.drupal.uiowa.edu'] = 'biomost.engineering.uiowa.edu';
$sites['engineeringbiomost.prod.drupal.uiowa.edu'] = 'biomost.engineering.uiowa.edu';

// Directory aliases for meyerholz.lab.uiowa.edu.
$sites['labmeyerholz.dev.drupal.uiowa.edu'] = 'meyerholz.lab.uiowa.edu';
$sites['labmeyerholz.stage.drupal.uiowa.edu'] = 'meyerholz.lab.uiowa.edu';
$sites['labmeyerholz.prod.drupal.uiowa.edu'] = 'meyerholz.lab.uiowa.edu';

// Directory aliases for accreditation.uiowa.edu.
$sites['accreditation.dev.drupal.uiowa.edu'] = 'accreditation.uiowa.edu';
$sites['accreditation.stage.drupal.uiowa.edu'] = 'accreditation.uiowa.edu';
$sites['accreditation.prod.drupal.uiowa.edu'] = 'accreditation.uiowa.edu';

// Directory aliases for yangs.lab.uiowa.edu.
$sites['labyangs.dev.drupal.uiowa.edu'] = 'yangs.lab.uiowa.edu';
$sites['labyangs.stage.drupal.uiowa.edu'] = 'yangs.lab.uiowa.edu';
$sites['labyangs.prod.drupal.uiowa.edu'] = 'yangs.lab.uiowa.edu';

// Directory aliases for merlino.lab.uiowa.edu.
$sites['labmerlino.dev.drupal.uiowa.edu'] = 'merlino.lab.uiowa.edu';
$sites['labmerlino.stage.drupal.uiowa.edu'] = 'merlino.lab.uiowa.edu';
$sites['labmerlino.prod.drupal.uiowa.edu'] = 'merlino.lab.uiowa.edu';

// Directory aliases for dentistry.uiowa.edu.
$sites['dentistry.dev.drupal.uiowa.edu'] = 'dentistry.uiowa.edu';
$sites['dentistry.stage.drupal.uiowa.edu'] = 'dentistry.uiowa.edu';
$sites['dentistry.prod.drupal.uiowa.edu'] = 'dentistry.uiowa.edu';

// Directory aliases for martin.lab.uiowa.edu.
$sites['labmartin.dev.drupal.uiowa.edu'] = 'martin.lab.uiowa.edu';
$sites['labmartin.stage.drupal.uiowa.edu'] = 'martin.lab.uiowa.edu';
$sites['labmartin.prod.drupal.uiowa.edu'] = 'martin.lab.uiowa.edu';

// Directory aliases for multilingualsyntax.lab.uiowa.edu.
$sites['labmultilingualsyntax.dev.drupal.uiowa.edu'] = 'multilingualsyntax.lab.uiowa.edu';
$sites['labmultilingualsyntax.stage.drupal.uiowa.edu'] = 'multilingualsyntax.lab.uiowa.edu';
$sites['labmultilingualsyntax.prod.drupal.uiowa.edu'] = 'multilingualsyntax.lab.uiowa.edu';

// Directory aliases for seru.uiowa.edu.
$sites['seru.dev.drupal.uiowa.edu'] = 'seru.uiowa.edu';
$sites['seru.stage.drupal.uiowa.edu'] = 'seru.uiowa.edu';
$sites['seru.prod.drupal.uiowa.edu'] = 'seru.uiowa.edu';

// Directory aliases for mootcourt.org.uiowa.edu.
$sites['orgmootcourt.dev.drupal.uiowa.edu'] = 'mootcourt.org.uiowa.edu';
$sites['orgmootcourt.stage.drupal.uiowa.edu'] = 'mootcourt.org.uiowa.edu';
$sites['orgmootcourt.prod.drupal.uiowa.edu'] = 'mootcourt.org.uiowa.edu';

// Directory aliases for alphachisigma-alphatheta.org.uiowa.edu.
$sites['orgalphachisigma-alphatheta.dev.drupal.uiowa.edu'] = 'alphachisigma-alphatheta.org.uiowa.edu';
$sites['orgalphachisigma-alphatheta.stage.drupal.uiowa.edu'] = 'alphachisigma-alphatheta.org.uiowa.edu';
$sites['orgalphachisigma-alphatheta.prod.drupal.uiowa.edu'] = 'alphachisigma-alphatheta.org.uiowa.edu';

// Directory aliases for qulat.sites.uiowa.edu.
$sites['sitesqulat.dev.drupal.uiowa.edu'] = 'qulat.sites.uiowa.edu';
$sites['sitesqulat.stage.drupal.uiowa.edu'] = 'qulat.sites.uiowa.edu';
$sites['sitesqulat.prod.drupal.uiowa.edu'] = 'qulat.sites.uiowa.edu';

// Directory aliases for iti.uiowa.edu.
$sites['iti.dev.drupal.uiowa.edu'] = 'iti.uiowa.edu';
$sites['iti.stage.drupal.uiowa.edu'] = 'iti.uiowa.edu';
$sites['iti.prod.drupal.uiowa.edu'] = 'iti.uiowa.edu';

// Directory aliases for international.uiowa.edu.
$sites['international.dev.drupal.uiowa.edu'] = 'international.uiowa.edu';
$sites['international.stage.drupal.uiowa.edu'] = 'international.uiowa.edu';
$sites['international.prod.drupal.uiowa.edu'] = 'international.uiowa.edu';

// Directory aliases for printmail.businsvc.uiowa.edu.
$sites['businsvcprintmail.dev.drupal.uiowa.edu'] = 'printmail.businsvc.uiowa.edu';
$sites['businsvcprintmail.stage.drupal.uiowa.edu'] = 'printmail.businsvc.uiowa.edu';
$sites['businsvcprintmail.prod.drupal.uiowa.edu'] = 'printmail.businsvc.uiowa.edu';

// Directory aliases for kroska.lab.uiowa.edu.
$sites['labkroska.dev.drupal.uiowa.edu'] = 'kroska.lab.uiowa.edu';
$sites['labkroska.stage.drupal.uiowa.edu'] = 'kroska.lab.uiowa.edu';
$sites['labkroska.prod.drupal.uiowa.edu'] = 'kroska.lab.uiowa.edu';
