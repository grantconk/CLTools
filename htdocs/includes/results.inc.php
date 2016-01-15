<?php
// check if form was submitted
if ( $_REQUEST['search'] ) {

	// check if regular expression field was used
	if ( $_REQUEST['regexp'] ) {
		// get pattern
		$pattern = $_REQUEST['regexp'];

		// create WHERE clause
		$where = "flagged IS NULL AND (title LIKE '%$pattern%' ";

		// check if we're checking details (body) as well
		if ( preg_match("/details/", $_REQUEST['occurrences']) ) {
			$where .= " OR details LIKE '%$pattern%'";
		}
		$where .= ')';
	} else {
		// check for all words
		if ( $_REQUEST['all_words'] ) {
			// get words
			$_REQUEST['all_words'] = preg_replace("/,(\s*)/", ' ', $_REQUEST['all_words']);
			$all_words = explode(' ', $_REQUEST['all_words']);

			// create WHERE clause
			foreach($all_words as $word) {
				if ($where) {
					$where .= ' AND ';
				}
				$where .= "(title LIKE '%$word%' ";

				// check if we're checking details (body) as well
				if ( preg_match("/details/", $_REQUEST['occurrences']) ) {
					$where .= " OR details LIKE '%$word%'";
				}
				$where .= ')';
			}
		}
		if ( $_REQUEST['exact_phrase'] ) {
			// check if this extends an existing WHERE clause
			if ($where) {
				$where .= ' AND ';
			}
			// get phrase
			$exact_phrase = $_REQUEST['exact_phrase'];

			// create WHERE clause
			$where .= "(title LIKE '%$exact_phrase%' ";

			// check if we're checking details (body) as well
			if ( preg_match("/details/", $_REQUEST['occurrences']) ) {
				$where .= " OR details LIKE '%$exact_phrase%'";
			}
			$where .= ')';
		}
		if ( $_REQUEST['one_word'] ) {
			// check if this extends an existing WHERE clause
			if ($where) {
				$where .= ' AND ';
			}
			$where .= '(';

			// get words
			$_REQUEST['one_word'] = preg_replace("/,(\s*)/", ' ', $_REQUEST['one_word']);
			$one_words = explode(' ', $_REQUEST['one_word']);

			// create WHERE clause
			$count = count($one_words);
			foreach($one_words as $word) {
				$where .= "(title LIKE '%$word%' ";

				// check if we're checking details (body) as well
				if ( preg_match("/details/", $_REQUEST['occurrences']) ) {
					$where .= " OR details LIKE '%$word%'";
				}
				$where .= ')';

				if (--$count) {
					$where .= ' OR ';
				}
			}
			$where .= ')';
		}
		if ( $_REQUEST['without_words'] ) {
			// get words
			$_REQUEST['without_words'] = preg_replace("/,(\s*)/", ' ', $_REQUEST['without_words']);
			$without_words = explode(' ', $_REQUEST['without_words']);

			// create WHERE clause
			foreach($without_words as $word) {
				if ($where) {
					$where .= ' AND ';
				}
				$where .= "(title NOT LIKE '%$word%' ";

				// check if we're checking details (body) as well
				if ( preg_match("/details/", $_REQUEST['occurrences']) ) {
					$where .= " OR details NOT LIKE '%$word%'";
				}
				$where .= ')';
			}
		}
		if ( $_REQUEST['date_span'] ) {
			// check if this extends an existing WHERE clause
			if ($where) {
				$where .= ' AND ';
			}
			$where .= "date_posted < DATE_SUB(NOW(), INTERVAL $date_span)";
		}
	}

	/* CATEGORIES */

	if ( count($_REQUEST['categories']) > 0 && !empty($_REQUEST['categories'][0])) {

		// get selected categories
		$cat_ids = $_REQUEST['categories'];

		// check if category id is "All"=1
		if (count($cat_ids) > 0 && $cat_ids[0] != 1) {
			// now only search these categories
			$where .= " AND (category_id IS NULL OR category_id=". join($cat_ids, ' OR category_id=') .")";
		}
	}

	/* REGIONS */

	if ( count($_REQUEST['regions']) > 0 && 
		($_REQUEST['regions'][1] || !empty($_REQUEST['regions'][0]))) {

		// get selected regions
		$region_ids = $_REQUEST['regions'];

		// record all sites for later use
		$all_sites = array();

		/* SITES */

		// get selected sites of the selected regions
		foreach ($region_ids as $rid) {

			if ( $sites_where ) {
				$sites_where .= ' OR ';
			}

			// check if region has subsites selected
			if ( count($_REQUEST["sites_$rid"]) > 1 || $_REQUEST["sites_$rid"][0] ) {
				// check if subsites were selected for this site
				# TODO

				// include only these in search
				$sites_where .= 'site_id='. join($_REQUEST["sites_$rid"], ' OR site_id=');
				array_push($all_sites, $_REQUEST["sites_$rid"]);
			} else if ( $_REQUEST["sites_$rid"] ) {
				// check if subsites were selected for this site
				# TODO

				// include all of this region's subsites in search
				$database->setQuery("SELECT id FROM Sites WHERE region_id=$rid AND active IS NOT null");
				$sids = $database->loadResultArray();
				if ( is_array($sids) ) {
					$sites_where .= 'site_id='. join($sids, ' OR site_id=');
					array_push($all_sites, $sids);
				}
			}
		}
		if ($sites_where) {
			// prepend to where clause
			$where .= " AND ($sites_where)";
		} else {
			// might be because a region was specified that does not include any sites
			print "No WHERE conditions.";
			return;
		}

if ($make_function) {
		/* SUBSITES */

		// check if this extends an existing WHERE clause
		if ($where) {
			$where .= ' AND ';
		}
		$where .= '(';

		$database->setQuery("SELECT id FROM Sites WHERE active IS NOT null");
		$sids = $database->loadResultArray();
		if ( is_array($results) ) {
			foreach ($results as $r) {
				array_push($sids, $r->site_id);
			}
		}

		$region_count = count($_REQUEST['regions']);
		unset($sids);
		foreach ($_REQUEST['regions'] as $region_id) {
			// get site field name (ie: sites_6)
			$sites_field = "sites_$region_id";
			array_push($all_site_fields, $sites_field);

			// check for specific sites
			if ( $_REQUEST[ $sites_field ] && $_REQUEST[ $sites_field ][0] ) {
				$sids = $_REQUEST[ $sites_field ];
			} else {
				// specify all sites for this region, region select box should have these as its value (using [ ])
				$sids = $sites[$region_id];
			}
			if ($sids) {
				$sites_count = count($sids);
				foreach ($sids as $site_id) {
					$where .= "site_id=$site_id";
					if (--$sites_count) {
						$where .= ' OR ';
					}
				}
			}
			if (--$region_count) {
				$where .= ' OR ';
			}
		}
		$where .= ')';
}
	}

	// get limit		NOTE: need to make this page-by-page later
	$limit = $_REQUEST['num_results'];

	// check if we have WHERE clause and ORDER BY statements now
	if ( $where ) {
		$sql = "SELECT *
				FROM Posts
				WHERE $where
				ORDER BY date_posted
				LIMIT $limit";
		$database->setQuery($sql);
		$results = $database->loadObjectList();

		print "Results: ". count($results) ." were found.<br>";
		#print "<div style='color:silver'>$sql</div>";
	} else {
		$err = "Search could not be performed with empty search fields.";
	}

	// check if errors
	if ( $err ) {
		printError($err);
	}

	// check if we have results
	if ( $results ) {
		?>
		<blockquote>
			<?php
			// loop through results and show them
			foreach ($results as $result) {
				// create tool tip arguments
				$desc_title = htmlentities( $result->title );
				unset($desc_args);

				$desc_args['Price'] = ($result->price ? '$'. $result->price : 'Not specified.');
				if ( $result->site_id ) {
					$desc_args['Region'] = ucwords( $GLOBALS['regions'][ $GLOBALS['site_ids'][ $result->site_id ]->region_id ]->name );
					$desc_args['Site'] = ucwords( $GLOBALS['site_ids'][ $result->site_id ]->name );
				}
				if ( $result->subsite_id ) {
					$desc_args['SubSite'] = ucwords( $GLOBALS['subsite_ids'][ $result->subsite_id ]->name );
				}
				if ( $result->city ) {
					$desc_args['City'] = ucwords( $result->city );
				}
				if ( $result->category_id ) {
					$desc_args['Category'] = ucwords( $GLOBALS['category_ids'][ $result->category_id ]->name );
				}
				if ( $result->images ) {
					foreach ($result->images as $img) {
						$desc_args['Images'] = "<img src='$img'>";
					}
				}
				?>
				<a href="details.php?id=<?= $result->id ?>"
					onMouseover="ddrivetip('<?= createTipTable($desc_title, $desc_args) ?>','white',300);";
					onMouseout="hideddrivetip()"><?= $result->title ?></a> 
				<?= ($result->price ? ' - $'. $result->price : null) ?>
				- <?= ($desc_args['Category'] ? $desc_args['Category'] : null) ?>
				- <a href="<?= $result->url ?>" style='color:silver'>original link</a>
				<br>
				<?php
			}
			?>
		</blockquote>
		<?php
	}
}
?>
