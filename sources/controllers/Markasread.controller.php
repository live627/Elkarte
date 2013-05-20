<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file is mainly concerned with minor tasks relating to boards, such as
 * marking them read, collapsing categories, or quick moderation.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class MarkRead_Controller
{
	/**
	* This is the main function for markasread file.
	*/
	public function action_index()
	{
		// These checks have been moved here.
		// Do NOT call the specific handlers directly.

		// Guests can't mark things.
		is_not_guest();

		checkSession('get');

		$redir = $this->_dispatch();

		redirectexit($redir);
	}

	/**
	* This function forwards the request to the appropriate function.
	*/
	private function _dispatch()
	{
		// sa=all action_markboards()
		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'all')
			$sa = 'action_markboards';
		elseif (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'unreadreplies')
			// mark topics from unread
			$sa = 'action_markreplies';
		elseif (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'topic')
			// mark a single topic as read
			$sa = 'action_marktopic';
		else
			// the rest, for now...
			$sa = 'action_markasread';

		return $this->{$sa}();
	}

	/**
	* This is the main function for markasread file when using APIs.
	*/
	public function action_index_api()
	{
		global $context, $txt;

		// Guests can't mark things.
		is_not_guest('', false);

		checkSession('get');

		$this->_dispatch();

		// For the time being this is a special case
		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'all')
		{
			loadTemplate('Xml');

			$context['template_layers'] = array();
			$context['sub_template'] = 'generic_xml_buttons';

			$context['xml_data'] = array(
				'text' => $txt['unread_topics_visit_none'],
			);
		}
		else
			// No need to do anything, just die
			obExit(false);
	}

	/**
	* action=markasread;sa=all
	* Marks boards as read (or unread).
	*/
	public function action_markboards()
	{
		global $modSettings;

		require_once(SUBSDIR . '/Boards.subs.php');

		// Find all the boards this user can see.
		$boards = accessibleBoards();

		if (!empty($boards))
			markBoardsRead($boards, isset($_REQUEST['unread']));

		$_SESSION['id_msg_last_visit'] = $modSettings['maxMsgID'];
		if (!empty($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'action=unread') !== false)
			return 'action=unread';

		if (isset($_SESSION['topicseen_cache']))
			$_SESSION['topicseen_cache'] = array();

		return '';
	}

	/**
	* action=markasread;sa=unreadreplies
	* Marks the selected topics as read.
	*/
	public function action_markreplies()
	{
		global $user_info, $modSettings;

		$db = database();

		// Make sure all the topics are integers!
		$topics = array_map('intval', explode('-', $_REQUEST['topics']));

		require_once(SUBSDIR . '/Topic.subs.php');
		$logged_topics = getLoggedTopics($user_info['id'], $topics);

		$markRead = array();
		foreach ($topics as $id_topic)
			$markRead[] = array($user_info['id'], (int) $id_topic, $modSettings['maxMsgID'], $logged_topics[$id_topic]);

		markTopicsRead($markRead, true);

		if (isset($_SESSION['topicseen_cache']))
			$_SESSION['topicseen_cache'] = array();

		return 'action=unreadreplies';
	}

	/**
	* action=markasread;sa=topic
	* Mark a single topic as unread.
	*/
	public function action_marktopic()
	{
		global $board, $topic, $user_info;

		$db = database();

		require_once(SUBSDIR . '/Topic.subs.php');

		// Mark a topic unread.
		// First, let's figure out what the latest message is.
		$topicinfo = getTopicInfo($topic, 'all');
		$topic_msg_id = (int) $_GET['t'];

		if (!empty($topic_msg_id))
		{
			// If they read the whole topic, go back to the beginning.
			if ($topic_msg_id >= $topicinfo['id_last_msg'])
				$earlyMsg = 0;
			// If they want to mark the whole thing read, same.
			elseif ($topic_msg_id <= $topicinfo['id_first_msg'])
				$earlyMsg = 0;
			// Otherwise, get the latest message before the named one.
			else
			{
				$result = $db->query('', '
					SELECT MAX(id_msg)
					FROM {db_prefix}messages
					WHERE id_topic = {int:current_topic}
						AND id_msg >= {int:id_first_msg}
						AND id_msg < {int:topic_msg_id}',
					array(
						'current_topic' => $topic,
						'topic_msg_id' => $topic_msg_id,
						'id_first_msg' => $topicinfo['id_first_msg'],
					)
				);
				list ($earlyMsg) = $smcFunc['db_fetch_row']($result);
				$smcFunc['db_free_result']($result);
			}
		}
		// Marking read from first page?  That's the whole topic.
		elseif ($_REQUEST['start'] == 0)
			$earlyMsg = 0;
		else
		{
			$result = $db->query('', '
				SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
				ORDER BY id_msg
				LIMIT {int:start}, 1',
				array(
					'current_topic' => $topic,
					'start' => (int) $_REQUEST['start'],
				)
			);
			list ($earlyMsg) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			$earlyMsg--;
		}

		// Blam, unread!
		markTopicsRead(array($user_info['id'], $topic, $earlyMsg, $topicinfo['disregarded']), true);

		return 'board=' . $board . '.0';
	}

	/**
	* Mark as read: boards, topics, unread replies.
	* Accessed by action=markasread
	* Subactions: sa=topic, sa=all, sa=unreadreplies
	*/
	public function action_markasread()
	{
		global $board, $user_info, $board_info, $modSettings;

		$db = database();

		checkSession('get');

		require_once(SUBSDIR . '/Boards.subs.php');

		$categories = array();
		$boards = array();

		if (isset($_REQUEST['c']))
		{
			$_REQUEST['c'] = explode(',', $_REQUEST['c']);
			foreach ($_REQUEST['c'] as $c)
				$categories[] = (int) $c;
		}
		if (isset($_REQUEST['boards']))
		{
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			foreach ($_REQUEST['boards'] as $b)
				$boards[] = (int) $b;
		}
		if (!empty($board))
			$boards[] = (int) $board;

		if (isset($_REQUEST['children']) && !empty($boards))
		{
			// They want to mark the entire tree starting with the boards specified
			// The easist thing is to just get all the boards they can see, but since we've specified the top of tree we ignore some of them
			addChildBoards($boards);
		}

		$boards = boardsPosts($boards, $categories);

		if (empty($boards))
			return '';

		markBoardsRead($boards, isset($_REQUEST['unread']));

		foreach ($boards as $b)
		{
			if (isset($_SESSION['topicseen_cache'][$b]))
				$_SESSION['topicseen_cache'][$b] = array();
		}

		if (!isset($_REQUEST['unread']))
		{
			// Find all the boards this user can see.
			$result = $db->query('', '
				SELECT b.id_board
				FROM {db_prefix}boards AS b
				WHERE b.id_parent IN ({array_int:parent_list})
					AND {query_see_board}',
				array(
					'parent_list' => $boards,
				)
			);
			if ($smcFunc['db_num_rows']($result) > 0)
			{
				$logBoardInserts = '';
				while ($row = $smcFunc['db_fetch_assoc']($result))
					$logBoardInserts[] = array($modSettings['maxMsgID'], $user_info['id'], $row['id_board']);
					$smcFunc['db_insert']('replace',
					'{db_prefix}log_boards',
					array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
					$logBoardInserts,
					array('id_member', 'id_board')
				);
			}
			$smcFunc['db_free_result']($result);
			if (empty($board))
				return '';
			else
				return 'board=' . $board . '.0';
		}
		else
		{
			if (empty($board_info['parent']))
				return '';
			else
				return 'board=' . $board_info['parent'] . '.0';
		}
	}
}