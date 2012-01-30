<?php	##################
	#
	#	Rah_external_output-plugin for Textpattern
	#	version 0.7
	#	by Jukka Svahn
	#	http://rahforum.biz
	#
	#	Copyright (C) 2011 Jukka Svahn <http://rahforum.biz>
	#	Licensed under GNU Genral Public License version 2
	#	http://www.gnu.org/licenses/gpl-2.0.html
	#
	##################

	if(@txpinterface == 'admin') {
		add_privs('rah_external_output','1,2');
		add_privs('plugin_prefs.rah_external_output','1,2');
		register_tab('extensions','rah_external_output',gTxt('rah_external_output') == 'rah_external_output' ? 'External output' : gTxt('rah_external_output'));
		register_callback('rah_external_output_page','rah_external_output');
		register_callback('rah_external_output_head','admin_side','head_end');
		register_callback('rah_external_output_prefs','plugin_prefs.rah_external_output');
		register_callback('rah_external_output_install','plugin_lifecycle.rah_external_output');
	}
	else
		register_callback('rah_external_output_do','textpattern');

/**
	The unified installer and uninstaller
	@param $event string Admin-side event.
	@param $step string Admin-side, plugin-lifecycle step.
*/

	function rah_external_output_install($event='',$step='') {
		
		/*
			Uninstall if uninstalling the
			plugin
		*/
		
		if($step == 'deleted') {
			
			@safe_query(
				'DROP TABLE IF EXISTS '.safe_pfx('rah_external_output')
			);
			
			safe_delete(
				'txp_prefs',
				"name='rah_external_output_version'"
			);
			
			return;
		}
		
		global $prefs, $textarray;
		
		/*
			Make sure language strings are set
		*/
		
		foreach(
			array(
				'rah_external_output' => 'External output',
				'rah_external_output_name_taken' => 'The name is already taken. Please choose other name.',
				'rah_external_output_start' => 'Start by creating your first snippet.',
				'rah_external_output_no_items' => 'No snippets created yet.',
				'rah_external_output_content_type' => 'Content-Type',
				'rah_external_output_select_something' => 'Select something before continuing.',
				'rah_external_output_snippets_removed' => 'Selected snippets removed.',
				'rah_external_output_snippets_activated' => 'Selected snippets activated.',
				'rah_external_output_snippets_disabled' => 'Selected snippets disabled.',
				'rah_external_output_unknown_snippet' => 'Snippet does not exist.',
				'rah_external_output_required' => 'Name is required.',
				'rah_external_output_name_taken' => 'Snippets name is already taken.',
				'rah_external_output_updated' => 'Changes saved.',
				'rah_external_output_created' => 'Snippet created.',
				'rah_external_output_nav_main' => 'Main',
				'rah_external_output_nav_create' => 'Create a new snippet',
				'rah_external_output_disabled' => 'Disabled',
				'rah_external_output_code' => 'Snippet/code',
				'rah_external_output_with_selected' => 'With selected...',
				'rah_external_output_activate' => 'Activate',
				'rah_external_output_disable' => 'Disable',
				'rah_external_output_error_saving' => 'Database error occured while saving.'
			) as $string => $translation
		)
			if(!isset($textarray[$string]))
				$textarray[$string] = $translation;
	
		$version = 
			isset($prefs['rah_external_output_version']) ? 
				$prefs['rah_external_output_version'] : 'base' ;
		
		if($version == '0.7')
			return;
		
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
		
		/*
			Set version
		*/
		
		set_pref('rah_external_output_version','0.7','rah_exo',2,'',0);
	}

/**
	Tag for returning external output
*/

	function rah_external_output($atts) {
		
		extract(lAtts(array(
			'name' => ''
		),$atts));
		
		global $rah_external_output;
		
		if(isset($rah_external_output[$name]))
			return parse($rah_external_output[$name]);
		
		$code = fetch('code','rah_external_output','name',$name);
		$rah_external_output[$name] = $code;
		return parse($code);
	}

