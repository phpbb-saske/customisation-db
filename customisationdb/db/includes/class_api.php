<?php
/** 
*
* @package MOD api
* @version $Id: api.php,v 1.34 2008/01/15 06:29:12 paul999 Exp $
* @copyright (c) 2008 phpBB Group, phpBB MOD team
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/


/**
 * @packacke MOD api
 *
 * Base class with functions for posting, moving, editing etc.
 *
 */
class phpbb_api
{
	/**
	 * Add a new topic to the database.
	 * @param $options array Array with post data, see our documentation for exact required items
	 * @param $poll array Array with poll options.
	 * @return mixed false if there was an error, topic_id when the new topic was created.
	 */
	function topic_add(&$options, $poll = array())
	{
		global $phpbb_root_path, $phpEx, $db, $user, $config;
		
		if (!class_exists('parse_message'))
		{
			include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
		}

		if (!function_exists('submit_post'))
		{
			include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
		}
		
		// Get correct data from forums table to be sure all data is there.
		$sql = 'SELECT forum_parents, forum_name, enable_indexing
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . $options['forum_id'];
		$result = $db->sql_query($sql);
		$forum_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if (!$forum_data)
		{
		    return false;
		}

		$message_parser = new parse_message();
		$message_parser->message = &$options['post_text'];
		unset($options['post_text']);
		
		// Only add poll if poll_title isnt empty.
		if (empty($poll['poll_title']))
		{
			$poll = array();
		}
		
		if (isset ($poll['poll_option_text']) && !empty($poll['poll_option_text']) && !empty($poll['poll_title']))
		{
			$message_parser->parse_poll($poll);
		}
		else
		{
			$poll = array();
		}
		
		// Some data for the ugly fix below :P
		$sql = 'SELECT username, user_colour, user_permissions, user_type
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $options['poster_id'];
		$result = $db->sql_query($sql);
		$user_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if (!$user_data)
		{
		    return false;
		}

		// Ugly fix, to be sure it is posted for the right user ;)
		$old_data = $user->data;
		$user->data['user_id'] = $options['poster_id'];
		$user->data['username'] = $user_data['username'];
		$user->data['user_colour'] = $user_data['user_colour'];
		$user->data['user_permissions'] = $user_data['user_permissions'];
		$user->data['user_type'] = $user_data['user_type'];
		
		// Same for auth, be sure its posted with correct permissions :)
		global $auth;
		$old_auth = $auth;
		
		$auth = new auth();
		$auth->acl($user->data);		
		
		if ($options['enable_bbcode'])
		{
			global $config;
			
			$message_parser->parse($options['enable_bbcode'], $options['enable_urls'], $options['enable_smilies'], (bool) $auth->acl_get('f_img', $options['forum_id']), (bool) $auth->acl_get('f_flash', $options['forum_id']),  (bool) $auth->acl_get('f_reply', $options['forum_id']), $config['allow_post_links']);
		}

		$data = array(
			'topic_title'			=> $options['topic_title'],
			'topic_first_post_id'	=> 0,
			'topic_last_post_id'	=> 0,
			'topic_time_limit'		=> $options['topic_time_limit'],
			'topic_attachment'		=> 0,
			'post_id'				=> 0,
			'topic_id'				=> 0,
			'forum_id'				=> $options['forum_id'],
			'icon_id'				=> (int) $options['icon_id'],
			'poster_id'				=> (int) $options['poster_id'],
			'enable_sig'			=> (bool) $options['enable_sig'],
			'enable_bbcode'			=> (bool) $options['enable_bbcode'],
			'enable_smilies'		=> (bool) $options['enable_smilies'],
			'enable_urls'			=> (bool) $options['enable_urls'],
			'enable_indexing'		=> (bool) $forum_data['enable_indexing'],
			'message_md5'			=> (string) md5($message_parser->message),
			'post_time'				=> $options['post_time'],
			'post_checksum'			=> '',
			'post_edit_reason'		=> '',
			'post_edit_user'		=> 0,
			'forum_parents'			=> $forum_data['forum_parents'],
			'forum_name'			=> $forum_data['forum_name'],
			'notify'				=> false,
			'notify_set'			=> 0,
			'poster_ip'				=> $options['poster_ip'],
			'post_edit_locked'		=> (int) $options['post_edit_locked'],
			'topic_status'			=> $options['topic_status'],
			'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
			'bbcode_uid'			=> $message_parser->bbcode_uid,
			'message'				=> $message_parser->message,
			'attachment_data'		=> array(),
			'filename_data'			=> array(),
			'post_approved'			=> 1,
		);
		
		$url_tmp = $config['max_post_urls'];
		$config['max_post_urls'] = 0;
		
		// Aaaand, submit it.
		submit_post('post', $options['topic_title'], $user_data['username'], $options['topic_type'], $poll, $data, true);
		
		// And restore it
		$user->data = $old_data;
		$auth = $old_auth;
		
		$config['max_post_urls'] = $url_tmp;
		
		return $data['topic_id'];
	}
	
