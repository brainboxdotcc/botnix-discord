<?php

function getSettings($channelid) {
	global $conn;
	/* We can be sure that channelid is always numeric, this is safe */
	$q = mysqli_query($conn, "SELECT * FROM infobot_discord_settings WHERE id = '" . $channelid . "'");
	$obj = mysqli_fetch_object($q);
	if ($obj) {
		return json_decode($obj->settings, true);
	} else {
		return [];
	}
}

function getSetting($channelid, $field) {
	$settings = getSettings($channelid);
	return (isset($settings[$field]) ? $settings[$field] : null);
}

function setSettings($channelid, $field, $value) {
	global $conn;
	$current = getSettings($channelid);
	if (!$current) {
		$current = [];
	}
	$current[$field] = $value;
	mysqli_query($conn, "REPLACE INTO infobot_discord_settings (id,settings) VALUES($channelid,'" . mysqli_real_escape_string($conn, json_encode($current)) . "')");
}
