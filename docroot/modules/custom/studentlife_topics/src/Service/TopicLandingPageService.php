<?php
namespace Drupal\studentlife_topics\Service;

use Drupal\node\Entity\Node;

/**
 *  Service that provides some functions to the Topic Landing Page twig template
 */
class TopicLandingPageService extends \Twig_Extension {

  /**
   * {@inheritdoc}
   */
	public function getName() {
		return 'topics.twig.TopicService';
	}

  /**
   * {@inheritdoc}
   */
	public function getFunctions() {
		return array(
			new \Twig_SimpleFunction('getNodesByTaxonomyTermIds',
				array($this, 'getNodesByTaxonomyTermIds'),
				array('is_safe' => array('html'),
				)),

		);
	}
  /**
   * Queries and returns nodes that are tagged with provided term/tag IDs
   *
   * @param int|array $termIds
   *   A single tag or an array of terms/tags to filter the nodes by
   *
   * @return array
   *   An array of nodes tagged with the provided terms/tag(s)
   */

	public function getNodesByTaxonomyTermIds($termIds) {
		$termIds = (array) $termIds;
		if (empty($termIds)) {
			return NULL;
		}
		$query = \Drupal::entityQuery('node')
			->condition('field_tags', $termIds, 'IN');
		$nids = $query->execute();
		$nids = array_values($nids);

		if (!empty($nids)) {
			return \Drupal\node\Entity\Node::loadMultiple($nids);
		}
		return NULL;
	}

}