	/**
	 * Add a new post to a existing topic.
	 * @param $options array list with options, see for items/values our documentation
	 * @return mixed false if there was an error, post_id when the post was added to the topic.
	 */
	function post_add(&$options)
	{
		global $phpbb_root_path, $phpEx, $db, $user, $config;

		if (!class_exists('parse_message'))
		{
			include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
		}

		if (!function_exists('submit_post'))
		{
			include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
		}
		
		// Check forum data, and if forum_id is the same.
		// Also get topic data.
		$sql = 'SELECT f.*, t.*
			FROM ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f
			WHERE t.topic_id = ' . $options['topic_id'] . '
				AND (f.forum_id = t.forum_id
					OR f.forum_id = ' . $options['forum_id'] . ')';
		$result = $db->sql_query($sql);
		$post_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if (!$post_data)
		{
			return false;
		}
		
		if ($options['forum_id'] != $post_data['forum_id'])
		{
			$options['forum_id'] = (int)$post_data['forum_id'];
		}

		$sql = 'SELECT forum_parents, forum_name, enable_indexing
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . $options['forum_id'];
		$result = $db->sql_query($sql);
		$forum_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if (!$forum_data)
		{
		    return false;
		}

		$message_parser = new parse_message();
		$message_parser->message = &$options['post_text'];
		unset($options['post_text']);

		// Get the data for our ugly fix later.
		$sql = 'SELECT username, user_colour, user_permissions, user_type
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $options['poster_id'];
		$result = $db->sql_query($sql);
		$user_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if (!$user_data)
		{
			return false;
		}

		// Ugly fix, to be sure it is posted for the right user ;)
		$old_data = $user->data;
		$user->data['user_id'] = $options['poster_id'];
		$user->data['username'] = $user_data['username'];
		$user->data['user_colour'] = $user_data['user_colour'];
		$user->data['user_permissions'] = $user_data['user_permissions'];
		$user->data['user_type'] = $user_data['user_type'];
		
		// And the permissions
		global $auth;
		$old_auth = $auth;
		
		$auth = new auth();
		$auth->acl($user->data);
		
		if ($options['enable_bbcode'])
		{
			global $config;
			
			$message_parser->parse($options['enable_bbcode'], $options['enable_urls'], $options['enable_smilies'], (bool) $auth->acl_get('f_img', $options['forum_id']), (bool) $auth->acl_get('f_flash', $options['forum_id']),  (bool) $auth->acl_get('f_reply', $options['forum_id']), $config['allow_post_links']);
		}

		$data = array(
			'topic_title'			=> $options['topic_title'],
			'topic_first_post_id'	=> $post_data['topic_first_post_id'],
			'topic_last_post_id'	=> $post_data['topic_last_post_id'],
			'topic_time_limit'		=> $options['topic_time_limit'],
			'topic_attachment'		=> 0,
			'post_id'				=> 0,
			'topic_id'				=> $options['topic_id'],
			'forum_id'				=> $options['forum_id'],
			'icon_id'				=> (int) $options['icon_id'],
			'poster_id'				=> (int) $options['poster_id'],
			'enable_sig'			=> (bool) $options['enable_sig'],
			'enable_bbcode'			=> (bool) $options['enable_bbcode'],
			'enable_smilies'		=> (bool) $options['enable_smilies'],
			'enable_urls'			=> (bool) $options['enable_urls'],
			'enable_indexing'		=> (bool) $forum_data['enable_indexing'],
			'message_md5'			=> (string) md5($message_parser->message),
			'post_time'				=> $options['post_time'],
			'post_checksum'			=> '',
			'post_edit_reason'		=> '',
			'post_edit_user'		=> 0,
			'forum_parents'			=> $forum_data['forum_parents'],
			'forum_name'			=> $forum_data['forum_name'],
			'notify'				=> false,
			'notify_set'			=> 0,
			'poster_ip'				=> $options['poster_ip'],
			'post_edit_locked'		=> (int) $options['post_edit_locked'],
			'topic_status'			=> $options['topic_status'],
			'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
			'bbcode_uid'			=> $message_parser->bbcode_uid,
			'message'				=> $message_parser->message,
			'attachment_data'		=> array(),
			'filename_data'			=> array(),
			'post_approved'			=> 1,
			'topic_replies'			=> false,
		);

		$poll = array();
		
		$url_tmp = $config['max_post_urls'];
		$config['max_post_urls'] = 0;

		submit_post('reply', $options['topic_title'], $user_data['username'], $options['topic_type'], $poll, $data, true);
		
		$config['max_post_urls'] = $url_tmp;
				
		// And restore the permissions.
		$user->data = $old_data;
		$auth = $old_auth;
		
		return $data['post_id'];
	}
	
