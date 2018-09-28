<?php

/*
 * [UCenter] (C)2001-2009 Comsenz Inc.
 * This is NOT a freeware, use is subject to license terms
 *
 * $Id: template.class.php 845 2008-12-08 05:36:51Z zhaoxiongfei $
 * Updated for php7 Pentyum
 */
class template
{

    var $tpldir;

    var $objdir;

    var $tplfile;

    var $objfile;

    var $langfile;

    var $vars;

    var $force = 0;

    var $var_regexp = "\@?\\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\[\]\$]+\])*";

    var $vtag_regexp = "\<\?=(\@?\\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\[\]\$]+\])*)\?\>";

    var $const_regexp = "\{([\w]+)\}";

    var $languages = array();

    var $sid;

    function __construct()
    {
        $this->template();
    }

    function template()
    {
        ob_start();
        $this->defaulttpldir = UC_ROOT . './view/default';
        $this->tpldir = UC_ROOT . './view/default';
        $this->objdir = UC_DATADIR . './view';
        $this->langfile = UC_ROOT . './view/default/templates.lang.php';
        if (version_compare(PHP_VERSION, '5') == - 1) {
            register_shutdown_function(array(
                &$this,
                '__destruct'
            ));
        }
    }

    function assign($k, $v)
    {
        $this->vars[$k] = $v;
    }

    function display($file)
    {
        extract($this->vars, EXTR_SKIP);
        include $this->gettpl($file);
    }

    function gettpl($file)
    {
        isset($_REQUEST['inajax']) && ($file == 'header' || $file == 'footer') && $file = $file . '_ajax';
        isset($_REQUEST['inajax']) && ($file == 'admin_header' || $file == 'admin_footer') && $file = substr($file, 6) . '_ajax';
        $this->tplfile = $this->tpldir . '/' . $file . '.htm';
        $this->objfile = $this->objdir . '/' . $file . '.php';
        $tplfilemtime = @filemtime($this->tplfile);
        if ($tplfilemtime === FALSE) {
            $this->tplfile = $this->defaulttpldir . '/' . $file . '.htm';
        }
        if ($this->force || ! file_exists($this->objfile) || @filemtime($this->objfile) < filemtime($this->tplfile)) {
            if (empty($this->language)) {
                @include $this->langfile;
                if (is_array($languages)) {
                    $this->languages += $languages;
                }
            }
            $this->complie();
        }
        return $this->objfile;
    }

    function get_lang($match)
    {
        return $this->lang($match[1]);
    }

    function get_stripvtag($match)
    {
        return $this->stripvtag('<? ' . $match[1] . '?>');
    }

    function get_stripvtag_for($match)
    {
        return $this->stripvtag('<? for(' . $match[1] . ') { ?>');
    }

    function get_stripvtag_elseif($match)
    {
        return $this->stripvtag('<? } elseif(' . $match[1] . ') { ?>');
    }

    function get_stripvtag_if($match)
    {
        return $this->stripvtag('<? if(' . $match[1] . ') { ?>');
    }

    function get_stripvtag_include_tpl($match)
    {
        return $this->stripvtag('<? include \$this->gettpl(' . $match[1] . '); ?>');
    }

    function get_loopsection_4($match)
    {
        return $this->loopsection($match[1], $match[2], $match[3], $match[4]);
    }

    function get_loopsection_3($match)
    {
        return $this->loopsection($match[1], '', $match[2], $match[3]);
    }

    function get_array_index($match)
    {
        return $this->arrayindex($match[1], $match[2]);
    }

    function complie()
    {
        $template = file_get_contents($this->tplfile);
        $template = preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $template);
        $template = preg_replace_callback("/\{lang\s+(\w+?)\}/is", array(
            $this,
            "get_lang"
        ), $template);
        
        $template = preg_replace("/\{($this->var_regexp)\}/", "<?=\\1?>", $template);
        $template = preg_replace("/\{($this->const_regexp)\}/", "<?=\\1?>", $template);
        $template = preg_replace("/(?<!\<\?\=|\\\\)$this->var_regexp/", "<?=\\0?>", $template);
        
        $template = preg_replace_callback("/\<\?=(\@?\\\$[a-zA-Z_]\w*)((\[[\\$\[\]\w]+\])+)\?\>/is", array(
            $this,
            "get_array_index"
        ), $template);
        
        $template = preg_replace_callback("/\{\{eval (.*?)\}\}/is", array(
            $this,
            "get_stripvtag"
        ), $template);
        $template = preg_replace_callback("/\{eval (.*?)\}/is", array(
            $this,
            "get_stripvtag"
        ), $template);
        $template = preg_replace_callback("/\{for (.*?)\}/is", array(
            $this,
            "get_stripvtag_for"
        ), $template);
        
