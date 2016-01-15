<?php
/**
 * Configurations for CLTools
 * 
 * @version $Id$
 * @package CLTools
 * @author Grant Conklin
 * @copyright 2006 Grant Conklin
 */

// site info
$CONFIG['site'] = new stdClass();
$CONFIG['site']->name = 'Craigs List Tools';
// database
$CONFIG['db'] = new stdClass();
$CONFIG['db']->name = 'gc_cltools';
$CONFIG['db']->host = 'db.caffeinated.org';
$CONFIG['db']->user = 'cltools';
$CONFIG['db']->pass = 'slootlc';
// absolute directories
$CONFIG['dir'] = new stdClass();
$CONFIG['dir']->root = '/home/gconklin/caffeinated.org/cltools';
$CONFIG['dir']->htdocs = $CONFIG['dir']->root;
$CONFIG['dir']->includes = $CONFIG['dir']->root .'/includes';
$CONFIG['dir']->phplibs = '/home/gconklin/php';
// url paths
$CONFIG['path'] = new stdClass();
$CONFIG['path']->root = '/cltools';
$CONFIG['path']->includes = $CONFIG['path']->root .'/includes';
$CONFIG['path']->images = $CONFIG['path']->root .'/images';
?>
