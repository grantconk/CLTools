<form name="advsearch_form">

<table id="search_box">
<caption>
	Advanced Search
</caption>
<tr>
	<td style="background-color:#efefef; border-bottom:1px solid silver;">

		<div style="font-weight:bold; padding-top:4px;">Find results: </div>

		<table cellpadding="0" cellspacing="0" style="padding-left:16px;">
		<tr>
			<td align="right" valign="top" style="padding-top:3px;">MySQL <b>Regular Expression</b>: </td>
			<td><input type="text" name="regexp" value="<?= $_REQUEST['regexp'] ?>" size="30">
				<?php
				// create Number of Results select box
				$vals = array(10=>'10 results', 25=>'25 results', 50=>'50 results', 100=>'100 results', 200=>'200 results');

				// check if form wasn't submitted
				if ( empty($_REQUEST['search']) ) {
					// preset from preferences
					$_REQUEST['num_results'] = $GLOBALS['preferences']->num_results;
				}
				print createHTMLMenu('num_results', $vals, 1);
				?>
				<input type="submit" name="search" value="Search">
				<br>
				OR
			</td>
		</tr>
		<tr>
			<td align="right">with <b>all</b> of the words: </td>
			<td><input type="text" name="all_words" value="<?= stripslashes($_REQUEST['all_words']) ?>" size="30"></td>
		</tr>
		<tr>
			<td align="right">with the <b>exact phrase</b>: </td>
			<td><input type="text" name="exact_phrase" value="<?= stripslashes($_REQUEST['exact_phrase']) ?>" size="30"></td>
		</tr>
		<tr>
			<td align="right">with <b>at least one</b> of the words: </td>
			<td><input type="text" name="one_word" value="<?= stripslashes($_REQUEST['one_word']) ?>" size="30"></td>
		</tr>
		<tr>
			<td align="right"><b>without</b> the words: </td>
			<td><input type="text" name="without_words" value="<?= stripslashes($_REQUEST['without_words']) ?>" size="30"></td>
		</tr>
		</table>
	</td>
</tr>
<tr>
	<td style="background-color:#efefef; border-bottom:1px solid silver;">
		<table>
		<tr>
			<td valign="top" style="padding-right:10px; font-weight:bold;">Categories: </td>
				<?php
				unset($vals);
				// get all categories
				$sql = "SELECT *
						FROM Categories
						WHERE active IS NOT null
						ORDER BY name";
				$database->setQuery($sql);
				$categories = $database->loadObjectList();
				if ( is_array($categories) ) {
					foreach ($categories as $category) {
						$vals[ $category->id ] = ucwords( $category->name );
					}
					print "</td><td>";
					print createHTMLMenu('categories', $vals, (count($vals)<10?count($vals)+1:10));
				}
				?>
			</td>
		</tr>
		</table>
	</td>
</tr>
<tr>
	<td valign="top">
		<table>
		<tr>
			<td style="font-weight:bold;">Date: </td>
			<td>
				<?php
				// create date select box
//				$vals = array(1=>'1 day', 3=>'3 days', 7=>'1 week', 14=>'2 weeks', 30=>'1 month');
				$vals = array('1 day'=>'1 day', '3 days'=>'3 days', '1 week'=>'1 week', '2 weeks'=>'2 weeks', '1 month'=>'1 month');
				print createHTMLMenu('date_span', $vals, 1, '-- Anytime --');
				?>
			</td>
		</tr>
		<tr>
			<td style="font-weight:bold;">Occurrences: </td>
			<td>
				<?php
				// create date select box
				#$vals = array('title,details'=>'both title and details', 'title'=>'title only');
				$vals = array('title'=>'title only','title,details'=>'both title and details');
				print createHTMLMenu('occurrences', $vals, 1);
				?>
			</td>
		</tr>
		</table>
	</td>
