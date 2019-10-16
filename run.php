<?php

/* Botnix to discord connector for infobot.pm botnix module.
 * Connects to discord as a bot via the API and talks directly to the discord
 * module on a running instance of botnix on the same machine. If there is no instance
 * of botnix to connect to, the bot just answers with non-commital responses.
 *
 * Note that this does not fork as a demon and should be run via 'screen'.
 *
 * This bot uses an extensively modded version of team-reflex/discordphp - DO NOT
 * run "composer update" on it, or you'll lose compatibility changes with modern
 * discord API!
 *
 * - Brain#0001 Oct 2019.
 */

include __DIR__.'/vendor/autoload.php';
include __DIR__.'/globals.php';
include __DIR__.'/apiupdate.php';
include __DIR__.'/help.php';
include __DIR__.'/settings.php';
include __DIR__.'/infobot.php';

use Discord\Parts\User\Game;
use Discord\Parts\User\User;

/* Connect to discord */
$discord = new \Discord\Discord([
	'token' => $config['token'],
	'guildSubscriptions'=>false,
	'loadAllMembers'=>false,
	'retrieveBans'=>false,
]);

/* Discord API connection ready to serve! */
$discord->on('ready', function ($discord) use ($global_last_message) {
	echo "Bot '$discord->username#$discord->discriminator' ($discord->id) is ready.", PHP_EOL;
	$countdetails = count_guilds($discord);
	echo "Present on " . $countdetails['guild_count'] . " servers with a total of " . $countdetails['member_count'] . " members\n";

	$discord->on('message', function ($message) {
		global $discord;
		global $global_last_message;
		global $last_update_status;
		global $last_botsite_webapi_update;
		global $ircnick;
		global $config;
		global $randoms;

		// Grab from global, because discordphp strips it out!
		$author = $global_last_message->d->author;

		if ($ircnick == "") {
			$ircnick = $discord->username;
		}

		/* Update for top.gg API, if defined in config */
		if (time() - $last_botsite_webapi_update > 3600) {
			$last_botsite_webapi_update = time();
			$countdetails = count_guilds($discord);

			$topggapi = new WebsiteUpdatePostThread('https://top.gg/api/bots/' . $discord->id . '/stats', ['server_count' => $countdetails['guild_count']], $config["topggapikey"]);
			$dblapi = new WebsiteUpdatePostThread('https://discordbotlist.com/api/bots/' . $discord->id . '/stats', ["guilds"=>$countdetails['guild_count'],"users"=>$countdetails['member_count']], "Bot " . $config["dblapikey"]);
			$ondiscordapi = new WebsiteUpdatePostThread('https://bots.ondiscord.xyz/bot-api/bots/' . $discord->id . '/guilds', ["guildCount"=>$countdetails['guild_count']], $config["ondiscordapikey"]);
			$ddapi = new WebsiteUpdatePostThread('https://divinediscordbots.com/bot/'.$discord->id . '/stats', ['server_count'=>$countdetails['guild_count']], $config["ddbotsapikey"]);

			$topggapi->run();
			$dblapi->run();
			$ondiscordapi->run();
			$ddapi->run();
		}


		/* Update online presence */
		if (time() - $last_update_status > 120) {
			$last_update_status = time();
			echo "Running presence update\n";
			list($found,$info) = infobot("Self", $ircnick . " status");
			$countdetails = count_guilds($discord);
			preg_match('/^Since (.+?), there have been (\d+) modifications and (\d+) questions. I have been alive for (.+?), I currently know (\d+)/', $info, $matches);
			$game = $discord->factory(Game::class, [
				'name' => number_format($matches[5]) . " facts on " . number_format($countdetails['guild_count']) . " servers, " . number_format($countdetails['member_count']) . " total users",
				'url' => 'https://www.botnix.org/',
				'type' => 3,
			]);
			$discord->updatePresence($game);
			print "Presence update done\n";
		}

		$randomnick = "";
		if ($author->username != $discord->username && $author->id != $discord->id) {
			if (!isset($randoms[$message->channel->guild->id])) {
				$randoms[$message->channel->guild->id] = [];
			}
			$randoms[$message->channel->guild->id][$author->id] = $author->username;
			$random = rand(0, sizeof($randoms[$message->channel->guild->id]) - 1);
			$randomnick = array_slice($randoms[$message->channel->guild->id], $random, 1)[0];


			$ignorelist = getSetting($global_last_message->d->channel_id, "ignores");
			if (is_array($ignorelist)) {
				foreach ($ignorelist as $userid) {
					if ($userid == $author->id) {
						/* User is ignored! */
						print "Message droppped - user $userid is ignored\n";
						return;
					}
				}
			}
		}

		# Replace mention of bot with nickname, and strip newlines
		$content = preg_replace('/<@'.$discord->id.'>/', $discord->username, $message->content);
		$content = trim(preg_replace('/\r|\n/', ' ', $content));

		if ($content == $discord->username . " invite") {
			$message->channel->sendMessage("", false, GetHelp("invite", $discord->username, $author->username));
			return;
		}
		if ($content == $discord->username . " help") {
			echo "Responding to help on channel\n";
			$message->channel->sendMessage("<@$author->id> Please check your DMs for help text.");
			$message->author->sendMessage("", false, GetHelp("basic", $discord->username, $discord->id));
			return;
		}
		if (preg_match("/^". $discord->username . " help ([a-z]+)/i", $content, $match)) {
			$kw = $match[1];
			echo "Responding to help ($kw) on channel\n";
			$message->channel->sendMessage("<@$author->id> Please check your DMs for help text.");
			$message->author->sendMessage("", false, GetHelp($kw, $discord->username, $discord->id));
			return;
		}

		if (preg_match("/".$discord->username." config /", $content)) {
			$params = explode(' ', $content);
			$chanconfig = getSettings($global_last_message->d->channel_id);
			$access = false;

			/* First check if server owner of current guild */
			if ($message->channel->guild->owner_id == $author->id) {
				$access = true;
			} else {
				/* Iterate roles looking for manage messages or administrator */
				foreach ($message->channel->guild->roles as $id=>$role) {
					foreach ($global_last_message->d->member->roles as $memberrole) {
						if ($id == $memberrole) {
							if ($role->permissions->manage_messages == true || $role->permissions->administrator == true) {
								$access = true;
								break;
							}
						}
					}
					if ($access) {
						break;
					}
				}
			}
			if (!$access) {
				$message->channel->sendMessage("Sorry, <@" . $author->id . ">, you must be in a role with the \"manage messages\" permission to alter configuration for <#".$global_last_message->d->channel_id."> (or be server owner or an administrator)");
				return;
			}
			switch ($params[2]) {
				case 'show':
					$learn = false;
					if (!isset($chanconfig['learningdisabled'])) {
						$learn = true;
					}
					if ($chanconfig['learningdisabled'] == false) {
						$learn = true;
					}

					$message->channel->sendMessage("", false, [
						"title" => "Settings for this channel",
						"color"=>0xffda00,
						"thumbnail"=>["url"=>"https://www.botnix.org/images/botnix.png"],
						"footer"=>["link"=>"https;//www.botnix.org/", "text"=>"Powered by Botnix 2.0 with the infobot and discord modules", "icon_url"=>"https://www.botnix.org/images/botnix.png"],
						"fields"=>[
							["name"=>"Talk without being mentioned?", "value"=>(isset($chanconfig['talkative']) && $chanconfig['talkative'] == true ? 'Yes' : 'No'), "inline"=>false],
							["name"=>"Learn from this channel", "value"=>($learn ? 'Yes' : 'No'), "inline"=>false],
							["name"=>"Ignored Users", "value"=>(isset($chanconfig['ignores']) && is_array($chanconfig['ignores']) ? number_format(sizeof($chanconfig['ignores'])) : '0'), "inline"=>false],
						],
						"description" => "For help on changing these settings, type ```@".$discord->username." help config```",
					]);			
				break;
				case 'set':
					$flag = ($params[4] == 'yes' || $params[4] == 'on');
					switch ($params[3]) {
						case 'talkative':
							setSettings($global_last_message->d->channel_id, "talkative", $flag);
							$message->channel->sendMessage("", false, ["color"=>0xffda00,"description"=>"Talkative mode " .($flag ? 'enabled' : 'disabled')." on <#" . $global_last_message->d->channel_id .">"]);
						break;
						case 'learn':
							setSettings($global_last_message->d->channel_id, "learningdisabled", !$flag);
							$message->channel->sendMessage("", false, ["color"=>0xffda00,"description"=>"Learning mode " .($flag ? 'enabled' : 'disabled')." on <#" . $global_last_message->d->channel_id .">"]);
						break;

					}
				break;
				case 'ignore':
					$add_or_del = strtolower($params[3]);
					$userid = ($add_or_del == 'add' || $add_or_del == 'del') ? $params[4] : '';
					if (preg_match('/<@(\d+)>/', $userid, $usermatch) || strtolower($add_or_del) == 'list') {
						$userid = sizeof($usermatch) > 1 ? $usermatch[1] : '';
						$user_array = [];
						if (isset($chanconfig['ignores']) && is_array($chanconfig['ignores'])) {
							$user_array = $chanconfig['ignores'];
						}
						if ($add_or_del == 'add') {
							if ($userid == $discord->id) {
								$message->channel->sendMessage("", false, ["color"=>0xffda00,"description"=>"Confucious say, only an idiot would ignore their inner voice..."]);
								return;
							}
							if ($userid != $author->id) {
								in_array($userid, $user_array) || $user_array[] = $userid;
								setSettings($global_last_message->d->channel_id, "ignores", $user_array);
								$message->channel->sendMessage("", false, ["color"=>0xffda00,"description"=>"User <@".$userid."> added to ignore list on <#" . $global_last_message->d->channel_id .">"]);
							} else {
								$message->channel->sendMessage("", false, ["color"=>0xffda00,"description"=>"You can't add an ignore on yourself on <#" . $global_last_message->d->channel_id .">!"]);
							}
						} else if ($add_or_del == 'del') {
							if (($key = array_search($userid, $user_array)) !== false) {
								unset($user_array[$key]);
								setSettings($global_last_message->d->channel_id, "ignores", $user_array);
								$message->channel->sendMessage("", false, ["color"=>0xffda00,"description"=>"User <@".$userid."> removed from ignore list on <#" . $global_last_message->d->channel_id .">"]);
							} else {
								$message->channel->sendMessage("", false, ["color"=>0xffda00,"description"=>"User <@".$userid."> does not exist on ignore list on <#" . $global_last_message->d->channel_id .">!"]);
							}
						} else if ($add_or_del == 'list') {
							if (count($user_array)) {
								$descr = "**Ignore list for <#" . $global_last_message->d->channel_id .">**\r\n\r\n";
								foreach ($user_array as $user_id) {
									$descr .= "<@" . $user_id . "> ($user_id)\r\n";
								}
							} else {
								 $descr = "**Ignore list for <#" . $global_last_message->d->channel_id .">** is **empty**!";
							}
							$message->channel->sendMessage("", false, ["color"=>0xffda00,"description"=>$descr]);
						}
					} else {
						if ($add_or_del != 'add' && $add_or_del != 'del' && $add_or_del != 'list') {
							$message->channel->sendMessage("", false, ["color"=>0xff0000, "description"=>"Invalid config ignore command '**$params[3]**', should be '**add**', '**del**' or '**list**'"]);
						} else {
							$message->channel->sendMessage("", false, ["color"=>0xffda00,"description"=>"User to add or delete on <#" . $global_last_message->d->channel_id ."> must be referred to as a metion"]);
						}
					}
				break;
				default:
					$message->channel->sendMessage("", false, ["color"=>0xff0000, "description"=>"Invalid config command '**$params[2]**', should be '**set**', '**show**' or '**ignore**'"]);
				break;
			}
			return;
		}

		if ($author->username != $discord->username && $author->discriminator != $discord->discriminator) {

			/* Learn from all public conversation the bot can see */

			echo "<{$author->username}> $content", PHP_EOL;

			$mentioned = false;
			foreach ($message->mentions as $mention) {

				# Replace mentions with usernames
				$content = preg_replace('/<@'.$mention->id.'>/', $mention->username, $content);
	
				# Determine if we've been mentioned
				if ($mention->username == $discord->username && $mention->discriminator == $discord->discriminator) {
					$mentioned = true;
				}
			}

			$talkative = getSetting($global_last_message->d->channel_id, "talkative");
			$learningdisabled = getSetting($global_last_message->d->channel_id, "learningdisabled");

			if ($talkative) {
				$mentioned = true;
				$content = $discord->username . ' ' . $content;
				if ($content == $discord->username . ' ' . $discord->username . ' status') {
					$content = $discord->username . ' status';
				}
			}

			$found = 0;

			/* When learning is not disabled, bot is always learning */
			if (!$learningdisabled) {
				list($found,$reply) = infobot($author->username, $content, $randomnick);
				$reply = trim(preg_replace('/\r|\n/', '', $reply));
			}

			/* Only respond if directly addressed */
			if ((!isset($author->bot) || $author->bot == false) && $mentioned && $author->username != $discord->username) {

				/* When learning is disabled, bot must be directly addressed to learn a new fact */
				if ($learningdisabled) {
					list($found,$reply) = infobot($author->username, $content, $randomnick);
					$reply = trim(preg_replace('/\r|\n/', '', $reply));
				}


				$reply = preg_replace('/^\001ACTION (.*)\s*\001$/', '*\1*', $reply);
				$reply = preg_replace('/\s\*$/', '*', $reply);
				if ($reply != '*NOTHING*') {
					/* Respond with infobot.pm text from the telnet port */
					echo "<" . $discord->username . "> " . $reply . PHP_EOL;
					if (preg_match('/^Since (.+?), there have been (\d+) modifications and (\d+) questions. I have been alive for (.+?), I currently know (\d+)/', $reply, $matches)) {
						$countdetails = count_guilds($discord);
						$message->channel->sendMessage("", false, [
							"title" => $discord->username . " status",
							"color"=>0xffda00,
							"url"=>"https://www.botnix.org",
							"image"=>["url"=>$config['statsurl'] . "?now=" . time(), "width"=>390, "height"=>195],
							"thumbnail"=>["url"=>"https://www.botnix.org/images/botnix.png"],
							"footer"=>["link"=>"https;//www.botnix.org/", "text"=>"Powered by Botnix 2.0 with the infobot and discord modules", "icon_url"=>"https://www.botnix.org/images/botnix.png"],
							"fields"=>[
								["name"=>"Connected Since", "value"=>$matches[1], "inline"=>false],
								["name"=>"Database changes", "value"=>number_format($matches[2]), "inline"=>false],
								["name"=>"Questions", "value"=>number_format($matches[3]), "inline"=>false],
								["name"=>"Uptime", "value"=>$matches[4], "inline"=>false],
								["name"=>"Number of facts in database", "value"=>number_format($matches[5]), "inline"=>false],
								["name"=>"Total Servers", "value"=>number_format($countdetails['guild_count']), "inline"=>false],
								["name"=>"Total Users", "value"=>number_format($countdetails['member_count']), "inline"=>false],
							],
							"description" => "",
						]);
					} else {

						// All but first url get <> around them to stop discord expanding them with previews
						if (preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $reply, $matches)) {
							for ($i = 1; $i < count($matches[0]); ++$i) {
								$reply = str_replace($matches[0][$i], "<".$matches[0][$i].">", $reply);
							}
						}
						if ($talkative && !$found) {
							return;
						}
						$message->channel->sendMessage($reply);
					}
				} else if (!$talkative) {
					$r = rand(0, 5);
					/* These are here just in case the bot's telnet port is down, so that at least it can say something. */
					switch ($r) {
						case 0:
							$message->channel->sendMessage("Sorry ".$author->username." I don't know what $content is.");
						break;
						case 1:
							$message->channel->sendMessage("$content? No idea " . $author->username);
						break;
						case 2:
							$message->channel->sendMessage("I'm not a genius, " . $author->username . "...");
						break;
						case 3:
							$message->channel->sendMessage("It's best to ask a real person about $content.");
						break;
						case 4:
							$message->channel->sendMessage("Not a clue.");
						break;
						case 5:
							$message->channel->sendMessage("Don't you know, " . $author->username . "?");
						break;
					}
				}
			}
		}

	});
});

$discord->run();

/* Count total guilds and members, luckily we still get this when guild_subscriptions = false! */
function count_guilds($discord) {
	$rv = ["guild_count"=>$discord->guilds->count(), "member_count"=>1];
	foreach ($discord->guilds as $guild) {
		$rv["member_count"] += $guild->member_count;
	}
	return $rv;
}
