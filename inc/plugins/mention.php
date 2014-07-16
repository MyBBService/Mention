<?php
if(!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("newthread_do_newthread_end", "mention_thread");
$plugins->add_hook("newreply_do_newreply_end", "mention_post");

$mention_count = array();

function mention_info()
{
	return array(
		"name"			=> "Mention",
		"description"	=> "Benachrichtigt Benutzer bei Erwähnung ihres Namens",
		"website"		=> "http://mybbservice.de/",
		"author"		=> "MyBBService",
		"authorsite"	=> "http://mybbservice.de/",
		"version"		=> "1.0.3",
		"guid"			=> "",
		"compatibility"	=> "*",
		"dlcid"			=> "19"
	);
}

function mention_install()
{
	global $db;
	$group = array(
		"title"			=> "Einstellungen für Mention",
		"name"			=> "mention",
		"description"	=> "",
		"disporder"		=> "50",
		"isdefault"		=> "0",
	);
	$gid = $db->insert_query("settinggroups", $group);

	$setting = array(
		"name"			=> "mention_subject",
		"title"			=> "Titel",
		"description"	=> "Der Titel der versendeten PN. Ersetzunngsmöglichkeiten: siehe unten",
		"optionscode"	=> "text",
		"value"			=> 'Du wurdest in einem Beitrag erwähnt',
		"disporder"		=> '1',
		"gid"			=> (int)$gid,
	);
	$db->insert_query("settings", $setting);

	$setting = array(
		"name"			=> "mention_message",
		"title"			=> "Nachricht",
		"description"	=> "Der Inhalt der PN. Ersetzungsmöglichkeiten:<br />
{fuser} -> Name des benachrichtigten User<br />{fid} -> ID des benachrichtigen User<br />{flink} -> Link zu dem Profil des Users<br />
{user} -> Name des Users der erwähnt<br />{uid} -> ID des Users der erwähnt<br />{ulink} -> Link zu dem Profil des Users<br />
{subject} -> Titel des Beitrags<br />{link} -> Link zu dem Beitrag",
		"optionscode"	=> "textarea",
		"value"			=> 'Hallo {fuser},

Wir schreiben dir, weil du von [url={ulink}]{user}[/url] in dem Beitrag [url={link}]{subject}[/url] erwähnt wurdest.

Mit freundlichen Grüßen',
		"disporder"		=> '2',
		"gid"			=> (int)$gid,
	);
	$db->insert_query("settings", $setting);

	$setting = array(
		"name"			=> "mention_disable_quote",
		"title"			=> "Deaktiviere Mention in [quote] Tags",
		"description"	=> "Soll das Plugin innerhalb von [quote] Tags reagieren?",
		"optionscode"	=> "yesno",
		"value"			=> "yes",
		"disporder"		=> '3',
		"gid"			=> (int)$gid,
	);
	$db->insert_query("settings", $setting);

	$setting = array(
		"name"			=> "mention_disable_code",
		"title"			=> "Deaktiviere Mention in [code] Tags",
		"description"	=> "Soll das Plugin innerhalb von [code] Tags reagieren?",
		"optionscode"	=> "yesno",
		"value"			=> "yes",
		"disporder"		=> '4',
		"gid"			=> (int)$gid,
	);
	$db->insert_query("settings", $setting);

	$setting = array(
		"name"			=> "mention_disable_php",
		"title"			=> "Deaktiviere Mention in [php] Tags",
		"description"	=> "Soll das Plugin innerhalb von [php] Tags reagieren?",
		"optionscode"	=> "yesno",
		"value"			=> "yes",
		"disporder"		=> '5',
		"gid"			=> (int)$gid,
	);
	$db->insert_query("settings", $setting);
	rebuild_settings();
}

function mention_is_installed() {
	global $db;
	$query = $db->simple_select("settinggroups", "gid", "name='mention'");
	if($db->num_rows($query) > 0)
		return true;
	return false;
}

function mention_uninstall()
{
	global $db;

	$query = $db->simple_select("settinggroups", "gid", "name='mention'");
	$g = $db->fetch_array($query);
	$db->delete_query("settinggroups", "gid='".$g['gid']."'");
	$db->delete_query("settings", "gid='".$g['gid']."'");
	rebuild_settings();
}

function mention_activate() {}

function mention_deactivate() {}
 
function mention_thread()
{
	global $new_thread, $thread_info, $info;
	$new_thread['pid'] = $thread_info['pid'];
	$info = $new_thread;
	mention_start($new_thread['message']);
}

function mention_post()
{
	global $post, $postinfo, $info;
	$post['pid'] = $postinfo['pid'];
	$info = $post;
	mention_start($post['message']);
}

function mention_start($message)
{
	global $mybb;
	if($mybb->settings['mention_disable_quote'])
		$message = preg_replace("#\[quote\](.*?)\[/quote\]#si", "", $message);
	if($mybb->settings['mention_disable_code'])
		$message = preg_replace("#\[code\](.*?)\[/code\]#si", "", $message);
	if($mybb->settings['mention_disable_php'])
		$message = preg_replace("#\[php\](.*?)\[/php\]#si", "", $message);

	preg_replace_callback('/@"([^<]+?)"|@([^\s<)]+)/', "mention_filter", $message);
}

function mention_filter(array $match)
{
	global $db, $info, $mybb, $user, $mention_count;

	$found = $match[0];

	if(isset($mention_count[$found]) && $mention_count[$found])
		return $found;
	$mention_count[$found] = true;
	$search = $db->escape_string(trim(my_strtolower(substr($found, 1)), '"'));

	$query = $db->simple_select("users", "uid, username", "LOWER(username)='{$search}'");
	if($db->num_rows($query) === 1) {
		$user = $db->fetch_array($query);

		require_once  MYBB_ROOT."inc/datahandlers/pm.php";
		$pmhandler = new PMDataHandler();

		$pm = array(
			"subject" => mention_parser($mybb->settings['mention_subject']),
			"message" => mention_parser($mybb->settings['mention_message']),
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

function mention_parser($message)
{
	global $user, $info, $mybb;

	$message = str_replace("{fuser}", $user['username'], $message);
	$message = str_replace("{fid}", $user['uid'], $message);
	$message = str_replace("{flink}", $mybb->settings['bburl']."/".get_profile_link($user['uid']), $message);
	$message = str_replace("{user}", $info['username'], $message);
	$message = str_replace("{uid}", $info['uid'], $message);
	$message = str_replace("{ulink}", $mybb->settings['bburl']."/".get_profile_link($info['uid']), $message);
	$message = str_replace("{subject}", $info['subject'], $message);
	$message = str_replace("{link}", $mybb->settings['bburl']."/".get_post_link($info['pid'], $info['tid'])."#pid".$info['pid'], $message);
	
	return $message;
}
?>