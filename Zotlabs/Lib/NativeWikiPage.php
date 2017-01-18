<?php

namespace Zotlabs\Lib;

use \Zotlabs\Lib as Zlib;

class NativeWikiPage {

	static public function page_list($channel_id,$observer_hash, $resource_id) {

		// TODO: Create item table records for pages so that metadata like title can be applied
		$w = Zlib\NativeWiki::get_wiki($channel_id,$observer_hash,$resource_id);

		$pages[] = [
			'resource_id' => '',
			'title'       => 'Home',
			'url'         => 'Home',
			'link_id'     => 'id_wiki_home_0'
		];

		$sql_extra = item_permissions_sql($channel_id,$observer_hash);

		$r = q("select * from item where resource_type = 'nwikipage' and resource_id = '%s' and uid = %d $sql_extra group by mid",
			dbesc($resource_id),
			intval($channel_id)
		);
		if($r) {
			$items = fetch_post_tags($r,true);
			foreach($items as $page_item) {
				$title = get_iconfig($page_item['id'],'nwikipage','pagetitle',t('(No Title)'));
				if(urldecode($title) !== 'Home') {
					$pages[] = [
						'resource_id' => $resource_id,
						'title'       => urldecode($title),
						'url'         => $title,
						'link_id'     => 'id_' . substr($resource_id, 0, 10) . '_' . $page_item['id']
					];
				}
			}
		}

		return array('pages' => $pages, 'wiki' => $w);
	}


	static public function create_page($channel_id, $observer_hash, $name, $resource_id) {

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);

		// create an empty activity

		$arr = [];
		$arr['uid'] = $channel_id;
		$arr['author_xchan'] = $observer_hash;
		$arr['resource_type'] = 'nwikipage';
		$arr['resource_id'] = $resource_id;

		set_iconfig($arr,'nwikipage','pagetitle',urlencode(($name) ? $name : t('(No Title)')),true);

		post_activity_item($arr, false, false);

		$page = [ 
			'rawName'  => $name,
			'htmlName' => escape_tags($name),
			'urlName'  => urlencode(escape_tags($name)), 
			'fileName' => urlencode(escape_tags($name)) . Zlib\NativeWikiPage::get_file_ext($w)
		];

