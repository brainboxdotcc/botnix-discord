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

/* Live config should be in the directory above this one. It's like this to make sure you never accidentally commit it to a repository. */
$config = parse_ini_file("../botnix-discord.ini");

/* Connect to the bot via its telnet port over localhost for remote control */
function sporks($user, $content)
{
	global $config;
	if ($content == "Sporks help") {
		/* The only hard coded singular response... */
		return "What? Are you struggling with me? For help with this bot, you should probably visit the brainbox.cc discord, here: https://discord.gg/brainbox";
	}
	/* NO, you can't configure any host other than localhost, as this connection is plaintext! */
	$fp = fsockopen('localhost', $config['telnetport'], $errno, $errstr, 30);
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
		fwrite($fp, ".DR $user $content\n");
		$response = fgets($fp);
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

		// Grab from global, because discordphp strips it out!
		$author = $global_last_message->d->author;

		$content = preg_replace('/<@'.$discord->id.'>/', $discord->username, $message->content);

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

			$reply = sporks($author->username, $content);
			$reply = trim(preg_replace('/\r|\n/', '', $reply));

			/* Only respond if directly addressed */
			if ((!isset($author->bot) || $author->ibot == false) && $mentioned && $author->username != $discord->username) {
				$reply = preg_replace('/^\001ACTION (.*)\s*\001$/', '*\1*', $reply);
				$reply = preg_replace('/\s\*$/', '*', $reply);
				if ($reply != '*NOTHING*') {
					/* Respond with infobot.pm text from the telnet port */
					echo "<" . $discord->username . "> " . $reply . PHP_EOL;
					$message->channel->sendMessage($reply);
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
