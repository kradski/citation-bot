<?
// $Id$

session_start();
ini_set ("user_agent", "Citation_bot; verisimilus@toolserver.org");

function includeIfNew($file){
	// include missing files
	$alreadyIn = get_included_files();
	foreach ($alreadyIn as $include){
		if (strstr($include, $file)) return false;
	}
	if (true || $GLOBALS["linkto2"]) echo "\n// including $file";
	require_once($file . $GLOBALS["linkto2"] . ".php");
	return true;
}

echo " \n Getting login details ... ";
require_once("/home/verisimilus/public_html/Bot/DOI_bot/doiBot$linkto2.login");
echo " done.";
# Snoopy should be set so the host name is en.wikipedia.org.
includeIfNew('Snoopy.class');
includeIfNew("wikiFunctions");
includeIfNew("DOItools");
echo "\n Connecting to MYSQL database ... ";
require_once("/home/verisimilus/public_html/res/mysql_connect.php");
$db = udbconnect("yarrow");
echo " connected.";
if(!true && !myIP()) {
	print "Sorry, the Citation bot is temporarily unavilable while bugs are fixed.  Please try back later."; exit;
}

echo "\n Initializing ... ... ";
#Yahoo Application ID
$yAppId = "wLWQRfDV34GGTxHoNZjroF_m94yRvVD_eGRA9KKFhPZsE4rAXNGOih3eCrI9Eh3ewBa6Ccqg";

//Google AppId
#$gAppId = "ABQIAAAAsqKZCEjzSKO3mjAh0efRehT5mbzX3Oi5P88WWtRyN9u9YXZnqRT56kmFtGXDpeNI_FTpsOOoAuCoFA";
# Above ID is for /~ms609; below is for /Wiki/Bot
$gAppId = "ABQIAAAAsqKZCEjzSKO3mjAh0efRehQrFKyE8YGyge8HxpDYaz1oDCwgkBTqu-eqTpVxlupEyuIYijuXU6B-aw";
$crossRefId=CROSSREFUSERNAME.":".CROSSREFPASSWORD;
$isbnKey = "268OHQMW";
$isbnKey2 = "268OHQMW";
$bot = new Snoopy();

mb_internal_encoding( 'UTF-8' ); // Avoid ??s

define("debugon", $_GET["debug"]);
define("restrictedDuties", !true);
define("editinterval", 10);
define("pipePlaceholder", "doi_bot_pipe_placeholder"); #4 when online...
define("wikiroot", "http://en.wikipedia.org/w/index.php?");
//define("doiRegexp", "(10\.\d{4}/([^\s;\"\?&<])*)(?=[\s;\"\?&]|</)");
#define("doiRegexp", "(10\.\d{4}(/|%2F)[^\s\"\?&]*)(?=[\s\"\?&]|</)"); //Note: if a DO I is superceded by a </span>, it will pick up this tag. Workaround: Replace </ with \s</ in string to search.

//Common replacements
$doiIn = array("[", 			"]", 			"<", 			">"	,			"&#60;!", 	"-&#62;",		"%2F"	);
$doiOut = array("&#x5B;", "&#x5D;", "&#60;",  "&#62;", 	"<!",  			"->", 			"/"	);

$pcDecode = array("[", 			"]", 			"<", 			">");
$pcEncode = array("&#x5B;", "&#x5D;", "&#60;",  "&#62;");

$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
$dotDecode = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

//Optimisation
#ob_start(); //Faster, but output is saved until page finshed.
ini_set("memory_limit","256M");

$searchGoogle=$_REQUEST["google"];
$searchYahoo=$_REQUEST["yahoo"];
$searchDepth=$_REQUEST["depth"];
$fastMode=$_REQUEST["fast"];
$slowMode=$_REQUEST["slow"];
//$user = $_REQUEST["user"];
$bugFix = $_REQUEST["bugfix"];
$crossRefOnly = $_REQUEST["crossrefonly"]?true:$_REQUEST["turbo"];

if ($_REQUEST["edit"] || $_GET["doi"] || $_GET["pmid"]) $ON = true;

$editSummaryStart = ($bugFix?"Double-checking that a [[User:DOI_bot/bugs|bug]] has been fixed. ":"Citation maintenance. ");
$editSummaryEnd = (isset($user)?" Initiated by [[User:$user|$user]].":"")
						.	" You can [[WP:UCB|use this bot]] yourself! Please [[User:Citation_bot/bugs|report any bugs]].";

