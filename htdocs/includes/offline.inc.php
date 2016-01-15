<?php
/**
 * Craigs List Tools offline page
 * 
 * @version $Id$
 * @package CLTools
 * @author Grant Conklin
 * @copyright 2006 Entrisphere
 */
global $CONFIG;
?>

<div id="intranet_offline" style="width: 430px; height: 110px;">
	<h2><?= $CONFIG['site']->name ?></h2>

	<?
	if ($systemError) {
		?>
		<h4 style="color:red"><?= $systemError ?></h4>
		<?
	}
	else {
		?>
		<h4><?= $CONFIG['message']->offline ?></h4>
		<?
	}
	?>
</div>