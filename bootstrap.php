<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

if(isset(getopt("", ["has-parent"])["has-parent"])){ //recursive call inside phar
	require __DIR__ . "/src/pocketmine/PocketMine.php";
	exit(); //this shouldn't be reached, but just in case...
}

require __DIR__ . "/vendor/autoload.php";

$args = $argv;
array_shift($args); //remove this file's name from args

$file = \Phar::running(false);
if($file === ""){
	$file = __DIR__ . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "pocketmine" . DIRECTORY_SEPARATOR . "PocketMine.php";

	echo "--- Syncing composer dependencies ---" . PHP_EOL;
	passthru("bin" . DIRECTORY_SEPARATOR . "composer install --prefer-source", $code);
	if($code !== 0) die($code);
	echo "--- Syncing git submodules ---" . PHP_EOL;

	$vt100 = function_exists('sapi_windows_vt100_support') ? sapi_windows_vt100_support(STDOUT) : false;
	passthru("git submodule update --init --recursive", $code);
	if(function_exists('sapi_windows_vt100_support')) sapi_windows_vt100_support(STDOUT, $vt100); //git likes to mess with this and break console colours

	if($code !== 0) die($code);
	echo "--- Booting server ---" . PHP_EOL;
}else{
	$args[] = "--has-parent"; //recursion guard for phars
}

if($v = \pocketmine\utils\Terminal::hasFormattingCodes()){
	//force colours to be enabled if we support them up here, since the child process will think that its stdout is piped
	$args[] = "--enable-ansi";
}

array_walk($args, function(string &$arg, $_){
	$arg = escapeshellarg($arg);
});

function run_child(string $file, array $args) : void{
	passthru($l = sprintf(
		"\"%s\" \"%s\" %s",
		PHP_BINARY,
		$file,
		implode(" ", $args)
	));
}

run_child($file, $args);

$loop = isset(getopt("l")["l"]);
while($loop){
	echo "--- To exit the loop, press q then ENTER. Otherwise, wait 5 seconds for the server to restart ---" . PHP_EOL;
	$read = [STDIN];
	if(stream_select($read, $write, $except, 5) === 1){
		$cmd = trim(fgets(STDIN));
		if($cmd === "q"){
			echo "--- Exiting ---" . PHP_EOL;
			break;
		}
	}
	sleep(5);
	echo PHP_EOL;
	run_child($file, $args);
}
