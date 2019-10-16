<?php
/* Connect to the bot via its telnet port over localhost for remote control */
function infobot($user, $content, $randomnick = "")
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
                $user = preg_replace("/\s+/", "_", $user);
                fwrite($fp, ".DR $user $content\n");
                $response = fgets($fp);
                $netdata = fgets($fp);
                if (preg_match("/nick => '(.+?)'/", $netdata, $match)) {
                        if ($match[1] != "") {
                                $ircnick = $match[1];
                        }
                }
                fclose($fp);
                list($found, $response) = explode(' ', $response, 2);
                return [$found, str_replace("_", " ", $response)];
        }
}