ob_flush();


################ Functions ##############

function updateBacklog($page) {
  $sPage = addslashes($page);
	$id = articleId($page);
  $db = udbconnect("yarrow");
  $result = mysql_query("SELECT page FROM citation WHERE id = '$id'") or die (mysql_error());
	$result = mysql_fetch_row($result);
	$sql = $result?"UPDATE citation SET fast = '" . date ("c") . "', revision = '" . revisionID()
                . "' WHERE page = '$sPage'"
                : "INSERT INTO citation VALUES ('"
                . $id . "', '$sPage', '" . date ("c") . "', '0000-00-00', '" . revisionID() ."')";
	#print "\n$sql";
	$result = mysql_query ($sql) or die(mysql_error());
}

function countMainLinks($title) {
	// Counts the links to the mainpage
	global $bot;
	if(preg_match("/\w*:(.*)/", $title, $title)) $title = $title[1]; //Gets {{PAGENAME}}
	$url = "http://en.wikipedia.org/w/api.php?action=query&bltitle=" . urlencode($title) . "&list=backlinks&bllimit=500&format=yaml";
	$bot->fetch($url);
	$page = $bot->results;
	if (preg_match("~\n\s*blcontinue~", $page)) return 501;
	preg_match_all("~\n\s*pageid:~", $page, $matches);
	return count($matches[0]);
}


// This function is called from the end of this page.
function logIn($username, $password) {
    global $bot; // Snoopy class loaded elsewhere

  // Set POST variables to retrieve a token
	$submit_vars["format"] = "json";
	$submit_vars["action"] = "login";
	$submit_vars["lgname"] = $username;
	$submit_vars["lgpassword"] = $password;
	// Submit POST variables and retrieve a token
  $bot->submit(api, $submit_vars);
  $first_response = json_decode($bot->results);
  $submit_vars["lgtoken"] = $first_response->login->token;
  // Store cookies; resubmit with new request (which hast token added to post vars)
  foreach ($bot->headers as $header) {
    if (substr($header, 0,10) == "Set-Cookie") {
      $cookies = explode(";", substr($header, 12));
      foreach ($cookies as $oCook) {
        $cookie = explode("=", $oCook);
        $bot->cookies[trim($cookie[0])] = $cookie[1];
      }
    }
  }

  $bot->submit(api, $submit_vars);
  $login_result = json_decode($bot->results);
	if ($login_result->login->result == "Success") {
    print "\n Using account " . $login_result->login->lgusername;
    // Add other cookies, which are necessary to remain logged in.
    $cookie_prefix = "enwiki";
    $bot->cookies[$cookie_prefix . "UserName"] = $login_result->login->lgusername;
    $bot->cookies[$cookie_prefix . "UserID"] = $login_result->login->lguserid;
    $bot->cookies[$cookie_prefix . "Token"] = $login_result->login->lgtoken;
    return true;
  } else {
    die( "\nCould not log in to Wikipedia servers.  Edits will not be committed.\n"); // Will not display to user
    global $ON; $ON = false;
    return false;
  }
}

function inputValue($tag, $form) {
	//Gets the value of an input, if the input's in the right format.
	preg_match("~value=\"([^\"]*)\" name=\"$tag\"~", $form, $name);
	if ($name) return $name[1];
	preg_match("~name=\"$tag\" value=\"([^\"]*)\"~", $form, $name);
	if ($name) return $name[1];
	return false;
}