	/**
	 * Edit a already existing post
	 * @param $options Array Data from for the edited post. See our documentation for more information about the options
	 * @return mixed false if there was an error, post_id when the post was edited.
	 */
	function post_edit(&$options)
	{
		global $phpbb_root_path, $phpEx, $db;
		if (!class_exists('parse_message'))
		{
			include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
		}

		if (!function_exists('submit_post'))
		{
			include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
		}

		// Check data from the user filled in, and get topic data.
		$sql = 'SELECT f.*, t.*
			FROM ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f
			WHERE t.topic_id = ' . (int) $options['topic_id'] . '
				AND (f.forum_id = t.forum_id
					OR f.forum_id = ' . (int) $options['forum_id'] . ')';
		$result = $db->sql_query($sql);
		$post_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if (!$post_data)
		{
		    return false;
		}

		// Get forum parents etc.
		$sql = 'SELECT forum_parents, forum_name, enable_indexing
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . (int) $options['forum_id'];
		$result = $db->sql_query($sql);
		$forum_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if (!$forum_data)
		{
		    return false;
		}

		$message_parser = new parse_message();
		$message_parser->message = &$options['post_text'];
		unset($options['post_text']);

		$sql = 'SELECT user_id, username, user_colour, user_permissions, user_type
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $options['poster_id'];
		$result = $db->sql_query($sql);
		$user_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if (!$user_data)
		{
		    return false;
		}
		
		$post_data['poll_options'] = array();
		
		// Poll checks
		if (!empty($post_data['poll_title']))
		{
			$sql = 'SELECT poll_option_text
				FROM ' . POLL_OPTIONS_TABLE . "
				WHERE topic_id = {$post_data['topic_id']}
				ORDER BY poll_option_id";
			$result = $db->sql_query($sql);
			
			while ($row = $db->sql_fetchrow($result))
			{
				$post_data['poll_options'][] = trim($row['poll_option_text']);
			}
			$db->sql_freeresult($result);
			$poll = array(
				'poll_title'		=> $post_data['poll_title'],
				'poll_length'		=> $post_data['poll_length'],
				'poll_max_options'	=> $post_data['poll_max_options'],
				'poll_option_text'	=> implode("\n", $post_data['poll_options']),
				'poll_start'		=> $post_data['poll_start'],
				'poll_last_vote'	=> $post_data['poll_last_vote'],
				'poll_vote_change'	=> $post_data['poll_vote_change'],
				'enable_bbcode'		=> true,
				'enable_urls'		=> true,
				'enable_smilies'	=> true,
				'img_status'		=> true,
			);
			$message_parser->parse_poll($poll);							
		}
		
		// Overwrite the auth.
		global $auth;
		$old_auth = $auth;
		
		$auth = new auth();
		$auth->acl($user_data);		
		
		if ($options['enable_bbcode'])
		{
			global $config;
			
			$message_parser->parse($options['enable_bbcode'], $options['enable_urls'], $options['enable_smilies'], (bool) $auth->acl_get('f_img', $options['forum_id']), (bool) $auth->acl_get('f_flash', $options['forum_id']),  (bool) $auth->acl_get('f_reply', $options['forum_id']), $config['allow_post_links']);
		}

		$data = array(
			'topic_title'			=> $options['topic_title'],
			'topic_first_post_id'	=> $post_data['topic_first_post_id'],
			'topic_last_post_id'	=> $post_data['topic_last_post_id'],
			'topic_time_limit'		=> $options['topic_time_limit'],
			'topic_attachment'		=> 0,
			'post_id'				=> $options['post_id'],
			'topic_id'				=> $options['topic_id'],
			'forum_id'				=> $options['forum_id'],
			'icon_id'				=> (int) $options['icon_id'],
			'poster_id'				=> (int) $options['poster_id'],
			'enable_sig'			=> (bool) $options['enable_sig'],
			'enable_bbcode'			=> (bool) $options['enable_bbcode'],
			'enable_smilies'		=> (bool) $options['enable_smilies'],
			'enable_urls'			=> (bool) $options['enable_urls'],
			'enable_indexing'		=> (bool) $forum_data['enable_indexing'],
			'message_md5'			=> (string) md5($message_parser->message),
			'post_time'				=> $options['post_time'],
			'post_checksum'			=> '',
			'post_edit_reason'		=> $options['post_edit_reason'],
			'post_edit_user'		=> $options['post_edit_user'],
			'forum_parents'			=> $forum_data['forum_parents'],
			'forum_name'			=> $forum_data['forum_name'],
			'notify'				=> false,
			'notify_set'			=> 0,
			'poster_ip'				=> $options['poster_ip'],
			'post_edit_locked'		=> (int) $options['post_edit_locked'],
			'topic_status'			=> $options['topic_status'],
			'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
			'bbcode_uid'			=> $message_parser->bbcode_uid,
			'message'				=> $message_parser->message,
			'attachment_data'		=> array(),
			'filename_data'			=> array(),
			'topic_replies_real'	=> $post_data['topic_replies_real'],
			'post_approved'			=> 1,
			'topic_replies'			=> false,
		);
		
		$url_tmp = $config['max_post_urls'];
		$config['max_post_urls'] = 0;

		submit_post('edit', $options['topic_title'], $user_data['username'], $options['topic_type'], $poll, $data, true);
		
		$config['max_post_urls'] = $url_tmp;
				
		// Aaand reset the auth data.
		$auth = $old_auth;
		
		return $data['post_id'];
	}
	
