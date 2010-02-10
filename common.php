<?php
/**
 * Allow users to tag a page
 *
 * @author Adrian Lang <lang@cosmocode.de>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'tagging/tagging_phptagengine.php';

/**
 * Initialize the phptagengine instance
 */
function tagging_get_pte($plugin) {
    static $pte = null;
    if (!is_null($pte)) {
        return $pte;
    }

    global $conf;

    require_once 'adodb/adodb.inc.php';

    if ($plugin->getConf('db_dsn') === '') return null;
    $db = ADONewConnection($plugin->getConf('db_dsn'));
    if (!$db) return null;
    $pte = new tagging_phptagengine(
              $db,
              $plugin->getConf('db_prefix'),
              $plugin->getLang('search_section_title'),
              isset($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : '',
              isset($conf['lang']) ? $conf['lang'] : 'en');
    return $pte;
}


function cloud_weight(&$tags,$min,$max,$levels){
    // calculate tresholds
    $tresholds = array();
    for($i=0; $i<=$levels; $i++){
        $tresholds[$i] = pow($max - $min + 1, $i/$levels) + $min - 1;
    }

    // assign weights
    foreach($tags as $tag => $cnt){
        foreach($tresholds as $tresh => $val){
            if($cnt <= $val){
                $tags[$tag] = $tresh;
                break;
            }
            $tags[$tag] = $levels;
        }
    }
}