function write($page, $data, $edit_summary = "Bot edit") {

	global $bot;

  // Check that bot is logged in:
  $bot->fetch(api . "?action=query&prop=info&meta=userinfo&format=json");
  $result = json_decode($bot->results);

  if ($result->query->userinfo->id == 0) {
    return "LOGGED OUT:  The bot has been logged out from Wikipedia servers";
  }

  $bot->fetch(api . "?action=query&prop=info&format=json&intoken=edit&titles=" . urlencode($page));
  $result = json_decode($bot->results);

  foreach ($result->query->pages as $i_page) {
    $my_page = $i_page;
  }

	$submit_vars = array (
    "action"    => "edit",
    "title"     => $my_page->title,
    "text"      => $data,
    "token"     => $my_page->edittoken,
    "summary"   => $edit_summary,
    "minor"     => "1",
    "bot"       => "1",
    #"basetimestamp" => $my_page->touched,
    #"starttimestamp" => $my_page->starttimestamp,
    "md5"       => md5($data),
    "watchlist" => "nochange",
    "format"    => "json",
  );

	$bot->submit(api, $submit_vars);
  $result = json_decode($bot->results);
  if ($result->edit->result == "Success") {
    // Need to check for this string whereever our behaviour is dependant on the success or failure of the write operation
    return "Success";
  } else if ($result->edit->result) {
    return $result->edit->result;
  } else if ($result->error->code) {
    // Return error code
    return strtoupper($result->error->code) . ": " . str_replace(array("You ", " have "), array("This bot ", " has "), $result->error->info);
  } else {
    return "Unhandled error.  Please copy this output and <a href=http://code.google.com/p/citation-bot/issues/list>report a bug.</a>";
  }
}

function noteDoi($doi, $src){
	echo "<h3 style='color:coral;'>Found <a href='http://dx.doi.org/$doi'>DOI</a> $doi from $src.</h3>";
}

function isDoiBroken ($doi, $p = false){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 1);
	curl_setopt($ch, CURLOPT_URL, "http://dx.doi.org/$doi");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  //This means we can get stuck.
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);  //This means we can't get stuck.
	curl_setopt($ch, CURLOPT_TIMEOUT, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
	$result = curl_exec($ch);
	curl_close($ch);
	preg_match("~\d{3}~", $result, $code);
	switch ($code[0]){
		case false:
			$parsed = parse_url("http://dx.doi.org/$doi");
			$host = $parsed["host"];
			$fp = @fsockopen($host, 80, $errno, $errstr, 20);
			if ($fp) return false; // Page exists, but had timed out when we first tried.
			logBrokenDoi($doi, $p, 404);
			return 404; // DOI is correct but points to a dead page
		case 200:
			if ($p["url"][0]) {
				$ch = curl_init();
				curlSetup($ch, $p["url"][0]);
				$content = curl_exec($ch);
				if (!preg_match("~\Wwiki(\W|pedia)~", $content)	&& preg_match("~" . preg_quote(urlencode($doi)) . "~", urlencode($content))) {
					logBrokenDoi($doi, $p, 200);
					return 200; // DOI is present in page, so probably correct
				} else return 999; // DOI could not be found in URL - or URL is a wiki mirror
			}	else return 100; // No URL to check for DOI
	}
	return false;
}

function logBrokenDoi($doi, $p, $error){
	$file = "brokenDois.xml";
	if (file_exists($file)) $xml = simplexml_load_file($file);
	else $xml = new SimpleXMLElement("<errors></errors>");
	$oDoi = $xml->addChild("doi", $doi);
	$oDoi->addAttribute("error_code", $error);
	$oDoi->addAttribute("error_found", date("Y-m-d"));
	unset($p["doi"], $p["unused_data"], $p["accessdate"]);
	foreach ($p as $key => $value) $oDoi->addAttribute($key, $value[0]);
	$xml->asXML($file);
	chmod($file, 0644);
}
// Error codes:
// 404 is a working DOI pointing to a page not found;
// 200 is a broken DOI, found in the source of the URL
// Broken DOIs are only logged if they can be spotted in the URL page specified.

echo "\n Establishing connection to Wikipedia servers ... ";
// Log in to Wikipedia
logIn(USERNAME, PASSWORD);

echo "\n Fetching parameter list ... ";
// Get a current list of parameters used in citations from WP
$page = $bot->fetch(api . "?action=query&prop=revisions&rvprop=content&titles=User:Citation_bot/parameters&format=json");
$json = json_decode($bot->results, true);
$parameter_list = (explode("\n", $json["query"]["pages"][26899494]["revisions"][0]["*"]));
function ascii_sort($val_1, $val_2)
{
  $return = 0;
  $len_1 = strlen($val_1);
  $len_2 = strlen($val_2);

  if ($len_1 > $len_2)
  {
    $return = -1;
  }
  else if ($len_1 < $len_2)
  {
    $return = 1;
  }
  return $return;
}
print "sorting ... ";
uasort($parameter_list, "ascii_sort");
print "done.";
?>