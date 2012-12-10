<?php
if(!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("newthread_do_newthread_end", "mention_thread");
$plugins->add_hook("newreply_do_newreply_end", "mention_post");

function mention_info()
{
    return array(
        "name"        => "Mention",
        "description" => "Benachrichtigt Benutzer bei Erwähnung ihres Namens",
        "website"     => "http://mybbservice.de/",
        "author"      => "MyBBService",
        "authorsite"  => "http://mybbservice.de/",
        "version"     => "1.0",
        "guid"        => "",
		"compatibility"	=> "16*",
    );
}

function mention_activate() {}

function mention_deactivate() {}
 
function mention_thread()
{
	global $new_thread, $thread_info, $info;
	$new_thread['pid'] = $thread_info['pid'];
	$info = $new_thread;
	preg_replace_callback('/@"([^<]+?)"|@([^\s<)]+)/', "mention_filter", $new_thread['message']);
}

function mention_post()
{
	global $post, $postinfo, $info;
	$post['pid'] = $postinfo['pid'];
	$info = $post;
	preg_replace_callback('/@"([^<]+?)"|@([^\s<)]+)/', "mention_filter", $post['message']);
}

function mention_filter(array $match)
{
	global $db, $info, $mybb;
	
	$found = $match[0];
	$search = $db->escape_string(my_strtolower(substr($found, 1)));

	$query = $db->simple_select("users", "uid, username", "LOWER(username)='{$search}'", array('limit' => 1));
	if($db->num_rows($query) === 1) {
		$user = $db->fetch_array($query);

		require_once  MYBB_ROOT."inc/datahandlers/pm.php";
		$pmhandler = new PMDataHandler();

		$pm = array(
			"subject" => "Du wurdest in einem Beitrag erwähnt",
			"message" => "Hallo {$user['username']},

Wir schreiben dir, weil du von [url={$mybb->settings['bburl']}/member.php?action=profile&uid={$info['uid']}]{$info['username']}[/url] in einem neuen [url={$mybb->settings['bburl']}/showthread.php?pid={$info['pid']}#pid{$info['pid']}]Beitrag[/url] erwähnt wurdest.

Mit freundlichen Grüßen",
			"icon" => 0,
			"fromid" => 0,
		);
		$pm['toid'][] = $user['uid'];

		$pmhandler->admin_override = true;
		$pmhandler->set_data($pm);

		// Now let the pm handler do all the hard work.
		if($pmhandler->validate_pm())
		{
			$pminfo = $pmhandler->insert_pm();
		}else {
			$pm_errors = $pmhandler->get_friendly_errors();
			$send_errors = inline_error($pm_errors);
			echo $send_errors;
		}

	}
	return $found;
}
?>