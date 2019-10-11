<?php

/* Botnix to discord connector for infobot.pm botnix module.
 * Connects to discord as a bot via the API and talks directly to the discord
 * module on a running instance of botnix on the same machine. If there is no instance
 * of botnix to connect to, the bot just answers with non-commital responses.
 *
 * Note that this does not fork as a demon and should be run via 'screen'.
 *
 * - Brain#0001 Oct 2019.
 */

include __DIR__.'/vendor/autoload.php';

use Discord\Parts\User\Game;

$last_update_status = time() - 600;
$ircnick = "";

/* Live config should be in the directory above this one. It's like this to make sure you never accidentally commit it to a repository. */
$config = parse_ini_file("../botnix-discord.ini");

/* Connect to the bot via its telnet port over localhost for remote control */
function sporks($user, $content, $randomnick = "")
{
	global $config;
	global $ircnick;
	/* NO, you can't configure any host other than localhost, as this connection is plaintext! */
	$fp = fsockopen('localhost', $config['telnetport'], $errno, $errstr, 1);
	if (!$fp) {
		print $errno . ": " . $errstr . "\n";
		return "*NOTHING*";
	} else {
		/* Note: This uses the .DR command specific to the infobot.pm module. We only want infobot available on discord,
		 * all the IRC admin/moderation stuff is useless on discord and may introduce problems.
		 */
		$userprompt = fgets($fp);
		fwrite($fp, $config['telnetuser']."\n");
		$passprompt = fgets($fp);
		fwrite($fp, $config['telnetpass']."\n");
		$welcomeprompt = fgets($fp);
		if ($randomnick != "") {
			fwrite($fp, ".RN $randomnick\n");
			$response = fgets($fp);
		}
		fwrite($fp, ".DR $user $content\n");
		$response = fgets($fp);
		$netdata = fgets($fp);
		if (preg_match("/nick => '(.+?)'/", $netdata, $match)) {
			if ($match[1] != "") {
				$ircnick = $match[1];
				print "IRC NICK: $ircnick\n";
			}
		}
		fclose($fp);
		return $response;
	}
}

/* Connect to discord */
$discord = new \Discord\Discord([
	'token' => $config['token'],
]);

