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
		add_privs('rah_external_output', '1,2');
		add_privs('plugin_prefs.rah_external_output', '1,2');
		register_tab('extensions', 'rah_external_output', gTxt('rah_external_output'));
		register_callback(array('rah_external_output', 'panes'), 'rah_external_output');
		register_callback(array('rah_external_output', 'head'), 'admin_side', 'head_end');
		register_callback(array('rah_external_output', 'prefs'), 'plugin_prefs.rah_external_output');
		register_callback(array('rah_external_output', 'install'), 'plugin_lifecycle.rah_external_output');
	}
	else {
		register_callback(array('rah_external_output', 'get_snippet'), 'textpattern');
	}

/**
 * Tag for returning snippets.
 * @param array $atts
 * @return string
 */

	function rah_external_output($atts) {
		
		static $cache = array();
		
		extract(lAtts(array(
			'name' => ''
		),$atts));
		
		if(isset($cache[$name])) {
			return parse($cache[$name]);
		}
		
		$r = fetch('code', 'rah_external_output', 'name', $name);
		
		if($r === false) {
			trigger_error(gTxt('invalid_attribute_value', array('{name}' => $name)));
			return;
		}
		
		$cache[$name] = $r;
		return parse($cache[$name]);
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
			
			@safe_query(
				'DROP TABLE IF EXISTS '.safe_pfx('rah_external_output')
			);
			
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
		
		if($current == 'base')
			@safe_query(
				'DROP TABLE IF EXISTS '.safe_pfx('rah_external_output_mime')
			);
		
		/*
			Stores snippets.
			
			* name: Snippets name. Primary key.
			* content_type: Content type of the snippet.
			* code: The code/markup.
			* posted: Date posted.
			* allow: Status. Tells if the snippet is disabled or active.
		*/	
		
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_external_output')." (
				`name` varchar(255) NOT NULL default '',
				`content_type` varchar(255) NOT NULL default '',
				`code` LONGTEXT NOT NULL,
				`posted` datetime NOT NULL default '0000-00-00 00:00:00',
				`allow` varchar(3) NOT NULL default 'Yes',
				PRIMARY KEY(`name`)
			) PACK_KEYS=1 AUTO_INCREMENT=1 CHARSET=utf8"
		);
		
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
		
		$r = 
			safe_row(
				'content_type, code',
				'rah_external_output',
				"name='".doSlash($name)."' AND allow='Yes' LIMIT 0, 1"
			);
		
		if(!$r) {
			return;
		}
		
		extract($r);
		ob_start();
		ob_end_clean();
		txp_status_header('200 OK');
		
		if($content_type) {
			header('Content-type: '.$content_type);
		}

		set_error_handler('tagErrorHandler');
		$pretext['secondpass'] = false;
		$html = parse($code);
		$pretext['secondpass'] = true;
		trace_add('[ ~~~ '.gTxt('secondpass').' ~~~ ]');
		$html = parse($html);
		
		restore_error_handler();
		echo $html;
		
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

	/**
	 * Delivers panes
	 */

	static function panes() {
		global $step;
		
		require_privs('rah_external_output');
		self::install();
		
		$steps = 
			array(
				'browse' => false,
				'edit' => false,
				'save' => true,
				'activate' => true,
				'disable' => true,
				'delete' => true
			);
		
		$panes = new rah_external_output();
		
		if(!$step || !bouncer($step, $steps))
			$step = 'browse';
		
		$panes->$step();
	}

	/**
	 * The main pane. Lists snippets
	 * @param string $message The message shown by Textpattern.
	 */

	public function browse($message='') {
		
		global $event;
		
		$rs = 
			safe_rows(
				'name, posted, content_type, allow',
				'rah_external_output',
				'1=1 order by name asc'
			);
		
		$out[] =
			
			'	<table id="list" class="list" cellspacing="0" cellpadding="0">'.n.
			'		<thead>'.n.
			'			<tr>'.n.
			'				<th>'.gTxt('rah_external_output_name').'</th>'.n.
			'				<th>'.gTxt('rah_external_output_content_type').'</th>'.n.
			'				<th>'.gTxt('rah_external_output_posted').'</th>'.n.
			'				<th>'.gTxt('rah_external_output_status').'</th>'.n.
			'				<th>'.gTxt('rah_external_output_view').'</th>'.n.
			'				<th>&#160;</th>'.n.
			'			</tr>'.n.
			'		</thead>'.n.
			'		<tbody>'.n;
			
		if($rs) {
			
			foreach($rs as $a){
				extract($a);
				$out[] = 
					'			<tr>'.n.
					'				<td><a href="?event='.$event.'&amp;step=edit&amp;name='.htmlspecialchars($name).'">'.htmlspecialchars($name).'</a></td>'.n.
					'				<td>'.($content_type ? htmlspecialchars($content_type) : '&#160;').'</td>'.n.
					'				<td>'.safe_strftime('%b %d %Y %H:%M:%S',strtotime($posted)).'</td>'.n.
					'				<td>'.($allow == 'Yes' ? gTxt('rah_external_output_active') :  gTxt('rah_external_output_disabled')).'</td>'.n.
					'				<td>'.($allow == 'Yes' ? '<a href="'.hu.'?rah_external_output='.htmlspecialchars($name).'">'.gTxt('rah_external_output_view').'</a>' : '&#160;').'</td>'.n.
					'				<td><input type="checkbox" name="selected[]" value="'.htmlspecialchars($name).'" /></td>'.n.
					'			</tr>'.n;
			}
		
		} else 
			$out[] = 
				'			<tr>'.n.
				'				<td colspan="6">'.gTxt('rah_external_output_no_items').' <a href="?event='.$event.'&amp;step=edit">'.gTxt('rah_external_output_start').'</a></td>'.n.
				'			</tr>'.n;
		
		$out[] = 
			'		</tbody>'.n.
			'	</table>'.n.
			
			'	<p id="rah_external_output_step" class="rah_ui_step">'.n.
			'		<select name="step">'.n.
			'			<option value="">'.gTxt('rah_external_output_with_selected').'</option>'.n.
			'			<option value="activate">'.gTxt('rah_external_output_activate').'</option>'.n.
			'			<option value="disable">'.gTxt('rah_external_output_disable').'</option>'.n.
			'			<option value="delete">'.gTxt('rah_external_output_delete').'</option>'.n.
			'		</select>'.n.
			'		<input type="submit" class="smallerbox" value="'.gTxt('go').'" />'.n.
			'	</p>'.n;
			
		$this->pane($out, 'rah_external_output', $message);
	}

	/**
	 * Deletes an array of snippets
	 */

	public function delete() {
		
		$selected = ps('selected');
		
		if(!is_array($selected) || !$selected) {
			$this->browse(array(gTxt('rah_external_output_select_something'), E_WARNING));
			return;
		}
		
		safe_delete(
			'rah_external_output',
			'name in('.implode(',', quote_list($selected)).')'
		);
		
		$this->browse(gTxt('rah_external_output_snippets_removed'));
	}

	/**
	 * Activates selected array of snippets.
	 * @param string $state The new status.
	 */

	public function activate($state='Yes') {
		$selected = ps('selected');
		
		if(!is_array($selected) || !$selected) {
			$this->browse(array(gTxt('rah_external_output_select_something'), E_WARNING));
			return;
		}
		
		safe_update(
			'rah_external_output',
			"allow='".doSlash($state)."'",
			'name in('.implode(',', quote_list($selected)).')'
		);
		
		$msg = $state == 'Yes' ? 'activated' : 'disabled';
		
		$this->browse(gTxt('rah_external_output_snippets_'.$msg));
	}

	/**
	 * Disables an array of snippets.
	 */

	public function disable() {
		$this->activate('No');
	}

	/**
	 * Pane for editing snippets.
	 * @param string $message The message shown by Textpattern.
	 * @param string $newname The snippet's new name, if changed.
	 */

	public function edit($message='', $newname='') {
		
		extract(
			psa(
				array(
					'name',
					'content_type',
					'code',
					'allow',
					'editing'
				)
			)
		);
		
		if(gps('name') && !$name) {
			
			$rs = 
				safe_row(
					'name, content_type, code, allow',
					'rah_external_output',
					"name='".doSlash(gps('name'))."'"
				);
			
			if(!$rs) {
				$this->browse(array(gTxt('rah_external_output_unknown_snippet'), E_WARNING));
				return;
			}
			
			extract($rs);
			
			$editing = $name;
		}
		
		if($newname)
			$editing = $newname;
		
		$out[] =  
			
			'	<input type="hidden" name="step" value="save" />'.n.
			
			($editing ? '	<input type="hidden" name="editing" value="'.htmlspecialchars($editing).'" />'.n : '').
			
			'		<p>'.n.
			'			<label>'.n.
			'				'.gTxt('rah_external_output_name').'<br />'.n.
			'				<input type="text" name="name" class="edit" value="'.htmlspecialchars($name).'" />'.n.
			'			</label>'.n.
			'		</p>'.n.
			
			'		<p>'.n.
			'			<label>'.n.
			'				'.gTxt('rah_external_output_code').'<br />'.n.
			'				<textarea name="code" class="code" rows="20" cols="100">'.htmlspecialchars($code).'</textarea>'.n.
			'			</label>'.n.
			'		</p>'.n.
			
			'		<p>'.n.
			'			<label>'.n.
			'				'.gTxt('rah_external_output_content_type').'<br />'.n.
			'				<input type="text" name="content_type" class="edit" value="'.htmlspecialchars($content_type).'" />'.n.
			'			</label>'.n.
			'		</p>'.n.
			
			'		<p>'.n.
			'			<label>'.n.
			'				'.gTxt('rah_external_output_status').'<br />'.n.
			'				<select name="allow">'.n.
			'					<option value="Yes"'.($allow == 'Yes' ? ' selected="selected"' : '').'>'.gTxt('rah_external_output_active').'</option>'.n.
			'					<option value="No"'.($allow == 'No' ? ' selected="selected"' : '').'>'.gTxt('rah_external_output_disabled').'</option>'.n.
			'				</select>'.n.
			'			</label>'.n.
			'		</p>'.n.
			
			'		<p class="rah_ui_save">'.n.
			'			<input type="submit" value="'.gTxt('rah_external_output_save').'" class="publish" />'.n.
			'		</p>'.n;
		
		$this->pane($out, 'rah_external_output', $message);
	}

	/**
	 * Saves snippet
	 */

	public function save() {
		
		extract(
			doSlash(
				psa(
					array(
						'name',
						'content_type',
						'code',
						'allow',
						'editing'
					)
				)
			)
		);
		
		if(!trim($name)) {
			$this->edit(array(gTxt('rah_external_output_required'), E_ERROR));
			return;
		}
		
		if($editing) {
			
			if(
				$name != $editing &&
				safe_count(
					'rah_external_output',
					"name='$name'"
				)
			) {
				$this->edit(array(gTxt('rah_external_output_name_taken'), E_ERROR));
				return;
			}
			
			if(
				safe_update(
					'rah_external_output',
					"name='$name',
					content_type='$content_type',
					code='$code',
					posted=now(),
					allow='$allow'",
					"name='$editing'"
				) === false
			) {
				$this->edit(array(gTxt('rah_external_output_error_saving'), E_ERROR));
				return;
			}
			
			$this->edit(gTxt('rah_external_output_updated'), ps('name'));
			return;
		}
		
		if(
			safe_count(
				'rah_external_output',
				"name='$name'"
			)
		) {
			$this->edit(array(gTxt('rah_external_output_name_taken'), E_ERROR));
			return;
		}
		
		if(
			safe_insert(
				'rah_external_output',
				"name='$name',
				content_type='$content_type',
				code='$code',
				posted=now(),
				allow='$allow'"
			) === false
		) {
			$this->edit(array(gTxt('rah_external_output_error_saving'), E_ERROR));
			return;	
		}
		
		$this->edit(gTxt('rah_external_output_created'));
	}

	/**
	 * Outputs the pane
	 * @param mixed $out Pane's HTML markup.
	 * @param string $pagetop Page's title.
	 * @param string $message The message shown by Textpattern.
	 */

	private function pane($out, $pagetop, $message) {
		
		global $event, $step;
		
		pagetop(gTxt($pagetop), $message);
		
		if(is_array($out)) {
			$out = implode('', $out);
		}
		
		echo 
			n.
			'<form method="post" action="index.php" id="rah_external_output_container" class="rah_ui_container">'.n.
			eInput($event).
			tInput().
			'	<p id="rah_external_output_nav" class="rah_ui_nav">'.
				($step == 'edit' || $step == 'save' ? 
					' <span class="rah_ui_sep">&#187;</span> <a href="?event='.$event.'">'.gTxt('rah_external_output_nav_main').'</a>' : ''
				).
				' <span class="rah_ui_sep">&#187;</span> <a href="?event='.$event.'&amp;step=edit">'.gTxt('rah_external_output_nav_create').'</a>'.
			'</p>'.n.
			$out.n.
			'</form>'.n;
	}

	/**
	 * Adds styles and JavaScript to <head>
	 */

	static public function head() {
		global $event;
		
		if($event != 'rah_external_output')
			return;
			
		$msg = gTxt('are_you_sure');

		echo 
			<<<EOF
			<script type="text/javascript">
				<!--
				$(document).ready(function(){
					if($('#rah_external_output_step').length < 1)
						return;
					
					$('#rah_external_output_step .smallerbox').hide();

					if($('#rah_external_output_container input[type=checkbox]:checked').val() == null)
						$('#rah_external_output_step').hide();

					/*
						Reset the value
					*/

					$('#rah_external_output_container select[name="step"]').val('');

					/*
						Every time something is checked, check if
						the dropdown should be shown
					*/

					$('#rah_external_output_container input[type=checkbox], #rah_external_output_container td').click(
						function(){
							$('#rah_external_output_container select[name="step"]').val('');
							if($('table#list input[type=checkbox]:checked').val() != null)	
								$('#rah_external_output_step').slideDown();
							else
								$('#rah_external_output_step').slideUp();
	
						}
					);

					/*
						If value is changed, send the form
					*/

					$('#rah_external_output_container select[name="step"]').change(
						function(){
							$('#rah_external_output_container').submit();
						}
					);

					/*
						Verify if the sent is allowed
					*/

					$('form#rah_external_output_container').submit(
						function() {
							if(!verify('{$msg}')) {
								$('#rah_external_output_container select[name="step"]').val('');
								return false;
							}
						}
					);
				});
				//-->
			</script>
			<style type="text/css">
				#rah_external_output_container {
					width: 950px;
					margin: 0 auto;
				}
				#rah_external_output_container table {
					width: 100%;
				}
				#rah_external_output_container .rah_ui_step {
					text-align: right;
				}
				#rah_external_output_container textarea,
				#rah_external_output_container input.edit {
					width: 100%;
				}
			</style>

EOF;
	}

	/**
	 * Redirect to the admin-side interface
	 */

	static public function prefs() {
		header('Location: ?event=rah_external_output');
		echo 
			'<p>'.n.
			'	<a href="?event=rah_external_output">'.gTxt('continue').'</a>'.n.
			'</p>';
	}
}

?>