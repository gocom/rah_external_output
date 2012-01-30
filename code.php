<?php	##################
	#
	#	rah_external_output-plugin for Textpattern
	#	version 0.5
	#	by Jukka Svahn
	#	http://rahforum.biz
	#
	###################

	if (@txpinterface == 'admin') {
		add_privs('rah_external_output_page','1,2');
		register_tab('extensions','rah_external_output_page','External output');
		register_callback('rah_external_output_page','rah_external_output_page');
	} else if(gps('rah_external_output')) rah_external_output_do();

	function rah_external_output_install() {
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_external_output')." (
				`name` varchar(255) NOT NULL default '',
				`content_type` varchar(255) NOT NULL default '',
				`code` LONGTEXT NOT NULL,
				`posted` datetime NOT NULL default '0000-00-00 00:00:00',
				`allow` varchar(3) NOT NULL default 'Yes',
				PRIMARY KEY(`name`)
			)"
		);
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_external_output_mime')." (
				`content_type` varchar(255) NOT NULL default '',
				PRIMARY KEY(`content_type`)
			)"
		);
	}

	function rah_external_output($atts) {
		extract(lAtts(array(
			'name' => ''
		),$atts));
		$code = fetch('code','rah_external_output','name',$name);
		if($code) return parse($code);
	}

	function rah_external_output_do() {
		$name = gps('rah_external_output');
		$rs = 
			safe_row(
				'content_type,code',
				'rah_external_output',
				"name='".doSlash($name)."' and allow='Yes'"
			)
		;
		if($rs) {
			extract($rs);
			ob_start();
			ob_end_clean();
			global $pretext;
			$pretext = array(
				'id' => '',
				's' => '',
				'c' => '',
				'q' => '',
				'pg' => '',
				'p' => '',
				'month' => '',
				'author' => '',
				'request_uri' => '',
				'qs' => '',
				'subpath' => '',
				'req' => '',
				'status' => 200,
				'page' => '',
				'css' => '',
				'path_from_root' => '',
				'pfr' => '',
				'path_to_site' => '',
				'permlink_mode' => '',
				'sitename' => '',
				'secondpass' => ''
			);
			if($content_type) header('Content-type: '.$content_type);
			echo @parse($code);
			exit();
		}
	}

	function rah_external_output_page() {
		require_privs('rah_external_output_page');
		rah_external_output_install();
		global $step;
		if(in_array($step,array(
			'rah_external_output_page_form',
			'rah_external_output_page_save',
			'rah_external_output_page_activate',
			'rah_external_output_page_disable',
			'rah_external_output_page_delete',
			'rah_external_output_content_types',
			'rah_external_output_content_types_save',
			'rah_external_output_content_types_delete'
		))) $step();
		else rah_external_output_page_list();
	}

	function rah_external_output_page_delete() {
		$selected = ps('selected');
		if(!is_array($selected)) $selected = array();
		foreach($selected as $name) {
			safe_delete(
				'rah_external_output',
				"name='".doSlash($name)."'"
			);
		}
		rah_external_output_page_list('Selection deleted.');
	}

	function rah_external_output_page_activate($state='Yes') {
		$selected = ps('selected');
		if(!is_array($selected))
			$selected = array();
		foreach($selected as $name) {
			safe_update(
				'rah_external_output',
				"allow='".doSlash($state)."'",
				"name='".doSlash($name)."'"
			);
		}
		rah_external_output_page_list('State changed.');
	}
	
	function rah_external_output_page_disable() {
		rah_external_output_page_activate('No');
	}

	function rah_external_output_content_types_delete() {
		$selected = ps('selected');
		if(!is_array($selected)) $selected = array();
		foreach($selected as $name) {
			safe_delete(
				'rah_external_output_mime',
				"content_type='".doSlash($name)."'"
			);
		}
		rah_external_output_content_types('Selection deleted.');
	}

	function rah_external_output_content_types($message='') {
		global $event;
		pagetop('External output',$message);
		echo 
			n.
			
			'	<form method="post" action="index.php" style="width:940px;margin:0 auto 15px auto;padding:5px;">'.n.
			'		<input type="hidden" name="event" value="'.$event.'" />'.n.
			'		<input type="hidden" name="step" value="rah_external_output_content_types_save" />'.n.
			'		<label>A new content-type: <input type="text" name="content_type" class="edit" value="" /></label>'.n.
			'		<input type="submit" class="smallerbox" value="Save" />'.n.
			'	</form>'.n.
			'	<form method="post" action="index.php" style="width:950px;margin:0 auto;">'.n.
			'		<input type="hidden" name="event" value="'.$event.'" />'.n.
			'		<table id="list" class="list" style="width:100%;" cellspacing="0" cellpadding="0">'.n.
			'			<tr>'.n.
			'				<th>Content-type</th>'.n.
			'				<th>Used by</th>'.n.
			'				<th>&#160;</th>'.n.
			'			</tr>'.n
		;
		
		$rs =
			safe_rows_start(
				'content_type',
				'rah_external_output_mime',
				'1=1 order by content_type'
			)
		;
		if(numRows($rs) > 0){
			while ($a = nextRow($rs)){
				extract($a);
				$count = 
					safe_count(
						'rah_external_output',
						"content_type='".doSlash($content_type)."'"
					);
				echo 
					'			<tr>'.n.
					'				<td>'.$content_type.'</td>'.n.
					'				<td>'.$count.'</td>'.n.
					'				<td><input type="checkbox" name="selected[]" value="'.htmlspecialchars($content_type).'" /></td>'.n.
					'			</tr>'.n;
			}
		} else 
			echo 
					'			<tr>'.n.
					'				<td colspan="3">No content-types defined yet.</td>'.n.
					'			</tr>'.n;
		echo 
			'		</table>'.n.
			'		<p style="text-align: right">'.n.
			'			<select name="step">'.n.
			'				<option value="">With selected...</option>'.n.
			'				<option value="rah_external_output_content_types_delete">Delete</option>'.n.
			'			</select>'.n.
			'			<input type="submit" class="smallerbox" value="Go" />'.n.
			'		</p>'.n.
			'	</form>'.n;
	}

	function rah_external_output_content_types_save() {
		if(ps('content_type'))
			safe_insert(
				'rah_external_output_mime',
				"content_type='".doSlash(ps('content_type'))."'"
			);
		rah_external_output_content_types();
	}

	function rah_external_output_page_list($message='') {
		global $event;
		pagetop('External output',$message);
		$rs = safe_rows_start('name,posted,content_type,allow','rah_external_output', '1=1 order by name');
		echo 
			n.'	<form method="post" action="index.php" style="width:950px;margin:0 auto;">'.n.
			'		<input type="hidden" name="event" value="'.$event.'" />'.n.
			'		<h1><strong>rah_external_output</strong> | External output</h1>'.n.
			'		<p>'.
						' &#187; <a href="?event='.$event.'&amp;step=rah_external_output_page_form">Create a new output</a>'.
						' &#187; <a href="?event='.$event.'&amp;step=rah_external_output_content_types">Arrange content types</a>'.
			'</p>'.n.
			'		<table id="list" class="list" style="width:100%;" cellspacing="0" cellpadding="0">'.n.
			'			<tr>'.n.
			'				<th>Name</th>'.n.
			'				<th>Content-type</th>'.n.
			'				<th>Updated</th>'.n.
			'				<th>Active</th>'.n.
			'				<th>View</th>'.n.
			'				<th>&#160;</th>'.n.
			'			</tr>'.n;
		if(numRows($rs) > 0){
			while ($a = nextRow($rs)){
				extract($a);
				echo 
					'			<tr>'.n.
					'				<td><a href="?event='.$event.'&amp;step=rah_external_output_page_form&amp;name='.htmlspecialchars($name).'">'.htmlspecialchars($name).'</a></td>'.n.
					'				<td>'.htmlspecialchars($content_type).'</td>'.n.
					'				<td>'.safe_strftime('%b %d %Y %H:%M:%S',strtotime($posted)).'</td>'.n.
					'				<td>'.$allow.'</td>'.n.
					'				<td>'.(($allow == 'Yes') ? '<a href="'.hu.'?rah_external_output='.htmlspecialchars($name).'">View</a>' : '&#160;').'</td>'.n.
					'				<td><input type="checkbox" name="selected[]" value="'.htmlspecialchars($name).'" /></td>'.n.
					'			</tr>'.n;
			}
		} else 
			echo 
				'			<tr>'.n.
				'				<td colspan="6">No external outputs created.</td>'.n.
				'			</tr>'.n;
		echo 
			'		</table>'.n.
			'		<p style="text-align: right">'.n.
			'			<select name="step">'.n.
			'				<option value="">With selected...</option>'.n.
			'				<option value="rah_external_output_page_activate">Activate</option>'.n.
			'				<option value="rah_external_output_page_disable">Disable</option>'.n.
			'				<option value="rah_external_output_page_delete">Delete</option>'.n.
			'			</select>'.n.
			'			<input type="submit" class="smallerbox" value="Go" />'.n.
			'		</p>'.n.
			'	</form>'.n;
	}

	function rah_external_output_page_form($message='') {
		pagetop('External output',$message);
		$name = '';
		extract(gpsa(array('content_type','code','allow','newname')));
		if(gps('name')) {
			$rs = safe_row('name,content_type,code,allow','rah_external_output',"name='".doSlash(gps('name'))."'");
			if($rs) extract($rs);
		}
		echo 
			n.'	<form method="post" action="index.php" style="width:950px;margin:0 auto;">'.n.
			'		<input type="hidden" name="event" value="rah_external_output_page" />'.n.
			'		<input type="hidden" name="step" value="rah_external_output_page_save" />'.n.
			(($name) ? 
					'		<input type="hidden" name="newname" value="'.htmlspecialchars($name).'" />'.n
				:
					''
			).
			'		<p>'.n.
			'			<label for="rah_name">Name</label><br />'.n.
			'			<input style="width:70%;" type="text" name="name" class="edit" id="rah_name" value="'.htmlspecialchars($name).'" />'.n.
			'		</p>'.n.
			'		<p>'.n.
			'			<label for="rah_code">Code</label><br />'.n.
			'			<textarea name="code" class="code" id="rah_code" rows="20" cols="40" style="width:95%;">'.htmlspecialchars($code).'</textarea>'.n.
			'		</p>'.n.
			'		<p>'.n.
			'			<label for="rah_content_type">Content-Type:</label>'.n.
			rah_external_output_content_option($content_type).n.
			'			<label for="rah_status">Status:</label>'.n.
			'			<select name="allow" id="rah_status">'.n.
			'				<option value="Yes"'.(($allow == 'Yes') ? ' selected="selected"' : '').'>Active</option>'.n.
			'				<option value="No"'.(($allow == 'No') ? ' selected="selected"' : '').'>Disabled</option>'.n.
			'			</select>'.n.
			'		</p>'.n.
			'		<input type="submit" value="Save" class="publish" />'.n.
			'	</form>'.n;
	}

	function rah_external_output_content_option($active='') {
		$rs =
			safe_rows_start(
				'content_type',
				'rah_external_output_mime',
				'1=1 order by content_type'
			)
		;
		$out = array();
		$count = numRows($rs);
		$missing = ($active) ? 1 : 0;
		if($count > 0){
			while ($a = nextRow($rs)){
				extract($a);
				$out[] = 
					'				<option value="'.htmlspecialchars($content_type).'"'.(($active == $content_type) ? ' selected="selected"' : '').'>'.htmlspecialchars($content_type).'</option>'.n;
				if($active == $content_type) $missing = 0;
			}
			if($missing == 1) 
				$out[] = 
					'				<option value="'.htmlspecialchars($active).'" selected="selected">'.htmlspecialchars($active).'</option>'.n;
		}
		if($count == 0) return '			<input style="width:20%;" type="text" name="content_type" class="edit" id="rah_content_type" value="'.htmlspecialchars($active).'" />'.n;
		else return 
			'			<select name="content_type" id="rah_content_type">'.n.implode('',$out).'			</select>';
	}

	function rah_external_output_page_save() {
		extract(doSlash(gpsa(array('name','content_type','code','allow','newname'))));
		$message = '';
		if($name) {
			if($newname) {
				if($name != $newname) {
					if(safe_count('rah_external_output',"name='$newname'") == 1 && safe_count('rah_external_output',"name='$name'") == 0) {
						safe_update(
							'rah_external_output',
							"name='$name',
							content_type='$content_type',
							code='$code',
							posted=now(),
							allow='$allow'",
							"name='$newname'"
						);
						$message = 'Code updated and name changed.';
					}
				} else {
					if(safe_count('rah_external_output',"name='$name'") == 1) {
						safe_update(
							'rah_external_output',
							"content_type='$content_type',
							code='$code',
							posted=now(),
							allow='$allow'",
							"name='$newname'"
						);
						$message = 'Code updated.';
					}
				}
			} else {
				if(safe_count('rah_external_output',"name='$name'") == 0) {
					safe_insert(
						'rah_external_output',
						"name='$name',
						content_type='$content_type',
						code='$code',
						posted=now(),
						allow='$allow'"
					);
					$message = 'Code created.';
				}
			}
		} else $message = 'Name undefined!';
		rah_external_output_page_form($message);
	}?>