/**
	Outputs external outputs.
*/

	function rah_external_output_do() {
		
		$name = gps('rah_external_output');
		
		if(!$name)
			return;
		
		$rs = 
			safe_row(
				'content_type,code',
				'rah_external_output',
				"name='".doSlash($name)."' and allow='Yes' limit 0, 1"
			);
		
		if(!$rs)
			return;
		
		extract($rs);
		ob_start();
		ob_end_clean();
		
		if($content_type)
			header('Content-type: '.$content_type);
		
		echo @parse($code);
		exit();
	}

/**
	Delivers panes.
*/

	function rah_external_output_page() {
		require_privs('rah_external_output');
		rah_external_output_install();
		
		global $step;
		$func = 'rah_external_output_' . $step;
		
		if(in_array($step,array(
			'edit',
			'save',
			'activate',
			'disable',
			'delete'
		)))
			$func();
		else
			rah_external_output_list();
	}

/**
	The main pane. Lists snippets
	@param $message string The message shown by Textpattern.
*/

	function rah_external_output_list($message='') {
		
		global $event;
		
		$rs = 
			safe_rows(
				'name,posted,content_type,allow',
				'rah_external_output',
				'1=1 order by name asc'
			);
		
		$out[] =
			
			'	<table id="list" class="list" cellspacing="0" cellpadding="0">'.n.
			'		<thead>'.n.
			'			<tr>'.n.
			'				<th>'.gTxt('name').'</th>'.n.
			'				<th>'.gTxt('rah_external_output_content_type').'</th>'.n.
			'				<th>'.gTxt('updated').'</th>'.n.
			'				<th>'.gTxt('status').'</th>'.n.
			'				<th>'.gTxt('view').'</th>'.n.
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
					'				<td>'.(trim($content_type) ? htmlspecialchars($content_type) : '&#160;').'</td>'.n.
					'				<td>'.safe_strftime('%b %d %Y %H:%M:%S',strtotime($posted)).'</td>'.n.
					'				<td>'.($allow == 'Yes' ? gTxt('active') :  gTxt('rah_external_output_disabled')).'</td>'.n.
					'				<td>'.($allow == 'Yes' ? '<a href="'.hu.'?rah_external_output='.htmlspecialchars($name).'">'.gTxt('view').'</a>' : '&#160;').'</td>'.n.
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
			'			<option value="delete">'.gTxt('delete').'</option>'.n.
			'		</select>'.n.
			'		<input type="submit" class="smallerbox" value="'.gTxt('go').'" />'.n.
			'	</p>'.n;
			
		rah_external_ouput_header($out,'rah_external_output',$message);
	}

/**
	Deletes array of snippets
*/

	function rah_external_output_delete() {
		
		$selected = ps('selected');
		
		if(!is_array($selected) || !$selected) {
			rah_external_output_list('rah_external_output_select_something');
			return;
		}
		
		foreach($selected as $name)
			$ids[] = "'".doSlash($name)."'";
		
		safe_delete(
			'rah_external_output',
			'name in('.implode(',',$ids).')'
		);
		
		rah_external_output_list('rah_external_output_snippets_removed');
	}

/**
	Activates selected array of snippets.
	@param $state string The new status.
*/

	function rah_external_output_activate($state='Yes') {
		$selected = ps('selected');
		
		if(!is_array($selected) || !$selected) {
			rah_external_output_list('rah_external_output_select_something');
			return;
		}
		
		foreach($selected as $name)
			$ids[] = "'".doSlash($name)."'";
		
		safe_update(
			'rah_external_output',
			"allow='".doSlash($state)."'",
			'name in('.implode(',',$ids).')'
		);
		
		$msg = $state == 'Yes' ? 'activated' : 'disabled';
		
		rah_external_output_list('rah_external_output_snippets_'.$msg);
	}

