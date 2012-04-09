<?php

/**
 * Rah_external_output plugin for Textpattern CMS
 *
 * @author Jukka Svahn
 * @date 2009-
 * @license GNU GPLv2
 * @link http://rahforum.biz/plugins/rah_external_output
 *
 * Requires Textpattern v4.4.1 or newer.
 *
 * Copyright (C) 2012 Jukka Svahn <http://rahforum.biz>
 * Licensed under GNU Genral Public License version 2
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

	if(@txpinterface == 'admin') {
		rah_external_output::install();
		register_callback(array('rah_external_output', 'install'), 'plugin_lifecycle.rah_external_output');
	}
	else {
		register_callback(array('rah_external_output', 'get_snippet'), 'textpattern');
	}

class rah_external_output {

	static public $version = '0.9';

	/**
	 * The unified installer and uninstaller
	 * @param string $event Admin-side event.
	 * @param string $step Admin-side, plugin-lifecycle step.
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
	
		$current = 
			isset($prefs['rah_external_output_version']) ? 
				$prefs['rah_external_output_version'] : 'base';
		
		if($current == self::$version)
			return;
		
		if($current == 'base') {
			@safe_query(
				'DROP TABLE IF EXISTS '.safe_pfx('rah_external_output_mime')
			);
		}
		
		@$rs = safe_rows(
			'name, content_type, code, allow',
			'rah_external_output',
			'1=1'
		);
		
		if($rs) {
	
			foreach($rs as $a) {
				
				$name = 'rah_eo_' . $a['name'];
				
				if($a['allow'] != 'Yes') {
					$name = 'bck.' . $name;
				}
				
				if(safe_count('txp_form', "name='".doSlash($name)."'")) {
					continue;
				}
				
				if(!$a['content_type']) {
					$a['content_type'] = 'text/html';
				}
				
				$code = '; Content-type: ' . $a['content_type'] .n. $a['code'];
				
				safe_insert(
					'txp_form',
					"name='".doSlash($name)."', type='misc', Form='".doSlash($code)."'"
				);
			}
			
			//@safe_query('DROP TABLE IF EXISTS '.safe_pfx('rah_external_output'));
		}
		
		set_pref('rah_external_output_version', self::$version, 'rah_exo', 2, '', 0);
		$prefs['rah_external_output_version'] = self::$version;
	}

	/**
	 * Outputs external snippets.
	 */

	static public function get_snippet() {
		
		global $pretext, $microstart, $prefs, $qcount, $qtime, $production_status, $txptrace, $siteurl;
		
		$name = gps('rah_external_output');
		
		if(!$name) {
			return;
		}
		
		$r = safe_field(
			'Form', 
			'txp_form', 
			"name='".doSlash('rah_eo_' . trim(preg_replace('/[<>&"\']/', '', $name)))."'"
		);
		
		if($r === false) {
			return;
		}

		ob_start();
		ob_end_clean();
		txp_status_header('200 OK');
		
		$r = explode(n, $r);
		
		foreach($r as $key => $line) {
			
			if(strpos($line, ';') !== 0) {
				break;
			}
			
			header(trim(substr($line, 1)));
			unset($r[$key]);
		}
		
		$r = implode(n, $r);
		
		set_error_handler('tagErrorHandler');
		$pretext['secondpass'] = false;
		$r = parse($r);
		$pretext['secondpass'] = true;
		trace_add('[ ~~~ '.gTxt('secondpass').' ~~~ ]');
		$r = parse($r);
		
		restore_error_handler();
		echo $r;
		
		if(gps('rah_external_output_trace') && in_array($production_status, array('debug', 'testing'))) {
			
			$microdiff = getmicrotime() - $microstart;
			
			echo 
				n.comment('Runtime:    '.substr($microdiff,0,6)).
				n.comment('Query time: '.sprintf('%02.6f', $qtime)).
				n.comment('Queries: '.$qcount).
				maxMemUsage('end of textpattern()', 1);
			
			if(!empty($txptrace) && is_array($txptrace)) {
				echo n.comment('txp tag trace: '.n.str_replace('--','&shy;&shy;', implode(n, $txptrace)).n);
			}
		}

		exit;
	}
}

?>