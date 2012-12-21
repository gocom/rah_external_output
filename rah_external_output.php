<?php

/**
 * Rah_external_output plugin for Textpattern CMS
 *
 * @author Jukka Svahn
 * @date 2009-
 * @license GNU GPLv2
 * @link http://rahforum.biz/plugins/rah_external_output
 *
 * Copyright (C) 2012 Jukka Svahn <http://rahforum.biz>
 * Licensed under GNU Genral Public License version 2
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

	new rah_external_output();

/**
 * The plugin class.
 */

class rah_external_output {

	/**
	 * Version number.
	 *
	 * @var string
	 */

	static public $version = '1.0.1';

	/**
	 * The installer.
	 *
	 * @param string $event Admin-side event.
	 * @param string $step  Admin-side, plugin-lifecycle step.
	 */

	static public function install($event='', $step='') {
		
		global $prefs;
		
		if($step == 'deleted') {
			
			safe_delete(
				'txp_prefs',
				"name LIKE 'rah\_external\_output\_%'"
			);
			
			return;
		}
		
		if((string) get_pref(__CLASS__.'_version') === self::$version) {
			return;
		}
		
		@$rs = safe_rows(
			'name, content_type, code, allow',
			'rah_external_output',
			'1=1'
		);
		
		if($rs) {

			foreach($rs as $a) {
				extract($a);
				
				$name = ($allow != 'Yes' ? '_' : '') . 'rah_eo_'.$name;
				
				if(safe_count('txp_form', "name='".doSlash($name)."'")) {
					continue;
				}
					
				$code = ($content_type ? '; Content-type: '.$content_type.n : '') . $code;
					
				@safe_insert(
					'txp_form',
					"name='".doSlash($name)."', type='misc', Form='".doSlash($code)."'"
				);
			}
			
			@safe_query('DROP TABLE IF EXISTS '.safe_pfx('rah_external_output'));
		}
		
		set_pref(__CLASS__.'_version', self::$version, 'rah_exo', PREF_HIDDEN);
	}

	/**
	 * Constructor.
	 */

	public function __construct() {
		register_callback(array(__CLASS__, 'install'), 'plugin_lifecycle.'.__CLASS__);
		register_callback(array($this, 'view'), 'form');
		register_callback(array($this, 'get_snippet'), 'textpattern');
	}

	/**
	 * Outputs external snippets.
	 */

	public function get_snippet() {
		
		global $microstart, $qcount, $qtime, $production_status, $txptrace, $rah_external_output_mime;
		
		$name = gps(__CLASS__);
		
		if($name === '' || !is_string($name)) {
			return;
		}
		
		$r = safe_field(
			'Form', 
			'txp_form', 
			"name='".doSlash('rah_eo_'.$name)."'"
		);
		
		if($r === false) {
			txp_die(gTxt('404_not_found'), 404);
		}
		
		$mime = array(
			'json' => 'application/json',
			'js' => 'text/javascript',
			'xml' => 'text/xml',
			'css' => 'text/css',
			'txt' => 'text/plain',
			'html' => 'text/html',
		) + (array) $rah_external_output_mime;

		ob_clean();
		txp_status_header('200 OK');
		$ext = pathinfo($name, PATHINFO_EXTENSION);

		if($ext && isset($mime[$ext])) {
			header('Content-type: '.$mime[$ext].'; charset=utf-8');
		}
		
		$lines = explode(n, $r);
		
		foreach($lines as $line) {
			
			if(strpos($line, ';') !== 0) {
				break;
			}
			
			header(trim(substr(array_shift($lines), 1)));
		}

		set_error_handler('tagErrorHandler');
		echo parse(parse(implode(n, $lines)));
		restore_error_handler();

		if($ext == 'html' && $production_status == 'debug') {
			echo 
				n.comment('Runtime: '.substr(getmicrotime() - $microstart, 0, 6)).
				n.comment('Query time: '.sprintf('%02.6f', $qtime)).
				n.comment('Queries: '.$qcount).
				maxMemUsage('end of textpattern()', 1).
				n.comment('txp tag trace: '.n.str_replace('--', '&shy;&shy;', implode(n, (array) $txptrace)));
		}
		
		callback_event(__CLASS__.'.snippet_end');
		exit;
	}

	/**
	 * Adds a view link to the form editor.
	 */

	public function view() {
		
		$view = escape_js(gTxt('view'));
		$hu = escape_js(hu);
	
		$js = <<<EOF
			$(document).ready(function(){
				var input = $('input[name="name"]');
			
				if(input.val().indexOf('rah_eo_') !== 0) {
					return;
				}
				
				var uri = '{$hu}?rah_external_output=' + input.val().substr(7);
				var link = $('<a class="navlink" href="#">{$view}</a>').attr('href', uri);
				input.after(link).after(' ');
			
				link.click(function(e) {
					e.preventDefault();
					window.open(uri);
				});
			});
EOF;

		echo script_js($js);
	}
}

?>