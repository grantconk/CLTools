<?php
include_once('includes/CLTools.php');

include($CONFIG['dir']->includes .'/header.inc.php');

if ( $id = $_REQUEST['id'] ) {
	$sql = "SELECT *
				FROM Posts
				WHERE id=$id
				ORDER BY date_posted";
		$database->setQuery($sql);
		$results = $database->loadObjectList();
		$post = $results[0];

		if ($post->title) {
			?>
			<table>
			<caption id="header_bar">
				<a style="text-decoration:none" href="<?= $post->url ?>"><?= $post->title ?></a>
			</caption>
			</table>
			<div style="margin:10px; padding:10px 25px 10px 25px; border:1px solid gray; background-color:#F2F2F2">
				<?= $post->details ?>
			</div>
			<?php
		}
}

include($CONFIG['dir']->includes .'/footer.inc.php');
?>