$discord->on('ready', function ($discord) {
	echo "Bot '$discord->username#$discord->discriminator' ($discord->id) is ready.", PHP_EOL;

	$discord->on('message', function ($message) {
		global $discord;
		global $global_last_message;
		global $last_update_status;
		global $ircnick;

		// Grab from global, because discordphp strips it out!
		$author = $global_last_message->d->author;

		if ($ircnick == "") {
			$ircnick = $discord->username;
		}

		if (time() - $last_update_status > 120) {
			$last_update_status = time();
			echo "Running presence update\n";
			$info = sporks("Self", $ircnick . " status");
			echo "Presence update with: '$info'\n";
			preg_match('/^Since (.+?), there have been (\d+) modifications and (\d+) questions. I have been alive for (.+?), I currently know (\d+)/', $info, $matches);
			$game = $discord->factory(Game::class, [
				'name' => number_format($matches[5]) . " facts",
				'url' => 'https://www.botnix.org/',
				'type' => 3,
			]);
			$discord->updatePresence($game);
		}

		$users = [];
		foreach ($message->channel->guild->members as $member) {
			$users[] = $member;
		}
		$usercount = count($users);
		$random = rand(0, $usercount - 1);
		$randomuser = array_slice($users, $random, 1);
		$randomnick = $randomuser[0]->user->username;

		# Replace mention of bot with nickname, and strip newlines
		$content = preg_replace('/<@'.$discord->id.'>/', $discord->username, $message->content);
		$content = trim(preg_replace('/\r|\n/', ' ', $content));

		if ($content == $discord->username . " help") {
			$trigger = "@".$discord->username;
			echo "Responding to help on channel\n";
			$message->channel->sendMessage("", false, [
				"title" => $discord->username . " help",
				"color"=>0xffda00,
				"url"=>"https://www.botnix.org",
				"thumbnail"=>["url"=>"https://www.botnix.org/images/botnix.png"],
				"footer"=>["link"=>"https;//www.botnix.org/", "text"=>"Powered by Botnix 2.0 with the infobot and discord modules"],
				"fields"=>[
					[
						"name"=>"Teaching " . $discord->username,
						"value"=>"
							Any declaritive statement will teach the bot. For example someone saying ```twitch is down again``` will teach the bot this response, asking later ```$trigger twitch``` will make the bot respond
							```I heard twitch is down again```
						",
						"inline"=>false,
					],
					[
						"name"=>"Giving ".$discord->username." amnesia",
						"value"=>"
							You can make the bot forget a phrase with
							```$trigger forget <keyword>```
							If the bot already knows a fact, you will usually have to tell it to forget the fact, before it will accept a new one.
						",
						"inline"=>false,
					],
                                        [
                                                "name"=>"Other commands",
                                                "value"=>"You can ask the bot where he learned a phrase by asking:
```$trigger, who told you about <keyword>?```
A status report can be obtained by asking the bot:
```$trigger status```
Note that the bot will only talk on channels, and not in private message, and will only respond when mentioned, although it will silently learn all it observes.",
                                                "inline"=>false,
                                        ],
					[
						"name"=>"Advanced commands",
						"value"=>"Further advanced commands are available, for info on them type ```$trigger help advanced```",
						"inline"=>false,
					],
				],
				"description" => "",
			]);
			return;
		}
		if ($content == $discord->username . " help advanced") {
			$trigger = "@".$discord->username;
			echo "Responding to help (advanced) on channel\n";
			$message->channel->sendMessage("", false, [
				"title" => $discord->username . " advanced help",
				"color"=>0xffda00,
				"url"=>"https://www.botnix.org",
				"thumbnail"=>["url"=>"https://www.botnix.org/images/botnix.png"],
				"footer"=>["link"=>"https;//www.botnix.org/", "text"=>"Powered by Botnix 2.0 with the infobot and discord modules"],
			"fields"=>[
                                        [
                                                "name"=>"Literal responses",
                                                "value"=>"
More advanced commands are available, such as if you want the bot to literally say some text, rather than reformatting it, you can for example type:
```$trigger, twitch is <reply> twitch is a streaming service.```
If you want the bot to tell you what is literally defined in the database for a fact you can type
```$trigger literal <keyword>```",
                                                "inline"=>false,
                                        ],			
					[
						"name"=>"Variables",
						"value"=>"You can use special keywords, which will be replaced:
``<who>`` the nickname (not as a mention) of the user talking to the bot.
``<me>`` the bot's current nickname.
``<random>`` the nickname (not as a mention) of a *random* user on the current discord server.
``<date>`` the date the bot learned the fact it is responding with
``<now>`` the current date and time.",
						"inline"=>false,
					],
					[
						"name"=>"Randomised Responses",
						"value"=>"You can separate multiple responses with a pipe symbol ``|`` and the bot will pick one at random when responding. for example:
```$trigger roll a dice is <reply>one|<reply>two|<reply>three|<reply>four|<reply>five|<reply>six```",
						"inline"=>false,
					],

				],
				"description" => "",
			]);
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

			$reply = sporks($author->username, $content, $randomnick);
			$reply = trim(preg_replace('/\r|\n/', '', $reply));

			/* Only respond if directly addressed */
			if ((!isset($author->bot) || $author->bot == false) && $mentioned && $author->username != $discord->username) {
				$reply = preg_replace('/^\001ACTION (.*)\s*\001$/', '*\1*', $reply);
				$reply = preg_replace('/\s\*$/', '*', $reply);
				if ($reply != '*NOTHING*') {
					/* Respond with infobot.pm text from the telnet port */
					echo "<" . $discord->username . "> " . $reply . PHP_EOL;
					if (preg_match('/^Since (.+?), there have been (\d+) modifications and (\d+) questions. I have been alive for (.+?), I currently know (\d+)/', $reply, $matches)) {
						$message->channel->sendMessage("", false, [
							"title" => $discord->username . " status",
							"color"=>0xffda00,
							"url"=>"https://www.botnix.org",
							"thumbnail"=>["url"=>"https://www.botnix.org/images/botnix.png"],
							"footer"=>["link"=>"https;//www.botnix.org/", "text"=>"Powered by Botnix 2.0 with the infobot and discord modules"],
							"fields"=>[
								["name"=>"Connected Since", "value"=>$matches[1], "inline"=>false],
								["name"=>"Database changes", "value"=>number_format($matches[2]), "inline"=>false],
								["name"=>"Questions", "value"=>number_format($matches[3]), "inline"=>false],
								["name"=>"Uptime", "value"=>$matches[4], "inline"=>false],
								["name"=>"Number of facts in database", "value"=>number_format($matches[5]), "inline"=>false],
							],
							"description" => "",
						]);
					} else {
						$message->channel->sendMessage($reply);
					}
				} else {
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
