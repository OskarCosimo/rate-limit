<?php

/**
 * ratelimit short summary.
 *
 * @version 1.0
 * @author oskar @ myincorporate.org
 *
 * $time_period = Time period is specified in seconds (3600 seconds is 1 hour , 86400 seconds is 1 day)
 * $max_calls_limit = max calls allowed in that timespan
 * $total_user_calls = the toal calls the user has made
 * clients headers:
 * X-RateLimit-Limit: It is for providing max capacity.
 * X-RateLimit-Remaining: It is for clarifiying the remaning limits (tokens).
 * X-RateLimit-Reset: The time at which the rate limit resets, specified in UTC epoch time (in seconds)
 */

	$redis = new Redis();
//connection to redis memory database
	$redis->connect('127.0.0.1', 6379);
//password for redis
	$redis->auth('');
//select the database in memory (can be changed from 0 to max 16)
	$redis->select(0);

//You can edit this part to set custom values
	$max_calls_limit = 500;
	$time_period = 3600;
	$total_user_calls = 0;
//End of the editable part

//It is possible to edit this part to implement the authentication system, just change the $user_code_ratelimit variable with the token used by the authentication system.
//Get the visitor's IP (also if behind proxy)
if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])):
	$_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
	$_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
endif;
	$client = @$_SERVER['HTTP_CLIENT_IP'];
	$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
	$remote = $_SERVER['REMOTE_ADDR'];

if (filter_var($client, FILTER_VALIDATE_IP)):
	$user_code_ratelimit = $client;
elseif (filter_var($forward, FILTER_VALIDATE_IP)):
	$user_code_ratelimit = $forward;
else:
	$user_code_ratelimit = $remote;
endif;
//End of the editable part

//hash the usercode created
$user_code_ratelimit_hashed = hash('sha256', $user_code_ratelimit);

//check the in-memory database and update it if needed
if (!$redis->exists($user_code_ratelimit_hashed)):
	$redis->set($user_code_ratelimit_hashed, 1);
	$redis->expire($user_code_ratelimit_hashed, $time_period);
	$total_user_calls = 1;
else:
	$redis->INCR($user_code_ratelimit_hashed);
	$total_user_calls = $redis->get($user_code_ratelimit_hashed);
	if ($total_user_calls > $max_calls_limit):
		echo "The rate limit is exceeded. Please try again later.";
		exit();
	endif;
endif;

//settings header for the client (important: put this before any output, if this is not possible comment these 3 lines)
header('X-RateLimit-Limit: '.$max_calls_limit);
header('X-RateLimit-Remaining: '.$max_calls_limit-$total_user_calls);
header('X-RateLimit-Reset: '.$time_period);

//testing purpose
//echo "Welcome " . $user_code_ratelimit_hashed . " total calls made " . $total_user_calls . " in " . $time_period . " seconds. Remaining calls: ".$max_calls_limit-$total_user_calls;