	/**
	 * Send a PM to the user
	 * @param $options Array data with the user where its send to, message etc. See our documentation for more information
	 * @return mixed false when there was a error, message_id when there message was posted.
	 */
	function pm_add(&$options)
	{
		global $phpbb_root_path, $phpEx, $db;
		global $user;
		
		if (!class_exists('parse_message'))
		{
			include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
		}

		if (!function_exists('submit_pm'))
		{
			include($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
		}
		
		$message_parser = new parse_message();
		$message_parser->message = &$options['post_text'];
		unset($options['post_text']);		

		if ($options['enable_bbcode'])
		{
			global $auth, $config;
			$message_parser->parse($options['enable_bbcode'], $options['enable_urls'], $options['enable_smilies'], (bool) $auth->acl_get('u_pm_img'), (bool) $auth->acl_get('u_pm_flash'), true, $config['allow_post_links']);
		}

		$sql = 'SELECT username
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $options['user_id'];
		$result = $db->sql_query($sql);
		$user_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if (!$user_data)
		{
		    return false;
		}

		$pm_data = array(
			'from_user_id'			=> $options['user_id'],
			'from_user_ip'			=> $options['user_ip'],
			'from_username'			=> $user_data['username'],
			'icon_id'				=> (int) $options['icon_id'],
			'enable_sig'			=> (bool) $options['enable_sig'],
			'enable_bbcode'			=> (bool) $options['enable_bbcode'],
			'enable_smilies'		=> (bool) $options['enable_smilies'],
			'enable_urls'			=> (bool) $options['enable_urls'],
			'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
			'bbcode_uid'			=> $message_parser->bbcode_uid,
			'message'				=> $message_parser->message,
			'address_list'			=> $options['address_list']
		);
		unset($message_parser);

		$msg_id = submit_pm('post', $options['subject'], $pm_data);
		return $msg_id;
	}
	
	/**
	 * Function to move a topic. It uses just move_topics, so that one can also be used to move it.
	 * @param $topic_id int topic_id that need moved
	 * @param $forum_id int forum_id where $topic_id need to be moved to.
	 * @return nothing
	 */
	function topic_move($topic_id, $forum_id)
	{
		global $phpbb_root_path, $phpEx;
		
		if (!function_exists('move_topics'))
		{
			include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
		}

		move_topics($topic_id, $forum_id);
	}
}

?>