</tr>
<tr>
	<td style="background-color:#efefef; border-top:1px solid silver;">
		<table>
		<tr>
			<td valign="top" style="padding-right:10px; font-weight:bold;">Locations: </td>

			<td valign="top"><!-- Region(s): --></td>
			<td valign="top">
				<?php
				unset($vals);
				// get all regions for the U.S. for now
				$sql = "SELECT *
						FROM Regions
						WHERE parent_id=1
							AND active IS NOT null
						ORDER BY name";
				$database->setQuery($sql);
				$regions = $database->loadObjectList();
				if ( is_array($regions) ) {
					foreach ($regions as $region) {
						$vals[ $region->id ] = ucwords( $region->name );
					}
					$js = 'onChange="showSites(this, event);"';

					// check if form was just submitted
					if ( empty($_REQUEST['search']) ) {
						// check if user has preferences
						if ( $GLOBALS['preferences']->regions ) {
							// preset to preferences
							$_REQUEST['regions'] = $GLOBALS['preferences']->regions;
						} else {
							// preset to California
//							$_REQUEST['regions'] = array(2);
						}
					}
					print createHTMLMenu('regions', $vals, (count($vals)<10?count($vals)+1:10), '--All--', $js);
				}
				?>
			</td>

			<td valign="top"><div id="DIV_sites_title" style="display:none;"><!--Site(s): --></div></td>
			<td valign="top">
				<?php
				// loop through the regions again
				foreach ($regions as $region) {
					unset($sites);
					// get sites for this region
					$sql = "SELECT *
							FROM Sites
							WHERE (region_id=$region->id
								OR region_id_alt=$region->id
								OR region_id_alt2=$region->id)
								AND active IS NOT null
							ORDER BY name";
					$database->setQuery($sql);
					$results = $database->loadObjectList();
					foreach ($results as $r) {
						$sites[ $r->id ] = ucwords( $r->name );
					}
					// create sites ID
					$sites_id = 'sites_'. $region->id;

					$js = 'onChange="showSubSites(this, event);"';

					// check if this region is preselected
					if ( is_array($_REQUEST['regions']) && in_array($region->id, array_values($_REQUEST['regions'])) ) {
						// preset to preferences first, or null
//						$_REQUEST[ $sites_id ] = ($GLOBALS['preferences']->sites ? $GLOBALS['preferences']->sites : null);
					}

					// check if form was just submitted
					if ( empty($_REQUEST['search']) ) {
						// check if user has preferences
						if ( $GLOBALS['preferences']->sites ) {
							// preset to preferences
							$_REQUEST['sites'] = $GLOBALS['preferences']->sites;
						}
					}
					?>
					<div id="DIV_<?= $sites_id ?>" style="display:<?= (is_array($_REQUEST['regions']) && in_array($region->id, $_REQUEST['regions']) ? 'normal' : 'none') ?>;">
						<?php
						if ( $sites ) {
							print createHTMLMenu($sites_id, $sites, (count($sites)<10?count($sites)+1:10), '-- All of '. ucwords( $region->name ) .' --', $js);
						} else {
							print "<span style='color:silver;'>All of ". ucwords( $region->name ) .".</span>";
						}
						?>
					</div>
					<?php
				}
				?>
			</td>

			<td valign="top"><div id="DIV_subsites_title" style="display:none;"><!--SubSite(s): --></div></td>
			<td valign="top">
				<?php
				// get unique site IDs that have subsites
				#$sql = "SELECT DISTINCT site_id
				$sql = "SELECT DISTINCT site_id
						FROM SubSites
						LEFT JOIN Sites on Sites.id=SubSites.site_id
						ORDER BY Sites.name";
				$database->setQuery($sql);
				$site_ids = $database->loadResultArray();
				if ( is_array($site_ids) ) {
					foreach ($site_ids as $site_id) {
						##$sql = "SELECT name FROM Sites WHERE id=$site_id";
						##$site_name = $database->loadResult();
						unset($vals);
						// create subsite ID
						$subsites_id = 'subsites_'. $site_id;

						// get subsites for this site
						$sql = "SELECT *
								FROM SubSites
								WHERE site_id =$site_id
									AND active IS NOT null
								ORDER BY name";
						$database->setQuery($sql);
						$subsites = $database->loadObjectList();
						if ( is_array($results) ) {
							foreach ($subsites as $subsite) {
								$vals[ $subsite->id ] = ucwords( $subsite->name );
							}
						}
						?>
						<div id="DIV_<?= $subsites_id ?>" style="display:<?= (is_array($_REQUEST['sites']) && in_array($site->id, $_REQUEST['sites']) ? 'normal' : 'none') ?>;">
							<?php
							if ( $vals ) {
								#print createHTMLMenu($subsites_id, $vals, (count($vals)<10?count($vals)+1:10), '-- All of '. ucwords( $subsite->name ) .' --');
								print createHTMLMenu($subsites_id, $vals, (count($vals)<10?count($vals)+1:10), '-- All of '. $site_name .' --');
							} else {
								print "<span style='color:silver;'>All of ". ucwords( $subsite->name ) .".</span>";
							}
							?>
						</div>
						<?php
					}
				}
				?>
			</td>
		</tr>
		</table>
	</td>
</tr>
</table>

</form>

<script language="JavaScript" type="text/javascript">
function showSites (ob, event)
{
	forceHideDiv('DIV_sites_title', event);
	selected = new Array();
	for (var x=0; x < ob.options.length; x++) {
		if (ob.options[ x ].selected) {
			//selected.push(ob.options[ i ].value);
//			alert('DIV_sites_'+ ob.options[ x ].value);
			forceShowDiv('DIV_sites_'+ ob.options[ x ].value, event);
			forceShowDiv('DIV_sites_title', event);
		} else {
			forceHideDiv('DIV_sites_'+ ob.options[ x ].value, event);
		}
	}
}

function showSubSites (ob, event)
{
	forceHideDiv('DIV_subsites_title', event);
	selected = new Array();
	for (var x=0; x < ob.options.length; x++) {
		if (ob.options[ x ].selected) {
			//selected.push(ob.options[ i ].value);
//			alert('DIV_subsites_'+ ob.options[ x ].value);
			forceShowDiv('DIV_subsites_'+ ob.options[ x ].value, event);
			forceShowDiv('DIV_subsites_title', event);
		} else {
			forceHideDiv('DIV_subsites_'+ ob.options[ x ].value, event);
		}
	}
}
</script>
