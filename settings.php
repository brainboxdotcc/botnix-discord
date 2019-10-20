<?php

function getSettings($channelid, $guildid, $name = "", $parent_id = null) {
	global $conn;
	/* We can be sure that channelid is always numeric, this is safe */
	$q = mysqli_query($conn, "SELECT settings FROM infobot_discord_settings WHERE id = '" . $channelid . "'");
	$obj = mysqli_fetch_object($q);
	if ($parent_id == null) {
		$parent_id = "NULL";
	}
	if ($obj) {
		if ($name != "") {
			mysqli_query($conn, "UPDATE infobot_discord_settings SET name = '".mysqli_real_escape_string($conn, $name)."', parent_id = $parent_id WHERE id = $channelid");
		}
		return json_decode($obj->settings, true);
	} else {
		mysqli_query($conn, "INSERT INTO infobot_discord_settings (id,guild_id,settings) VALUES($channelid,$guildid,'{}')");
		if ($name != "") {
			mysqli_query($conn, "UPDATE infobot_discord_settings SET name = '".mysqli_real_escape_string($conn, $name)."', parent_id = $parent_id WHERE id = $channelid");
		}
		return [];
	}
}

function getSetting($channelid, $guildid, $field) {
	$settings = getSettings($channelid, $guildid);
	return (isset($settings[$field]) ? $settings[$field] : null);
}

function setSettings($channelid, $guildid, $field, $value) {
	global $conn;
	$current = getSettings($channelid,$guildid);
	if (!$current) {
		$current = [];
	}
	$current[$field] = $value;
	mysqli_query($conn, "REPLACE INTO infobot_discord_settings (id,guild_id,settings) VALUES($channelid,$guildid,'" . mysqli_real_escape_string($conn, json_encode($current)) . "')");
}
