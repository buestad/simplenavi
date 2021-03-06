<?php
/**
 * DokuWiki Plugin simplenavi (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'syntax.php';
require_once DOKU_INC.'inc/search.php';

class syntax_plugin_simplenavi extends DokuWiki_Syntax_Plugin {
    function getType() {
        return 'substition';
    }

    function getPType() {
        return 'block';
    }

    function getSort() {
        return 155;
    }


    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{simplenavi>[^}]*}}',$mode,'plugin_simplenavi');
    }

    function handle($match, $state, $pos, Doku_Handler $handler){
        $data = array(cleanID(substr($match,13,-2)));

        return $data;
    }

    function render($mode, Doku_Renderer $R, $pass) {
        if($mode != 'xhtml') return false;

        global $conf;
        global $INFO;
        $R->info['cache'] = false;

        $ns = utf8_encodeFN(str_replace(':','/',$pass[0]));
        $data = array();
        search($data,$conf['datadir'],array($this,'_search'),array('ns' => $INFO['id']),$ns,1,'natural');
        if ($this->getConf('sortByTitle') == true) {
		//if ($conf['useheading']) {	
			$this->_sortByTitle($data,"id");
        } else {
            if ($this->getConf('sort') == 'ascii') {
                uksort($data, array($this, '_cmp'));
            }
        }

        $R->doc .= '<div class="plugin__simplenavi">';
        $R->doc .= html_buildlist($data,'idx',array($this,'_list'),array($this,'_li'));
        $R->doc .= '</div>';

        return true;
    }

    function _list($item){
        global $INFO;

        if(($item['type'] == 'd' && $item['open']) || $INFO['id'] == $item['id']){
            return '<strong>'.html_wikilink(':'.$item['id'],$this->_title($item['id'])).'</strong>';
        }else{
            return html_wikilink(':'.$item['id'],$this->_title($item['id']));
        }

    }

    function _li($item){
        if($item['type'] == "f"){
            return '<li class="level'.$item['level'].'">';
        }elseif($item['open']){
            return '<li class="open">';
        }else{
            return '<li class="closed">';
        }
    }

    function _search(&$data,$base,$file,$type,$lvl,$opts){
        global $conf;
        $return = true;

        $item = array();

        $id = pathID($file);

        if($type == 'd' && !(
            preg_match('#^'.$id.'(:|$)#',$opts['ns']) ||
            preg_match('#^'.$id.'(:|$)#',getNS($opts['ns']))

        )){
            //add but don't recurse
            $return = false;
        }elseif($type == 'f' && (!empty($opts['nofiles']) || substr($file,-4) != '.txt')){
            //don't add
            return false;
        }

        if($type=='d' && $conf['sneaky_index'] && auth_quickaclcheck($id.':') < AUTH_READ){
            return false;
        }

        if($type == 'd'){
            // link directories to their start pages
            $exists = false;
            $id = "$id:";
            resolve_pageid('',$id,$exists);
            $this->startpages[$id] = 1;
        }elseif(!empty($this->startpages[$id])){
            // skip already shown start pages
            return false;
        }elseif(noNS($id) == $conf['start']){
            // skip the main start page
            return false;
        }

        //check hidden
        if(isHiddenPage($id)){
            return false;
        }

        //check ACL
        if($type=='f' && auth_quickaclcheck($id) < AUTH_READ){
            return false;
        }

        $data[$id]=array( 'id'    => $id,
                       'type'  => $type,
                       'level' => $lvl,
                       'open'  => $return);
        return $return;
    }

    function _title($id) {
        global $conf;

        if(useHeading('navigation')){
            $p = p_get_first_heading($id);
        }
        if(!empty($p)) return $p;

        $p = noNS($id);
        if ($p == $conf['start'] || $p == false) {
            $p = noNS(getNS($id));
            if ($p == false) {
                return $conf['start'];
            }
        }
        return $p;
    }

    function _cmp($a, $b) {
        global $conf;
        $a = preg_replace('/'.preg_quote($conf['start'], '/').'$/', '', $a);
        $b = preg_replace('/'.preg_quote($conf['start'], '/').'$/', '', $b);
		
		//This handles leading zeros can be skipped
		$callback = function($digit) {
			$digit = $digit[0];
			$dlen = strlen($digit);
			for($i = $dlen; $i<4; $i++)
				$digit = "0$digit";
			return $digit;
		};
		
        $aArr = preg_split("/(:)/",$a);
        $bArr = preg_split("/(:)/",$b);
		$ret = 0;
		$len = min(count($aArr), count($bArr));
		for($i=0; $i<$len; $i++)
		{
			$aa = preg_replace_callback('~\d+~', $callback, $aArr[$i]);
			$bb = preg_replace_callback('~\d+~', $callback, $bArr[$i]);
			$ret = strcmp($aa, $bb);
			if($ret != 0)
				return $ret;
		}
		if(count($aArr) > $len)
			return 1;
		if(count($bArr) > $len)
			return -1;
		return 0;
    }
	
	function _resolveTitlePath($pageId){
		$ret = $this->_title($pageId);
		$pageId = preg_replace("/:start$/", "", $pageId);
		$nsp = preg_split("/(:)/",$pageId);
		$endId = $nsp[count($nsp)-1];
		
		//check if the name is different from the page
		if($ret == $endId) {
			$ret = $this->_title($pageId.":start");
			if($ret == "start")
				$ret = $endId;
		}	
		//check if there are more to resolve
		if(count($nsp)>1) {
			$parentId = "";
			for($i = 0; $i < count($nsp)-1; $i++)
				$parentId = $parentId.":".$nsp[$i];
			$parentId = preg_replace("/^:/", "", $parentId);
			$ret = $this->_resolveTitlePath($parentId) . ":" . $ret;
		}
		return $ret;
	}

    function _sortByTitle(&$array, $key) {
        $sorter = array();
        $ret = array();
        reset($array);
        foreach ($array as $ii => $va) {
            $sorter[$ii] = $this->_resolveTitlePath($va[$key]);
        }
        if ($this->getConf('sort') == 'ascii') {
            uasort($sorter, array($this, '_cmp'));
        } else {
            natcasesort($sorter);
        }
        foreach ($sorter as $ii => $va) {
            $ret[$ii] = $array[$ii];
        }
        $array = $ret;
    }

}

// vim:ts=4:sw=4:et:
