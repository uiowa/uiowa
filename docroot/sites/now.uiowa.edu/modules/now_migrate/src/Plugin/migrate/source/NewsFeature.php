<?php

namespace Drupal\now_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\UrlHelper;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "now_news_feature",
 *   source_module = "node"
 * )
 */
class NewsFeature extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Make sure our nodes are retrieved in order,
    // and force a highwater mark of our last-most migrated node.
    $query->condition('n.nid', ['34621', '34626', '34576', '34546', '34531', '34496', '34481', '29416', '34396', '34341', '34356', '34376', '34346', '34351', '34276', '34241', '34121', '34026', '33916', '33931', '33886', '33621', '33401', '33321', '33276', '33081', '32981', '32916', '32891', '32826', '32801', '32806', '32781', '32776', '32751', '32791', '32766', '32661', '32626', '32296', '32026', '31951', '31781', '31761', '31611', '31586', '31516', '31461', '31411', '31306', '31211', '31241', '31226', '30956', '30936', '30946', '30916', '30836', '30901', '30896', '30876', '30791', '30671', '30646', '30516', '30316', '30121', '30086', '30056', '29991', '30031', '30016', '29951', '29831', '29936', '29916', '29906', '29896', '29886', '29876', '29881', '29806', '29686', '29571', '29556', '29506', '29511', '29441', '29166', '29091', '29036', '29011', '28976', '28771', '28841', '28806', '28781', '28701', '28676', '28731', '28656', '28586', '28616', '28426', '28481', '28461', '27611', '28251', '28176', '28181', '28006', '28101', '28076', '27966', '27896', '27916', '27536', '27746', '27751', '27696', '27661', '27656', '27456', '27261', '27031', '27366', '27281', '27301', '27251', '27166', '27201', '27136', '27131', '26936', '26996', '26981', '26961', '26856', '26836', '26741', '26731', '26681', '26656', '26576', '26561', '26481', '26256', '26361', '26366', '26281', '26286', '26231', '26056', '26001', '25991', '25966', '25936', '25926', '25756', '25751', '25716', '25711', '25701', '25661', '25651', '25596', '25606', '25671', '25556', '25531', '25516', '25546', '25526', '25446', '25456', '25406', '25371', '25286', '25276', '25261', '25246', '25226', '25251', '25186', '25151', '25121', '25041', '24826', '24836', '24821', '24711', '24721', '24591', '24576', '24581', '24561', '24501', '24451', '24361', '24421', '24416', '24211', '24201', '24176', '24116', '24076', '24121', '23916', '23921', '23926', '23931', '23936', '23941', '23946', '23951', '23956', '24131', '24061', '23961', '23966', '23971', '23976', '23981', '24226', '23911', '23891', '23846', '23811', '23751', '23796', '23786', '23761', '23736', '23701', '23586', '23666', '23541', '23521', '23471', '23411', '23356', '23266', '23311', '23291', '23216', '23211', '23206', '23231', '23226', '23076', '23186', '23176', '23116', '23071', '23031', '23046', '22971', '22946', '22936', '22891', '22926', '22886', '22856', '22771', '22801', '22806', '22811', '22816', '22821', '22796', '22741', '22736', '22726', '22656', '22661', '22566', '22586', '22491', '22396', '22351', '22346', '22276', '22256', '22251', '22201', '22161', '22066', '22156', '22151', '22131', '22121', '22071', '22036', '21411', '21791', '21951', '21841', '21836', '21821', '21801', '21751', '21686', '21691', '21621', '21616', '21681', '21606', '21631', '21586', '21581', '21651', '21556', '21521', '21451', '21386', '21346', '21336', '21256', '21251', '21166', '21201', '21101', '21141', '21131', '21116', '21096', '21081', '21071', '21026', '21031', '21016', '21036', '21011', '20986', '20976', '20996', '20991', '20956', '20896', '20891', '20886', '20881', '20876', '20871', '20866', '20861', '20856', '20851', '20846', '20841', '20836', '20611', '20806', '20797', '20741', '20666', '20691', '20686', '20661', '20636', '20591', '20576', '20541', '20531', '20241', '20496', '20456', '20461', '20431', '20401', '20381', '20386', '20316', '20291', '20221', '20251', '20186', '20206', '20166', '20156', '20126', '20136', '20001', '19951', '19931', '19936', '19916', '19856', '19891', '19851', '19706', '19626', '19701', '19661', '19606', '19046', '19546', '19556', '19446', '19491', '18981', '19206', '19421', '19351', '19381', '19051', '19301', '19296', '19171', '19006', '19126', '19061', '19091', '19096', '19071', '19066', '19086', '19101', '19026', '19016', '18986', '18991', '18956', '18961', '18951', '18831', '18946', '17961', '18916', '18771', '18856', '18826', '18646', '18736', '18571', '18676', '17941', '18516', '18356', '17591', '18301', '18281', '18196', '18276', '18131', '18061', '18166', '18171', '17911', '18001', '17946', '17771', '17891', '17896', '17821', '17701', '17481', '17681', '17636', '17666', '17656', '17616', '17366', '17286', '17601', '17606', '17626', '17203', '17141', '17551', '17536', '17198', '17576', '17541', '17381', '17210', '17476', '17431', '17401', '17391', '17311', '17256', '17351', '17346', '17251', '17231', '17222', '17220', '17213', '17151', '17150', '17201', '17154', '17145', '17146', '17152', '17143', '17126', '17133', '17123', '17113', '17099', '17088', '17094', '17084', '17078', '17072', '17039', '17062', '17012', '17027', '17043', '17018', '17032', '17017', '16924', '17014', '17016', '17011', '16983', '16963', '16957', '16949', '16950', '16905', '16940', '16925', '16902', '16895', '16770', '16883', '16882', '16871', '16874', '16831', '16850', '16853', '16852', '16828', '16796', '16785', '16777', '16735', '16699', '16083', '16702', '16688', '16531', '16681', '16568', '16650', '16648', '16637', '16634', '16532', '16622', '16574', '16604', '16589', '16578', '16593', '16441', '16552', '16541', '16449', '16469', '16465', '16039', '16430', '16442', '16439', '16459', '16460', '16461', '16462', '16458', '16387', '16179', '16372', '16402', '16350', '16390', '16366', '16347', '16320', '16288', '16254', '16223', '16310', '16305', '16192', '16285', '16257', '16238', '16134', '16251', '16221', '16222', '16081', '16218', '16082', '16088', '16210', '16097', '16199', '16188', '16182', '16177', '16180', '16146', '16140', '16153', '16037', '16138', '16125', '16095', '15830', '16010', '16085', '15944', '15844', '16074', '16070', '16046', '15886', '16063', '16059', '16054', '16038', '16021', '15864', '16031', '16006', '16016', '16008', '16001', '15971', '15866', '15967', '15959', '15948', '15943', '15908', '15926', '15865', '15858', '15868', '15790', '15849', '15840', '15836', '15756', '15829', '15827', '15815', '15816', '15788', '15767', '15738', '15728', '15702', '15704', '15674', '15676', '15633', '15635', '15622', '15620', '15606', '15605', '15597', '15596', '15594', '15585', '15569', '15564', '15544', '15542', '15531', '15514', '15513', '15501', '15491', '15486', '15484', '15482', '15477', '15472', '15464', '15461', '15459', '15441', '15437', '15434', '15423', '15422', '15410', '15409', '15407', '15394', '15374', '15358', '15341', '15325', '15322', '15287', '15307', '15304', '15272', '15278', '15274', '15270', '15266', '15248', '15230', '15233', '15229', '15221', '15201', '15206', '15199', '15191', '15174', '15180', '15176', '15162', '15165', '15151', '15141', '15140', '15114', '15091', '15084', '15083', '15069', '15068', '15067', '15062', '15061', '15057', '15048', '15038', '15036', '15029', '15019', '14999', '14997', '14992', '14969', '14960', '14904', '14900', '14891', '14882', '14866', '14571', '14573', '14735', '14552', '14551', '14561', '14736', '14553', '14541', '14545', '14538', '14530', '14863', '14534', '14115', '14113', '14095', '14101', '14114', '14732', '14731', '14730', '14097', '14109', '14098', '14110', '14096', '14105', '14102', '14099', '14100', '14093', '14108', '14091', '14092', '14086', '14088', '14080', '14085', '14729', '14087', '14076', '14090', '14728', '14071', '14073', '14072', '14065', '14064', '14067', '14069', '14061', '14063', '14052', '14057', '14066', '14856', '14046', '14045', '14043', '14050', '14040', '14039', '14041', '14035', '14030', '14029', '14031', '14037', '14854', '14013', '14019', '14020', '14010', '14006', '14001', '14014', '13997', '14563', '13991', '13990', '13983', '13975', '13974', '13973', '13969', '13981', '13963', '13965', '13971', '13962', '13955', '13957', '13947', '13943', '13939', '13946', '13938', '13932', '13934', '13961', '13951', '13945', '13924', '13920', '13921', '13912', '13906', '13905', '13918', '14719', '13899', '13940', '14845', '13903', '13896', '13900', '13892', '13978', '13889', '13886', '14718', '14846', '14717', '13877', '13885', '13878', '13866', '14566', '13860', '13864', '14837', '13853', '14713', '13855', '14835', '13857', '14712', '13837', '13824', '13823', '13820', '13826', '14710', '13815', '13806', '14716', '13800', '13801', '13794', '13758', '13755', '13749', '13751', '13741', '13730', '13732', '13727', '14708', '13717', '14825', '13707', '13705', '13701', '13700', '13695', '14707', '13686', '13668', '14822', '13659', '13720', '13658', '13669', '13679', '13657', '13653', '14821', '14819', '13644', '13711', '14834', '13642', '13640', '13635', '13638', '13633', '13645', '14706', '13631', '13623', '13622', '14122', '13609', '13626', '13602', '13601', '13604', '13590', '13577', '13576', '14123', '13567', '13566', '13569', '13563', '13560', '14814', '13554', '13558', '14126', '13539', '13538', '13535', '14813', '13540', '13513', '13505', '13499', '13493', '13498', '14811', '14182', '13476', '13471', '13451', '14118', '13439', '13831', '13425', '13421', '13409', '13407', '13391', '13369', '13350', '13362', '13353', '13341', '13336', '13346', '13329', '13327', '14804', '13318', '13308', '13325', '14170', '13309', '13310', '13294', '13305', '13314', '13285', '13315', '13286', '13278', '13263', '13270', '13259', '13255', '14165', '13266', '13249', '13251', '13250', '13240', '13237', '13230', '14695', '13238', '13224', '13275', '13226', '13229', '14164', '13207', '14163', '13173', '13311', '13149', '13142', '13141', '13137', '13123', '13115', '13312', '13110', '13650', '13095', '13098', '13089', '13081', '13070', '13080', '13069', '14689', '13048', '13054', '13044', '13043', '13050', '13031', '13016', '13005', '13003', '13017', '13004', '12991', '12975', '12977', '12957', '12946', '13015', '12939', '12932', '14151', '12930', '12927', '12917', '12918', '12922', '12912', '12902', '12887', '14788', '12888', '12884', '12906', '12875', '12874', '12870', '12869', '12848', '12976', '14138', '12846', '12841', '12945', '12826', '12835', '12820', '12824', '12811', '12852', '12795', '12792', '12791', '12797', '12778', '14142', '12771', '12769', '12767', '12761', '12768', '14683', '12745', '12764', '12743', '12732', '12853', '12715', '12717', '12713', '12721', '12707', '12706', '12854', '12667', '14780', '12681', '12654', '12659', '12641', '12637', '12635', '12630', '12625', '12645', '12622', '12620', '12611', '12711', '12602', '12594', '12855', '12661', '12587', '12580', '12592', '12567', '14672', '12856', '14131', '12574', '12857', '12545', '12542', '12538', '12537', '14130', '12530', '12529', '12531', '14670', '14684', '12686', '12517', '12506', '12505', '12493', '12490', '13843', '12473', '12474', '13033', '12459', '12463', '12461', '12444', '12440', '12441', '12435', '12425', '12423', '12419', '12404', '12410', '12402', '12395', '12422', '12377', '12373', '12374', '12361', '12359', '12356', '12354', '12355', '12347', '13082', '12342', '12334', '14658', '12328', '14768', '12371', '12337', '12317', '12299', '12309', '12310', '14491', '12275', '12280', '12266', '14653', '12263', '12265', '12256', '12246', '12224', '12690', '12223', '14651', '12205', '12177', '12175', '12148', '12149', '12147', '12138', '12118', '12110', '12098', '12075', '12065', '14459', '14645', '12030', '12022', '12018', '12004', '12006', '12003', '12005', '11994', '12692', '11988', '11962', '11952', '11960', '11940', '11913', '14453', '11893', '11888', '11950', '11880', '11865', '11870', '11864', '11862', '12694', '11869', '11846', '11836', '11829', '11828', '11830', '11811', '11797', '11793', '11779', '11777', '11772', '11766', '11761', '14641', '12696', '11734', '11739', '11726', '11735', '11727', '12697', '12698', '11658', '11644', '11626', '12709', '11625', '14758', '11611', '11607', '11616', '12699', '14630', '14631', '11581', '11584', '11577', '11575', '11565', '11561', '11541', '11534', '11521', '11512', '14433', '14629', '11496', '11493', '11490', '11488', '11484', '14432', '11474', '11477', '11476', '11473', '11466', '11463', '11454', '11468', '11449', '12701', '11453', '11446', '11443', '11423', '11426', '11480', '11432', '14427', '11412', '11414', '11397', '11388', '11390', '11381', '11382', '11372', '11368', '11367', '11353', '11344', '11347', '14326', '11340', '11336', '14623', '11327', '11325', '11323', '11317', '14420', '11316', '11307', '11302', '11298', '11291', '11289', '14760', '11273', '11269', '14418', '11261', '11267', '11244', '11247', '11240', '14308', '11227', '11204', '11211', '11605', '11205', '11202', '11209', '11186', '11175', '11169', '11165', '11166', '11179', '11161', '14614', '11167', '14283', '11133', '11116', '11109', '11105', '11106', '11101', '11096', '11093', '11083', '11075', '11058', '11051', '11043', '11055', '14606', '11040', '11039', '14605', '11026', '11018', '11015', '13059', '14603', '11005', '11012', '11006', '11002', '10995', '10994', '10988', '11010', '14398', '10987', '10978', '14256', '10973', '10971', '10966', '10961', '10958', '10967', '10951', '10948', '14241', '10922', '10917', '13919', '10899', '14594', '10890', '10894', '10900', '10886', '14218', '14589', '10875', '14592', '10865', '10866', '10863', '10840', '10835', '10832', '14383', '10859', '10830', '14584', '10822', '10852', '10817', '12901', '10815', '14582', '14529', '14117', '10847', '14748', '10809', '14581', '10808'], 'IN');
    $query->orderBy('nid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Skip this node if it comes after our last migrated.
    if ($row->getSourceProperty('nid') < $this->getLastMigrated()) {
      return FALSE;
    }

    parent::prepareRow($row);

    // Map various old fields into Tags.
    $tag_tids = [];
    foreach ([
      'field_news_from',
      'field_news_about',
      'field_news_for',
    ] as $field_name) {
      $values = $row->getSourceProperty($field_name);
      if (!isset($values)) {
        continue;
      }
      foreach ($values as $tid_array) {
        $tag_tids[] = $tid_array['tid'];
      }
    }

    if (!empty($tag_tids)) {

      // Fetch tag names based on TIDs from our old site.
      $tag_results = $this->select('taxonomy_term_data', 't')
        ->fields('t', ['name'])
        ->condition('t.tid', $tag_tids, 'IN')
        ->execute();
      $tags = [];
      foreach ($tag_results as $result) {
        $tag_name = $result['name'];
        $tid = $this->createTag($tag_name, $row);

        // Add the mapped TID to match our tag name.
        if ($tid) {
          $tags[] = $tid;
        }

      }
      $row->setSourceProperty('tags', $tags);
    }

    // Replace inline files and images in the body,
    // and set for placement in the body and teaser fields.
    $body = $row->getSourceProperty('body');

    // Before doing anything with the body, check if empty.
    // If empty, check for external redirects.
    if (empty($body) || $body[0]['value'] == '') {
      $nid = $row->getSourceProperty('nid');
      $node_path = 'node/' . $nid;

      // Establish an array of paths to check for redirects.
      $paths = [$node_path];

      $aliases = $this->select('url_alias', 'a')
        ->fields('a', ['alias'])
        ->condition('a.source', $node_path, 'IN')
        ->execute();

      // Add any results to the paths to check against array.
      if (isset($aliases)) {
        foreach ($aliases as $result) {
          $paths[] = $result['alias'];
        }
      }

      // Check and return any redirects for the paths in our array,
      // as well as any redirect options.
      $redirects = $this->select('redirect', 'r')
        ->fields('r', ['redirect', 'redirect_options'])
        ->condition('r.source', $paths, 'IN')
        ->execute();

      if ($redirects) {
        // There should just be one, but use the external/last one.
        foreach ($redirects as $redirect) {
          if (UrlHelper::isExternal($redirect['redirect'])) {
            $target = $redirect['redirect'];
            // We need to unserialize the options,
            // and then check if there is a query (there are
            // other options possible we don't need).
            $options = unserialize($redirect['redirect_options'], [
              'allowed_classes' => TRUE,
            ]);
            if (!empty($options) && isset($options['query'])) {
              // Start our query string, and
              // if we're not the first query parameter,
              // we need to include an extra '&',
              // so initialize 'first' as a check for this.
              $query_string = '?';
              $first = TRUE;
              foreach ($options['query'] as $prop => $val) {
                $query_string .= ($first) ? '' : '&';
                $first = FALSE;
                $query_string .= "{$prop}={$val}";
              }
              $target .= $query_string;
            }
          }
        }
        if (isset($target)) {
          $row->setSourceProperty('field_article_source_link_direct', 1);
          $row->setSourceProperty('custom_source_link', $target);
          $this->logger->notice($this->t('From original node @nid, added source link based on @redirect redirect.', [
            '@redirect' => $target,
            '@nid' => $nid,
          ]));
        }
      }
    }

    if (!empty($body)) {
      $this->viewMode = 'medium__no_crop';
      $this->align = 'left';

      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      // Check for captions in the old format, and if found,
      // manually insert them into the drupal-media element.
      $body[0]['value'] = preg_replace_callback('%<div class=\"(image|video)-(.*?)-(.*?)\">(<drupal-media.*?)><\/drupal-media>(.*?)<\/div>%is', [
        $this,
        'captionReplace',
      ], $body[0]['value']);
      // Check for callouts in the source,
      // and construct the proper format for the destination.
      $body[0]['value'] = preg_replace_callback('|<div class=\"(.*?)-callout\">(.*?)<\/div>|is', [
        $this,
        'calloutReplace',
      ], $body[0]['value']);

      $row->setSourceProperty('body', $body);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    // Process the gallery images.
    $gallery = $row->getSourceProperty('field_photo_gallery');
    if (!empty($gallery)) {

      // The d7 galleries are a separate entity, so we need to fetch it
      // and then process the individual images attached.
      $gallery_query = $this->select('field_data_field_gallery_photos', 'g')
        ->fields('g')
        ->condition('g.entity_id', $gallery[0]['target_id'], '=');
      // Grab title and alt directly from these tables,
      // as they are the most accurate for the photo gallery images.
      $gallery_query->join('field_data_field_file_image_title_text', 'title', 'g.field_gallery_photos_fid = title.entity_id');
      $gallery_query->join('field_data_field_file_image_alt_text', 'alt', 'g.field_gallery_photos_fid = alt.entity_id');
      $images = $gallery_query->fields('title')
        ->fields('alt')
        ->execute();
      $new_images = [];
      foreach ($images as $image) {
        // On the source site, the image title is used as the caption
        // in photo galleries, so pass it in as the global caption
        // parameter for the new site.
        $new_images[] = $this->processImageField($image['field_gallery_photos_fid'], $image['field_file_image_alt_text_value'], $image['field_file_image_title_text_value'], $image['field_file_image_title_text_value']);
      }
      $row->setSourceProperty('gallery', $new_images);
    }

    // Truncate the featured image caption, if needed,
    // and add a message to the migrate table for future followup.
    $caption = $row->getSourceProperty('field_primary_media_caption');
    if (!empty($caption)) {
      if (strlen($caption[0]['value']) > 255) {
        $message = 'Field image caption truncated. Original caption was: ' . $caption[0]['value'];

        // Need to limit to lower than the actual 255 limit,
        // to account for added ellipsis as well as giving
        // some buffer room for possible encoded characters like ampersands.
        $caption[0]['value'] = $this->extractSummaryFromText($caption[0]['value'], 245);
        $row->setSourceProperty('field_primary_media_caption', $caption);

        // Add a message to the migration that can be queried later.
        // The following query can then be used:
        // "SELECT migrate_map_now_news_feature.destid1 AS NODE_ID,
        // migrate_message_now_news_feature.message AS MESSAGE
        // FROM migrate_map_now_news_feature JOIN
        // migrate_message_now_news_feature ON
        // migrate_map_now_news_feature.source_ids_hash =
        // migrate_message_now_news_feature.source_ids_hash;".
        $this->migration
          ->getIdMap()
          ->saveMessage(['nid' => $row->getSourceProperty('nid')], $message);
      }
    }

    // Process the primary media field.
    $media = $row->getSourceProperty('field_primary_media');
    if (!empty($media)) {

      // Check if it's a video or image.
      $filemime = $this->select('file_managed', 'fm')
        ->fields('fm', ['filemime'])
        ->condition('fm.fid', $media[0]['fid'], '=')
        ->execute()
        ->fetchField();

      // If it's an image, we can handle it like normal.
      if (str_starts_with($filemime, 'image')) {
        $fid = $this->processImageField($media[0]['fid'], $media[0]['alt'], $media[0]['title']);
        $row->setSourceProperty('field_primary_media', $fid);
        // Check the aspect ratio of the featured image.
        // If it's 3:2 or wider, set the display to use
        // the site-wide-default. If it's more square or taller,
        // or if we can't determine it,
        // set it to not display.
        if (!empty($media[0]['width'])
          && !empty($media[0]['height']
          && $media[0]['width'] / $media[0]['height'] >= 1.5)) {
          $row->setSourceProperty('featured_image_display', '');
        }
        else {
          $row->setSourceProperty('featured_image_display', 'do_not_display');
        }
      }
      elseif (in_array($filemime, ['video/oembed', 'application/octet-stream'])) {
        $body = $row->getSourceProperty('body');

        // Check to see if body has media in it, then set alignment.
        // If there is no media or the first media is far enough away,
        // left align the video, otherwise center align so that it
        // doesn't overlap later media.
        if (preg_match('/drupal-media/is', substr($body[0]['value'], 0, 700)) === 0) {
          $video = $this->createVideo($media[0]['fid'], 'right');
        }
        else {
          $video = $this->createVideo($media[0]['fid']);
        }

        $body[0]['value'] = $video . $body[0]['value'];
        $row->setSourceProperty('body', $body);
      }
    }

    // If there's a byline, prepend 'By: ' to it
    // since it is being added to the article source field,
    // and would otherwise be unlabeled.
    $byline = $row->getSourceProperty('field_by_line');
    if (!empty($byline)) {
      $byline = 'Written by: ' . $byline[0]['value'];
      $row->setSourceProperty('field_by_line', $byline);
    }

    return TRUE;
  }

  /**
   * Helper function to add an image caption during a preg_replace.
   */
  private function captionReplace($match) {

    // Match[1] denotes whether it is an image or video.
    // Match[2] is the alignment in the source.
    // Match[3] is the pixel-width in the source.
    // Match[4] is most of the drupal-media element,
    // and match[5] is the image caption.
    // Here we're adding the caption and then re-closing
    // the drupal-media element.
    // First, remove extra breaks that were used in source for
    // visual spacing.
    $match[5] = preg_replace('%(<br>|<br \/>)%is', ' ', $match[5]);
    // Then remove any extraneous spaces.
    $match[5] = trim(preg_replace('/\s\s+/', ' ', $match[5]));

    // Process the alignment and size in here as well.
    // Because they are part of a wrapper div, it has to be processed
    // here, as our base tooling can't currently handle wrappers.
    switch ($match[1]) {
      case 'video':
        $size = match ($match[3]) {
          '320' => 'small',
          // 640 or default should go to medium.
          default => 'medium',
        };
        break;

      case 'image':
      default:
        $size = match ($match[3]) {
          '150' => 'small__no_crop',
          '640' => 'large__no_crop',
          // 320 or default should go to medium.
          default => 'medium__no_crop',
        };
    }
    $match[4] = preg_replace('%(data-align=\")(.*?)(\")%is', '$1' . $match[2] . '$3', $match[4]);
    $match[4] = preg_replace('%(data-view-mode=\")(.*?)(\")%is', '$1' . $size . '$3', $match[4]);

    return $match[4] . ' data-caption="' . $match[5] . '"></drupal-media>';
  }

  /**
   * Helper function to update a callout during a preg_replace.
   */
  private function calloutReplace($match) {
    // Match[1] is the "left" or "right" of the callout
    // alignment. Match[2] is the interior content of the <div>.
    // Extra spacings have been added in various places
    // for visual spacing. Remove them, or they'll throw
    // things off in the new callout component.
    // Remove anything after the first '<br>',
    // as it is not in the same '<strong>' group
    // as the ones on the first line.
    $headline_match_string = preg_replace('%(<br>|<br \/>|<br\/>).*%is', '', $match[2]);

    // Look for a headline to use in the callout, which are bolded strings
    // at the start of the callout. Also look for any additional
    // line breaks. Like before, here they are unnecessary.
    $headline = '';
    if (preg_match_all("|<strong>(.*?)<\/strong>(<br>)*|is", $headline_match_string, $headline_matches)) {
      // Build the headline if we found one.
      $headline_classes = implode(' ', [
        'headline',
        'block__headline',
        'headline--serif',
        'headline--underline',
        'headline--center',
      ]);

      // If there are multiple <strong>'s, then we need to concatenate them.
      $headline_text = '';
      foreach ($headline_matches[1] as $value) {
        $headline_text .= $value;
        // If we're adding the headline separately,
        // remove it from the rest of the text, so we don't duplicate.
        $match[2] = str_replace('<strong>' . $value . '</strong>', '', $match[2]);
      };

      $headline = '<h4 class="' . $headline_classes . '">';
      $headline .= '<span class="headline__heading">';
      $headline .= $headline_text;
      $headline .= '</span></h4>';
    }

    // Remove all leading and trailing 'br' tags.
    $match[2] = preg_replace("%^(<br>|<br \/>|<br\/>\s)*%is", '', $match[2], 1);
    $match[2] = preg_replace("%(<br>|<br \/>|<br\/>$|\s)*%is", '', $match[2], 1);

    // Build the callout wrapper and return.
    // We're defaulting to medium size, but taking the
    // alignment from the source.
    $wrapper_classes = 'block--word-break callout bg--gray inline--size-small inline--align-' . $match[1];
    return '<div class="' . $wrapper_classes . '">' . $headline . '<p>' . $match[2] . '</p>' . '</div>';
  }

  /**
   * Helper function to check for existing tags and create if they don't exist.
   */
  private function createTag($tag_name, $row) {
    // Check if we already have the tag in the destination.
    $result = \Drupal::database()
      ->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid'])
      ->condition('t.vid', 'tags', '=')
      ->condition('t.name', $tag_name, '=')
      ->execute()
      ->fetchField();
    if ($result) {
      return $result;
    }
    // If we didn't have the tag already,
    // then create a new tag and return its id.
    $term = Term::create([
      'name' => $tag_name,
      'vid' => 'tags',
    ]);
    if ($term->save()) {
      return $term->id();
    }

    // If we didn't save for some reason, add a notice
    // to the migration, and return a null.
    $message = 'Taxonomy term failed to migrate. Missing term was: ' . $tag_name;
    $this->migration
      ->getIdMap()
      ->saveMessage(['nid' => $row->getSourceProperty('nid')], $message);
    return FALSE;
  }

}