        $template = preg_replace_callback("/\{elseif\s+(.+?)\}/is", array(
            $this,
            "get_stripvtag_elseif"
        ), $template);
        
        for ($i = 0; $i < 2; $i ++) {
            $template = preg_replace_callback("/\{loop\s+$this->vtag_regexp\s+$this->vtag_regexp\s+$this->vtag_regexp\}(.+?)\{\/loop\}/is", array(
                $this,
                "get_loopsection_4"
            ), $template);
            $template = preg_replace_callback("/\{loop\s+$this->vtag_regexp\s+$this->vtag_regexp\}(.+?)\{\/loop\}/is", array(
                $this,
                "get_loopsection_3"
            ), $template);
        }
        $template = preg_replace_callback("/\{if\s+(.+?)\}/is", array(
            $this,
            "get_stripvtag_if"
        ), $template);
        
        $template = preg_replace("/\{template\s+(\w+?)\}/is", "<? include \$this->gettpl('\\1');?>", $template);
        $template = preg_replace_callback("/\{template\s+(.+?)\}/is", array(
            $this,
            "get_stripvtag_include_tpl"
        ), $template);
        
        $template = preg_replace("/\{else\}/is", "<? } else { ?>", $template);
        $template = preg_replace("/\{\/if\}/is", "<? } ?>", $template);
        $template = preg_replace("/\{\/for\}/is", "<? } ?>", $template);
        
        $template = preg_replace("/$this->const_regexp/", "<?=\\1?>", $template);
        
        $template = "<? if(!defined('UC_ROOT')) exit('Access Denied');?>\r\n$template";
        $template = preg_replace("/(\\\$[a-zA-Z_]\w+\[)([a-zA-Z_]\w+)\]/i", "\\1'\\2']", $template);
        
        $fp = fopen($this->objfile, 'w');
        fwrite($fp, $template);
        fclose($fp);
    }

    function arrayindex($name, $items)
    {
        $items = preg_replace("/\[([a-zA-Z_]\w*)\]/is", "['\\1']", $items);
        return "<?=$name$items?>";
    }

    function stripvtag($s)
    {
        return preg_replace("/$this->vtag_regexp/is", "\\1", str_replace("\\\"", '"', $s));
    }

    function loopsection($arr, $k, $v, $statement)
    {
        $arr = $this->stripvtag($arr);
        $k = $this->stripvtag($k);
        $v = $this->stripvtag($v);
        $statement = str_replace("\\\"", '"', $statement);
        return $k ? "<? foreach((array)$arr as $k => $v) {?>$statement<?}?>" : "<? foreach((array)$arr as $v) {?>$statement<? } ?>";
    }

    function lang($k)
    {
        return ! empty($this->languages[$k]) ? $this->languages[$k] : "{ $k }";
    }

    function _transsid($url, $tag = '', $wml = 0)
    {
        $sid = $this->sid;
        $tag = stripslashes($tag);
        if (! $tag || (! preg_match("/^(http:\/\/|mailto:|#|javascript)/i", $url) && ! strpos($url, 'sid='))) {
            if ($pos = strpos($url, '#')) {
                $urlret = substr($url, $pos);
                $url = substr($url, 0, $pos);
            } else {
                $urlret = '';
            }
            $url .= (strpos($url, '?') ? ($wml ? '&amp;' : '&') : '?') . 'sid=' . $sid . $urlret;
        }
        return $tag . $url;
    }

    function get_transsid($match)
    {
        return $this->_transsid($match[3], '<a' . $match[1] . 'href=' . $match[2]);
    }

    function __destruct()
    {
        if ($_COOKIE['sid']) {
            return;
        }
        $sid = rawurlencode($this->sid);
        $searcharray = array(
            "/\<a(\s*[^\>]+\s*)href\=([\"|\']?)([^\"\'\s]+)/is",
            "/(\<form.+?\>)/is"
        );
        $replacearray = array(
            array(
                $this,
                'get_transsid'
            ),
            "\\1\n<input type=\"hidden\" name=\"sid\" value=\"" . rawurldecode(rawurldecode(rawurldecode($sid))) . "\" />"
        );
        $content = preg_replace_callback("/\<a(\s*[^\>]+\s*)href\=([\"|\']?)([^\"\'\s]+)/is", array(
            $this,
            'get_transsid'
        ), ob_get_contents());
        $content = preg_replace("/(\<form.+?\>)/is", "\\1\n<input type=\"hidden\" name=\"sid\" value=\"" . rawurldecode(rawurldecode(rawurldecode($sid))) . "\" />", $content);
        ob_end_clean();
        echo $content;
    }
}

/*
 *
 * Usage:
 * require_once 'lib/template.class.php';
 * $this->view = new template();
 * $this->view->assign('page', $page);
 * $this->view->assign('userlist', $userlist);
 * $this->view->display("user_ls");
 *
 */

?>