/**
	Disables array of snippets.
*/

	function rah_external_output_disable() {
		rah_external_output_activate('No');
	}

/**
	Pane for editing snippets.
	@param $message string The message shown by Textpattern.
	@param $newname string The snippet's new name, if changed.
*/

	function rah_external_output_edit($message='',$newname='') {
		
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
					'name,content_type,code,allow',
					'rah_external_output',
					"name='".doSlash(gps('name'))."'"
				);
			
			if(!$rs) {
				rah_external_output_list('rah_external_output_unknown_snippet');
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
			'				'.gTxt('name').'<br />'.n.
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
			'				'.gTxt('status').'<br />'.n.
			'				<select name="allow">'.n.
			'					<option value="Yes"'.($allow == 'Yes' ? ' selected="selected"' : '').'>'.gTxt('active').'</option>'.n.
			'					<option value="No"'.($allow == 'No' ? ' selected="selected"' : '').'>'.gTxt('rah_external_output_disabled').'</option>'.n.
			'				</select>'.n.
			'			</label>'.n.
			'		</p>'.n.
			
			'		<p class="rah_ui_save">'.n.
			'			<input type="submit" value="'.gTxt('save').'" class="publish" />'.n.
			'		</p>'.n;
		
		rah_external_ouput_header($out,'rah_external_output',$message);
	}

/**
	Saves snippet
*/

	function rah_external_output_save() {
		
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
			rah_external_output_edit('rah_external_output_required');
			return;
		}
		
		if($editing) {
			
			if(
				$name != $editing &&
				safe_count(
					'rah_external_output',
					"name='$name'"
				) > 0
			) {
				rah_external_output_edit('rah_external_output_name_taken');
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
				rah_external_output_edit('rah_external_output_error_saving');
				return;
			}
			
			rah_external_output_edit('rah_external_output_updated',ps('name'));
			return;
			
		}
		
		if(
			safe_count(
				'rah_external_output',
				"name='$name'"
			) > 0
		) {
			rah_external_output_edit('rah_external_output_name_taken');
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
			rah_external_output_edit('rah_external_output_error_saving');
			return;	
		}
		
		rah_external_output_edit('rah_external_output_created');
	}

/**
	Outputs the panes
	@param $out mixed Pane's HTML markup.
	@param $pagetop Page's title.
	@param $message The message shown by Textpattern.
*/

	function rah_external_ouput_header($out,$pagetop,$message) {
		
		global $event, $step;
		
		if($message)
			$message = gTxt($message);
		
		pagetop(gTxt($pagetop),$message);
		
		if(is_array($out))
			$out = implode('',$out);
		
		echo 
			n.
			'<form method="post" action="index.php" id="rah_external_output_container" class="rah_ui_container">'.n.
			'	<input type="hidden" name="event" value="'.$event.'" />'.n.
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
	Adds styles and JavaScript to <head>
*/

	function rah_external_output_head() {
		global $event;
		
		if($event != 'rah_external_output')
			return;
			
		$msg = gTxt('are_you_sure');

		echo 
			<<<EOF
			<script type="text/javascript">
				<!--
				function rah_external_output_stepper() {
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
				}
			
				$(document).ready(function(){
					rah_external_output_stepper();
				});
				-->
			</script>
			<style type="text/css">
				#rah_external_output_container {
					width: 950px;
					margin: 0 auto;
				}
				#rah_external_output_container table {
					width: 100%;
				}
				#rah_external_output_container #rah_external_output_step {
					text-align: right;
				}
				#rah_external_output_container textarea,
				#rah_external_output_container input.edit {
					width: 948px;
					padding: 0;
					margin: 0;
				}
			</style>

EOF;
	}

/**
	Redirect to the admin-side interface
*/

	function rah_external_output_prefs() {
		header('Location: ?event=rah_external_output');
		echo 
			'<p>'.n.
			'	<a href="?event=rah_external_output">'.gTxt('continue').'</a>'.n.
			'</p>';
	}
?>