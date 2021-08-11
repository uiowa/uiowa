<?php

namespace Drupal\Tests\sitenow_migrate\Kernel;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystem;
use Drupal\Core\State\State;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\Migration;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

/**
 * Test description.
 *
 * @group sitenow_migrate
 */
class BaseNodeSourceTest extends KernelTestBase {
  /**
   * The system under test.
   *
   * @var \Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource|mixed|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['sitenow_migrate'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->sut = $this->getMockForAbstractClass(BaseNodeSource::class, [
      [
        'key' => 'drupal_7',
        'constants' => [],
        'database' => [],
        'plugin' => 'sitenow_migrate_test',
      ],
      'sitenow_migrate_test',
      [
        'requirements_met' => TRUE,
        'id' => "sitenow_migrate_test",
        'source_module' => "node",
        'provider' => [],
      ],
      $this->createMock(Migration::class),
      $this->createMock(State::class),
      $this->createMock(ModuleHandler::class),
      $this->createMock(FileSystem::class),
      $this->createMock(EntityTypeManager::class),
    ]);
  }

  /**
   * Test callback.
   */
  public function testSummaryUsedIfNotEmpty() {
    $summary = $this->sut->getSummaryFromTextField([
      0 => [
        'value' => 'bar',
        'summary' => 'foo',
        'format' => 'filtered_html',
      ],
    ]);

    $this->assertEquals('foo', $summary);
  }

  /**
   * Test a failed summary migration for obermann node 178.
   *
   * @todo Refactor into generic test.
   */
  public function testSummaryObermannNode178() {
    $value = <<<EOD
'<p>Teresa Mangum, Director of the Obermann Center, is co-editing a new book series for&nbsp;<a href="http://www.uiowapress.org/">The University of Iowa Press</a>&nbsp;with <a href="http://www.brown.edu/Research/JNBC/contact.php">Anne Valk</a>,&nbsp;Associate Director of the John Nicholas Brown Center for the Public Humanities and Cultural Heritage at Brown University. &nbsp;<a href="http://www.uiowapress.org/authors/humanities-and-public-life.htm"><strong>Humanities and Public Life</strong></a> will feature books examining projects using the arts and humanities to promote community building and civic change. These short books will meet the pressing needs of publicly engaged scholars and their community partners for models of rigorous work, critical thinking about best practices, and strategies for measuring a project's impact.&nbsp;</p>
EOD;

    $summary = $this->sut->getSummaryFromTextField([
      0 => [
        'value' => $value,
        'summary' => '',
        'format' => 'filtered_html',
      ],
    ]);

    $this->assertNotEmpty($summary);
  }

  /**
   * Test a failed summary migration for obermann node 2116.
   *
   * @todo Refactor into generic test.
   */
  public function testSummaryObermannNode2116() {
    $value = <<<EOD
<p>On Monday, February 13, at 7:00 p.m. at the Iowa City Public Library, Sara Goldrick-Rab, <span style="background:#fcfcfc">Professor of Higher Education Policy &amp; Sociology at Temple University</span>, will give a public lecture on the crisis of college affordability and student loan debt. She will offer solutions for fixing the U.S. financial aid system<span style="background:white"> to make higher education accessible to all</span>, drawing on research published in her newest book, <i>Paying the Price: College Costs, Financial Aid, and the Betrayal of the American Dream</i>. The book chronicles the experiences of working- and middle-class students who enter public colleges with <span style="background:white">federal aid and Pell Grants but leave without a degree, unable to afford tuition, books, and living expenses. </span></p>

<h3>Access Denied</h3>

<p><span style="color:black">Goldrick-Rab places the blame for the national college affordability crisis on outdated, convoluted financial aid systems. “In the 1970s,” she writes, “targeting financial aid for the poorest individuals made sense—after all, most people didn’t want to attend college, it wasn’t required, and college costs were low enough that the Pell Grant largely covered the bills. Today, that model is failing: the vast majority of the populace wants access to affordable, high-quality public higher education, it is required by the modern labor market, and college costs are so high that grants and scholarships are restricted to only a fraction of students with financial need.”</span></p>

<h3>Implications of Financial Aid Policies</h3>

<p>At the state level, according to the Iowa College Student Aid Commission’s 2016 report, Iowa ranks 30th in the nation in BA degree attainment and a lowly 50th in the percentage of financial aid provided to students with demonstrated need attending its public institutions (16%). The state also has the eighth-highest percentage of students graduating with student debt (68%), and the average amount of debt its graduates carry is the eighth-highest in the nation.</p>

<p><span style="color:black">Goldrick-Rab, who identifies as an “activist-scholar,” is the Founder of the Wisconsin HOPE Lab, which seeks ways to make college more affordable. Her research examines the efficacy and implications of financial aid policies, welfare reform, transfer practices, and interventions aimed at increasing college enrollment among marginalized groups. She has published articles in <i>The Atlantic</i>, the <i>New York Times</i>, and the <i>Washington Post</i>, was a recent guest on Comedy Central’s <i>The Daily Show,</i> and, in June 2015, joined Senator Elizabeth Warren for a college affordability roundtable at the renowned Albert Shanker Institute.</span></p>

<p><span style="color:black">“Goldrick-Rab’s scholarship fills a critical void in our conversations about the realities of financial aid policy in the face of rapidly rising tuition,” notes F. King Alexander, president of Louisiana State University. “[It] is an important, poignant reminder of the ongoing negative impact of state appropriation reductions in this era.” &nbsp;</span></p>

<h3><span style="color:black">Scholar Activism Topic of Afternoon Talk</span></h3>

<p><span style="color:black">In addition to her public lecture, Goldrick-Rab will meet with University of Iowa Inequality Seminar to discuss “Making College Affordable: Adventures in Scholar-Activism.” The seminar, hosted by the Public Policy Center, will meet at 2:00 p.m. on February 13 in W113 Seashore Hall on the University of Iowa campus. Both events are free and open to the public. </span></p>

<p><span style="font-size:12.0pt"><span style="font-family:&quot;Times New Roman&quot;"><span style="color:black">Goldrick-Rab’s visit is sponsored by the University of Iowa’s Obermann Center for Advanced Studies, Public Policy Center, College of Education, Educational Policy &amp; Leadership Studies, Graduate &amp; Professional Student Government, UI Student Government, Graduate Student Senate, College of Liberal Arts &amp; Sciences, Department of Sociology, Division of Student Life, College of Business, Chief Diversity Office, and the Iowa City Public Library. It is part of a year-long series of events examining the current state of public higher education. The series began with a screening of the documentary film <i>Starving the Beast </i>and will culminate in a day-long public forum in April.</span></span></span></p>

EOD;

    $summary = $this->sut->getSummaryFromTextField([
      0 => [
        'value' => $value,
        'summary' => '',
        'format' => 'filtered_html',
      ],
    ]);

    $this->assertNotEquals('On Monday, February 13, at 7:00 p.m.', $summary);

  }

}