		return array('page' => $page, 'wiki' => $w, 'message' => '', 'success' => true);
	}

	static public function rename_page($arr) {
		$pageUrlName   = ((array_key_exists('pageUrlName',$arr))   ? $arr['pageUrlName']   : '');
		$pageNewName   = ((array_key_exists('pageNewName',$arr))   ? $arr['pageNewName']   : '');
		$resource_id   = ((array_key_exists('resource_id',$arr))   ? $arr['resource_id']   : '');
		$observer_hash = ((array_key_exists('observer_hash',$arr)) ? $arr['observer_hash'] : '');
		$channel_id    = ((array_key_exists('channel_id',$arr))    ? $arr['channel_id']    : 0);

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);
		if(! $w['path']) {
			return array('message' => t('Wiki not found.'), 'success' => false);
		}

		$page_path_old = $w['path'] . '/' . $pageUrlName . \Zlib\NativeWikiPage::get_file_ext($w);

		if(! is_readable($page_path_old) === true) {
			return array('message' => 'Cannot read wiki page: ' . $page_path_old, 'success' => false);
		}

		$page = [ 
			'rawName' => $pageNewName, 
			'htmlName' => escape_tags($pageNewName), 
			'urlName' => urlencode(escape_tags($pageNewName)), 
			'fileName' => urlencode(escape_tags($pageNewName)) . \Zlib\NativeWikiPage::get_file_ext($w)
		];

		$page_path_new = $w['path'] . '/' . $page['fileName'] ;

		if(is_file($page_path_new)) {
			return array('message' => 'Page already exists.', 'success' => false);
		}

		// Rename the page file in the wiki repo
		if(! rename($page_path_old, $page_path_new)) {
			return array('message' => 'Error renaming page file.', 'success' => false);
		}
		else {
			return array('page' => $page, 'message' => '', 'success' => true);
		}
	
	}

	static public function get_page_content($arr) {
		$pageUrlName   = ((array_key_exists('pageUrlName',$arr))   ? $arr['pageUrlName']        : '');
		$resource_id   = ((array_key_exists('resource_id',$arr))   ? $arr['resource_id']        : '');
		$observer_hash = ((array_key_exists('observer_hash',$arr)) ? $arr['observer_hash']      : '');
		$channel_id    = ((array_key_exists('channel_id',$arr))    ? intval($arr['channel_id']) : 0);
		$revision      = ((array_key_exists('revision',$arr))      ? intval($arr['revision'])   : (-1));

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);
		if (! $w['wiki']) {
			return array('content' => null, 'message' => 'Error reading wiki', 'success' => false);
		}

		$item = self::load_page($arr);

		if($item) {
			$content = $item['body'];

			return [ 
				'content' => json_encode($content), 
				'mimeType' => $w['mimeType'], 
				'message' => '', 
				'success' => true
			];
		}
	
		return array('content' => null, 'message' => t('Error reading page content'), 'success' => false);

	}

	static public function page_history($arr) {
		$pageUrlName = ((array_key_exists('pageUrlName',$arr)) ? $arr['pageUrlName'] : '');
		$resource_id = ((array_key_exists('resource_id',$arr)) ? $arr['resource_id'] : '');
		$observer_hash = ((array_key_exists('observer_hash',$arr)) ? $arr['observer_hash'] : '');
		$channel_id    = ((array_key_exists('channel_id',$arr))    ? $arr['channel_id']    : 0);

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);
		if (!$w['wiki']) {
			return array('history' => null, 'message' => 'Error reading wiki', 'success' => false);
		}

		$items = self::load_page_history($arr);

		$history = [];

		if($items) {
			$processed = 0;
			foreach($items as $item) {
				if($processed > 1000)
					break;
				$processed ++;
				$history[] = [ 
					'revision' => $item['revision'],
					'date' => datetime_convert('UTC',date_default_timezone_get(),$item['created']),
					'name' => $item['author']['xchan_name'],
					'title' => get_iconfig($item,'nwikipage','commit_msg') 
				];

			}

			return [ 'success' => true, 'history' => $history ];
		}

		return [ 'success' => false ];

	}
	

	static public function load_page($arr) {
logger('arr: ' . print_r($arr,true));
		$pageUrlName   = ((array_key_exists('pageUrlName',$arr))   ? $arr['pageUrlName']     : '');
		$resource_id   = ((array_key_exists('resource_id',$arr))   ? $arr['resource_id']     : '');
		$observer_hash = ((array_key_exists('observer_hash',$arr)) ? $arr['observer_hash']   : '');
		$channel_id    = ((array_key_exists('channel_id',$arr))    ? $arr['channel_id']      : 0);
		$revision      = ((array_key_exists('revision',$arr))      ? $arr['revision']        : (-1));

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);
		if (! $w['wiki']) {
			return array('content' => null, 'message' => 'Error reading wiki', 'success' => false);
		}

		$ids = '';
dbg(0);
		$ic = q("select * from iconfig left join item on iconfig.iid = item.id where uid = %d and cat = 'nwikipage' and k = 'pagetitle' and v = '%s'",
			intval($channel_id),
			dbesc($pageUrlName)
		);
	
		if($ic) {
			foreach($ic as $c) {
				if($ids)
					$ids .= ',';
				$ids .= intval($c['iid']);
			}
		}

		$sql_extra = item_permissions_sql($channel_id,$observer_hash);
		if($revision == (-1))
	        $sql_extra .= " order by revision desc ";
    	elseif($revision)
        	$sql_extra .= " and revision = " . intval($revision) . " ";

		$r = null;
		if($ids) {
			$r = q("select * from item where resource_type = 'nwikipage' and resource_id = '%s' and uid = %d and id in ( $ids ) $sql_extra limit 1",
				dbesc($resource_id),
				intval($channel_id)
			);
dbg(0);
			if($r) {
				$items = fetch_post_tags($r,true);
				return $items[0];
			}
		}
dbg(0);
		return null;
	}

	static public function load_page_history($arr) {

		$pageUrlName   = ((array_key_exists('pageUrlName',$arr))   ? $arr['pageUrlName']     : '');
		$resource_id   = ((array_key_exists('resource_id',$arr))   ? $arr['resource_id']     : '');
		$observer_hash = ((array_key_exists('observer_hash',$arr)) ? $arr['observer_hash']   : '');
		$channel_id    = ((array_key_exists('channel_id',$arr))    ? $arr['channel_id']      : 0);
		$revision      = ((array_key_exists('revision',$arr))      ? $arr['revision']        : (-1));

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);
		if (! $w['wiki']) {
			return array('content' => null, 'message' => 'Error reading wiki', 'success' => false);
		}

		$ids = '';

		$ic = q("select * from iconfig left join item on iconfig.iid = item.id where uid = %d and cat = 'nwikipage' and k = 'pagetitle' and v = '%s'",
			intval($channel_id),
			dbesc($pageUrlName)
		);
	
		if($ic) {
			foreach($ic as $c) {
				if($ids)
					$ids .= ',';
				$ids .= intval($c['iid']);
			}
		}

		$sql_extra = item_permissions_sql($channel_id,$observer_hash);
		$sql_extra .= " order by revision desc ";

		$r = null;
		if($ids) {
			$r = q("select * from item where resource_type = 'nwikipage' and resource_id = '%s' and uid = %d and id in ( $ids ) $sql_extra",
				dbesc($resource_id),
				intval($channel_id)
			);
			if($r) {
				xchan_query($r);
				$items = fetch_post_tags($r,true);
				return $items;
			}
		}

		return null;
	}



	static public function prepare_content($s) {
			
		$text = preg_replace_callback('{
					(?:\n\n|\A\n?)
					(	            # $1 = the code block -- one or more lines, starting with a space/tab
					  (?>
						[ ]{'.'4'.'}  # Lines must start with a tab or a tab-width of spaces
						.*\n+
					  )+
					)
					((?=^[ ]{0,'.'4'.'}\S)|\Z)	# Lookahead for non-space at line-start, or end of doc
				}xm',
				'self::nwiki_prepare_content_callback', $s);
	
		return $text;
	}
	
	static public function nwiki_prepare_content_callback($matches) {
		$codeblock = $matches[1];
	
		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES, UTF8, false);
		return "\n\n" . $codeblock ;
	}
	
	
	
	static public function save_page($arr) {

		$pageUrlName = ((array_key_exists('pageUrlName',$arr)) ? $arr['pageUrlName'] : '');
		$content = ((array_key_exists('content',$arr)) ? purify_html(Zlib\NativeWikiPage::prepare_content($arr['content'])) : '');
		$resource_id = ((array_key_exists('resource_id',$arr)) ? $arr['resource_id'] : '');
		$observer_hash = ((array_key_exists('observer_hash',$arr)) ? $arr['observer_hash'] : '');
		$channel_id    = ((array_key_exists('channel_id',$arr))    ? $arr['channel_id']    : 0);
		$revision = ((array_key_exists('revision',$arr))    ? $arr['revision']    : 0);

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);

		if (!$w['wiki']) {
			return array('message' => t('Error reading wiki'), 'success' => false);
		}
	
		$item = self::load_page($arr);
		if(! $item) {
			return array('message' => t('Page not found'), 'success' => false);
		}
		unset($item['id']);
		unset($item['author']);

		$item['parent'] = 0;
		$item['body'] = $content;
		$item['author_xchan'] = $observer_hash;
		$item['revision'] = (($arr['revision']) ? intval($arr['revision']) + 1 : intval($item['revision']) + 1);

		if($item['iconfig'] && is_array($item['iconfig']) && count($item['iconfig'])) {
			for($x = 0; $x < count($item['iconfig']); $x ++) {
				unset($item['iconfig'][$x]['id']);
				unset($item['iconfig'][$x]['iid']);
			}
		}

		$ret = item_store($item, false, false);

		if($ret['item_id'])
			return array('message' => '', 'filename' => $filename, 'success' => true);
		else
			return array('message' => t('Page update failed.'), 'success' => false);
	}	

	static public function delete_page($arr) {
		$pageUrlName = ((array_key_exists('pageUrlName',$arr)) ? $arr['pageUrlName'] : '');
		$resource_id = ((array_key_exists('resource_id',$arr)) ? $arr['resource_id'] : '');
		$observer_hash = ((array_key_exists('observer_hash',$arr)) ? $arr['observer_hash'] : '');
		$channel_id    = ((array_key_exists('channel_id',$arr))    ? $arr['channel_id']    : 0);

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);

		if (!$w['path']) {
			return array('message' => t('Error reading wiki'), 'success' => false);
		}
		$page_path = $w['path'] . '/' . $pageUrlName . wiki_get_file_ext($w);
		if (is_writable($page_path) === true) {
			if(!unlink($page_path)) {
				return array('message' => t('Error deleting page file'), 'success' => false);
			}
			return array('message' => '', 'success' => true);
		} 
		else {
			return array('message' => t('Page file not writable'), 'success' => false);
		}	
	}
	
	static public function revert_page($arr) {
		$pageUrlName   = ((array_key_exists('pageUrlName',$arr))   ? $arr['pageUrlName']   : '');
		$resource_id   = ((array_key_exists('resource_id',$arr))   ? $arr['resource_id']   : '');
		$commitHash    = ((array_key_exists('commitHash',$arr))    ? $arr['commitHash']    : null);
		$observer_hash = ((array_key_exists('observer_hash',$arr)) ? $arr['observer_hash'] : '');
		$channel_id    = ((array_key_exists('channel_id',$arr))    ? $arr['channel_id']    : 0);

		if (! $commitHash) {
			return array('content' => $content, 'message' => 'No commit was provided', 'success' => false);
		}

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);
		if (!$w['wiki']) {
			return array('content' => $content, 'message' => 'Error reading wiki', 'success' => false);
		}

		$x = $arr;

		if(intval($commitHash) > 0) {
			unset($x['commitHash']);
			$x['revision'] = intval($commitHash) - 1;
			$loaded = self::load_page($x);

			if($loaded) {
				$content = $loaded['body'];
				return [ 'content' => $content, 'success' => true ];
			}
			return [ 'content' => $content, 'success' => false ]; 
		}
	}
	
	static public function compare_page($arr) {
		$pageUrlName = ((array_key_exists('pageUrlName',$arr)) ? $arr['pageUrlName'] : '');
		$resource_id = ((array_key_exists('resource_id',$arr)) ? $arr['resource_id'] : '');
		$currentCommit = ((array_key_exists('currentCommit',$arr)) ? $arr['currentCommit'] : 'HEAD');
		$compareCommit = ((array_key_exists('compareCommit',$arr)) ? $arr['compareCommit'] : null);
		$observer_hash = ((array_key_exists('observer_hash',$arr)) ? $arr['observer_hash'] : '');
		$channel_id    = ((array_key_exists('channel_id',$arr))    ? $arr['channel_id']    : 0);

		if (! $compareCommit) {
			return array('message' => t('No compare commit was provided'), 'success' => false);
		}

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);

		if (!$w['path']) {
			return array('message' => t('Error reading wiki'), 'success' => false);
		}
		$page_path = $w['path'] . '/' . $pageUrlName . \Zlib\NativeWikiPage::get_file_ext($w);
		if (is_readable($page_path) === true) {
			$reponame = ((array_key_exists('title', $w['wiki'])) ? urlencode($w['wiki']['title']) : 'repo');
			if($reponame === '') {
				$reponame = 'repo';
			}
			$git = new GitRepo('', null, false, $w['wiki']['title'], $w['path']);
			$compareContent = $currentContent = '';
			try {
				foreach ($git->git->tree($currentCommit) as $object) {
					if ($object['type'] == 'blob' && $object['file'] === $pageUrlName . wiki_get_file_ext($w)) {
							$currentContent = $git->git->cat->blob($object['hash']);						
					}
				}
				foreach ($git->git->tree($compareCommit) as $object) {
					if ($object['type'] == 'blob' && $object['file'] === $pageUrlName . wiki_get_file_ext($w)) {
							$compareContent = $git->git->cat->blob($object['hash']);						
					}
				}
				require_once('library/class.Diff.php');
				$diff = Diff::toTable(Diff::compare($currentContent, $compareContent));
			} 
			catch (\PHPGit\Exception\GitException $e) {
				return array('message' => t('GitRepo error thrown'), 'success' => false);
			}
			return array('diff' => $diff, 'message' => '', 'success' => true);
		} 
		else {
			return array('message' => t('Page file not writable'), 'success' => false);
		}
	}
	
	static public function commit($arr) {
logger('committing');
		$commit_msg    = ((array_key_exists('commit_msg', $arr))   ? $arr['commit_msg']    : t('Page updated'));
		$observer_hash = ((array_key_exists('observer_hash',$arr)) ? $arr['observer_hash'] : '');
		$channel_id    = ((array_key_exists('channel_id',$arr))    ? $arr['channel_id']    : 0);
		$pageUrlName   = ((array_key_exists('pageUrlName',$arr))   ? $arr['pageUrlName']   : t('Untitled'));

		if(array_key_exists('resource_id', $arr)) {
			$resource_id = $arr['resource_id'];
		}
		else {
			return array('message' => t('Wiki resource_id required for git commit'), 'success' => false);
		}

		$w = Zlib\NativeWiki::get_wiki($channel_id, $observer_hash, $resource_id);

		if (! $w['wiki']) {
			return array('message' => t('Error reading wiki'), 'success' => false);
		}

		$page = self::load_page($arr);
logger('commit: page: ' . print_r($page,true));
		if($page) {
			set_iconfig($page['id'],'nwikipage','commit_msg',escape_tags($commit_msg),true);
			return [ 'success' => true, 'page' => $page ];
		}

		return [ 'success' => false, 'message' => t('Page not found.') ];

	}
	
	static public function convert_links($s, $wikiURL) {
		
		if (strpos($s,'[[') !== false) {
			preg_match_all("/\[\[(.*?)\]\]/", $s, $match);
			$pages = $pageURLs = array();
			foreach ($match[1] as $m) {
				// TODO: Why do we need to double urlencode for this to work?
				$pageURLs[] = urlencode(urlencode(escape_tags($m)));
				$pages[] = $m;
			}
			$idx = 0;
			while(strpos($s,'[[') !== false) {
			$replace = '<a href="'.$wikiURL.'/'.$pageURLs[$idx].'">'.$pages[$idx].'</a>';
				$s = preg_replace("/\[\[(.*?)\]\]/", $replace, $s, 1);
				$idx++;
			}
		}
		return $s;
	}
	
	/**
	 * Replace the instances of the string [toc] with a list element that will be populated by
	 * a table of contents by the JavaScript library
	 * @param string $s
	 * @return string
	 */
	static public function generate_toc($s) {
		if (strpos($s,'[toc]') !== false) {
			//$toc_md = wiki_toc($s);	// Generate Markdown-formatted list prior to HTML render
			$toc_md = '<ul id="wiki-toc"></ul>'; // use the available jQuery plugin http://ndabas.github.io/toc/
			$s = preg_replace("/\[toc\]/", $toc_md, $s, -1);
		}
		return $s;
	}
	
	/**
	 *  Converts a select set of bbcode tags. Much of the code is copied from include/bbcode.php
	 * @param string $s
	 * @return string
	 */
	static public function bbcode($s) {
			
			$s = str_replace(array('[baseurl]', '[sitename]'), array(z_root(), get_config('system', 'sitename')), $s);
			
			$observer = App::get_observer();
			if ($observer) {
					$s1 = '<span class="bb_observer" title="' . t('Different viewers will see this text differently') . '">';
					$s2 = '</span>';
					$obsBaseURL = $observer['xchan_connurl'];
					$obsBaseURL = preg_replace("/\/poco\/.*$/", '', $obsBaseURL);
					$s = str_replace('[observer.baseurl]', $obsBaseURL, $s);
					$s = str_replace('[observer.url]', $observer['xchan_url'], $s);
					$s = str_replace('[observer.name]', $s1 . $observer['xchan_name'] . $s2, $s);
					$s = str_replace('[observer.address]', $s1 . $observer['xchan_addr'] . $s2, $s);
					$s = str_replace('[observer.webname]', substr($observer['xchan_addr'], 0, strpos($observer['xchan_addr'], '@')), $s);
					$s = str_replace('[observer.photo]', '', $s);
			} else {
					$s = str_replace('[observer.baseurl]', '', $s);
					$s = str_replace('[observer.url]', '', $s);
					$s = str_replace('[observer.name]', '', $s);
					$s = str_replace('[observer.address]', '', $s);
					$s = str_replace('[observer.webname]', '', $s);
					$s = str_replace('[observer.photo]', '', $s);
			}
	
			return $s;
	}
	
	static public function get_file_ext($arr) {
		if($arr['mimeType'] == 'text/bbcode')
			return '.bb';
		else
			return '.md';
	}
	
	// This function is derived from 
	// http://stackoverflow.com/questions/32068537/generate-table-of-contents-from-markdown-in-php
	static public function toc($content) {
	  // ensure using only "\n" as line-break
	  $source = str_replace(["\r\n", "\r"], "\n", $content);
	
	  // look for markdown TOC items
	  preg_match_all(
		'/^(?:=|-|#).*$/m',
		$source,
		$matches,
		PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE
	  );
	
	  // preprocess: iterate matched lines to create an array of items
	  // where each item is an array(level, text)
	  $file_size = strlen($source);
	  foreach ($matches[0] as $item) {
		$found_mark = substr($item[0], 0, 1);
		if ($found_mark == '#') {
		  // text is the found item
		  $item_text = $item[0];
		  $item_level = strrpos($item_text, '#') + 1;
		  $item_text = substr($item_text, $item_level);
		} else {
		  // text is the previous line (empty if <hr>)
		  $item_offset = $item[1];
		  $prev_line_offset = strrpos($source, "\n", -($file_size - $item_offset + 2));
		  $item_text =
			substr($source, $prev_line_offset, $item_offset - $prev_line_offset - 1);
		  $item_text = trim($item_text);
		  $item_level = $found_mark == '=' ? 1 : 2;
		}
		if (!trim($item_text) OR strpos($item_text, '|') !== FALSE) {
		  // item is an horizontal separator or a table header, don't mind
		  continue;
		}
		$raw_toc[] = ['level' => $item_level, 'text' => trim($item_text)];
	  }
		$o = '';
		foreach($raw_toc as $t) {
			$level = intval($t['level']);
			$text = $t['text'];
			switch ($level) {
				case 1:
					$li = '* ';
					break;
				case 2:
					$li = '  * ';
					break;
				case 3:
					$li = '    * ';
					break;
				case 4:
					$li = '      * ';
					break;
				default:
					$li = '* ';
					break;
			}
			$o .= $li . $text . "\n";
		}
	  return $o;
	}

}