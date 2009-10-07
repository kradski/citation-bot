<?php
// $Id: $


function loadParam($param, $value, $equals, $pipe) {
  global $p;
  $param = strtolower($param);
  if (is($param)) {
    if (substr($param, strlen($param)-1) > 0 && trim($value) != trim($p[$param][0])) {
      // Add one to last1 to create last2
      $param = substr($param, 0, strlen($param)-1) . (substr($param, strlen($param)-1) + 1);
    } else {
      // Parameter already exists
      $param = null;
    }
  }
  if ($param) {
    $p[$param] = Array($value, $equals, $pipe);
  }
}

while ($page) {
	$startPage = time();
	echo $htmlOutput?("\n<hr>[" . date("H:i:s", $startPage) . "] Processing page '<a href='http://en.wikipedia.org/wiki/$page' style='text-weight:bold;'>$page</a>' &mdash; <a href='http://en.wikipedia.org/?title=". urlencode($page)."&action=edit' style='text-weight:bold;'>edit</a>&mdash;<a href='http://en.wikipedia.org/?title=".urlencode($page)."&action=history' style='text-weight:bold;'>history</a> <script type='text/javascript'>document.title=\"Citation bot: '" . str_replace("+", " ", urlencode($page)) ."'\";</script>"):("\n\n\n*** Processing page '$page' : " . date("H:i:s", $startPage));

	$bot->fetch(wikiroot . "title=" . urlencode($page) . "&action=raw");
	$startcode = $bot->results;
	if ($citedoi && !$startcode) $startcode = $freshcode;

	// Which template family is dominant?

	preg_match_all("~\{\{\s*[Cc]ite[ _](\w+)~", $startcode, $cite_x);
	preg_match_all("~\{\{\s*[Cc]itation\b(?! \w)~", $startcode, $citation);
	if (count($cite_x[0]) * count($citation[0]) >0) {
		// Two types are present
		$changeCitationFormat = true;
		$useCitationFormat = (count($cite_x[0]) < count($citation[0]));
		print ($useCitationFormat)?"\n\n\n\nUSING CITATION FORMAT\n\n\n\n":"\n\n\n\nUSING CITE XXX FORMAT\n\n\n\n";
		print count($cite_x[0]) . " cite / citation " . count($citation[0]);
		sleep(2);
	} else {
		$changeCitationFormat = false;
		$useCitationFormat = false;
	}

	if (preg_match("/\{\{nobots\}\}|\{\{bots\s*\|\s*deny\s*=[^}]*(Citation[ _]bot|all)[^}]*\}\}|\{\{bots\s*\|\s*allow=none\}\}/i", $startcode, $denyMsg)) {
		echo "**** Bot forbidden by bots / nobots tag: $denyMsg[0]";
		$page = nextPage();
	} else {
		$pagecode = preg_replace("~(\{\{cit(e[ _]book|ation)[^\}]*)\}\}\s*\{\{\s*isbn[\s\|]+[^\}]*([\d\-]{10,})[\s\|\}]+[^\}]?\}\}?~i", "$1|isbn=$3}}",
				preg_replace("~(\{\{cit(e[ _]journal|ation)[^\}]*)\}\}\s*\{\{\s*doi[\s\|]+[^\}]*(10\.\d{4}/[^\|\s\}]+)[\s\|\}]+[^\}]?\}\}?~i", "$1|doi=$3}}",
        preg_replace
										("~(?<!\?&)\bid(\s*=\s*)(DOI\s*(\d*)|\{\{DOI\s*\|\s*(\S*)\s*\}\})([\s\|\}])~Ui","doi$1$4$3$5",
				preg_replace("~(id\s*=\s*)\[{2}?(PMID[:\]\s]*(\d*)|\{\{PMID[:\]\s]*\|\s*(\d*)\s*\}\})~","pm$1$4$3",
				preg_replace("~[^\?&]\bid(\s*=\s*)DOI[\s:]*(\d[^\s\}\|]*)~i","doi$1$2",

				preg_replace("~url(\s*)=(\s*)http://dx.doi.org/~", "doi$1=$2", $startcode))))));

     if (mb_ereg("p(p|ages)([\t ]*=[\t ]*[0-9A-Z]+)[\t ]*(-|\&mdash;|\xe2\x80\x94|\?\?\?)[\t ]*([0-9A-Z])", $pagecode)) {
       $pagecode = mb_ereg_replace("p(p|ages)([\t ]*=[\t ]*[0-9A-Z]+)[\t ]*(-|\&mdash;|\xe2\x80\x94|\?\?\?)[\t ]*([0-9A-Z])", "p\\1\\2\xe2\x80\x93\\4", $pagecode);
       $changedDashes = true;
       print "Converted dashes in all page parameters to en-dashes.\n";
     }

	//Search for any duplicate refs with names
	if (false && preg_match_all("~<[\n ]*ref[^>]*name=(\"[^\"><]+\"|'[^']+|[^ ><]+)[^/>]*>(([\s\S](?!<)|[\s\S]<(?!ref))*?)</ref[\s\n]*>~", $pagecode, $refs)) {
		dbg($refs);#############
		$countRefs = count($refs[0]);
		for ($cit_i = 0; $cit_i < $countRefs; $cit_i++) {
			$refs[2][$cit_i] = trim($refs[2][$cit_i]);
			for ($j=0; $j<$cit_i; $j++){
				$refs[2][$j] = trim($refs[2][$j]);
				if (
					strlen($refs[2][$j]) / strlen($refs[2][$cit_i]) > 0.9
					&& strlen($refs[2][$j]) / strlen($refs[2][$cit_i]) <1.1
					&& similar_text($refs[2][$cit_i], $refs[2][$j]) / strlen($refs[2][$cit_i]) >= 1  # We can lower this if we can avoid hitting "Volume II/III" and "page 30/45"
					&& ( similar_text($refs[2][$cit_i], $refs[2][$j]) / strlen($refs[2][$cit_i]) == 1
						|| similar_text($refs[2][$cit_i], $refs[2][$j]) > 52) //Avoid comparing strings that are too short; e.g. "ibid p20"
					) {if ($_GET["DEBUG"]) dbg(array(
					" i & j " => "$cit_i & $j",
					"J" => $refs[2][$j],
					"Jlen" => strlen($refs[2][$j]),
					"I" => $refs[2][$cit_i],
					"Ilen" => strlen($refs[2][$cit_i]),
					"SimTxt" => similar_text($refs[2][$j],$refs[2][$cit_i]) . " = " . similar_text($refs[2][$cit_i], $refs[2][$j]) / strlen($refs[2][$cit_i])
					));
						$duplicateRefs[$refs[0][$cit_i]] = $refs[1][$j]; // Full text to be replaced, and name to replace it by
					}
			}
		}
		foreach ($duplicateRefs as $text => $name){
			$pagecode = preg_replace("~^([\s\S]*)" . preg_quote("<ref name=$name/>") . "~", "$1" . $text,
									preg_replace("~" . preg_quote($text) . "~", "<ref name=$name/>", $pagecode));
		}
	}

###################################  START ASSESSING BOOKS ######################################

		if ($citation = preg_split("~{{((\s*[Cc]ite[_ ]?[bB]ook(?=\s*\|))([^{}]|{{.*}})*)([\n\s]*)}}~U", $pagecode, -1, PREG_SPLIT_DELIM_CAPTURE)) {
			$pagecode = null;
			$iLimit = (count($citation)-1);

			for ($cit_i=0; $cit_i<$iLimit; $cit_i+=5){//Number of brackets in cite book regexp +1
				$starttime = time();

				// Remove any comments so they don't confuse any regexps.
				if (preg_match_all("~<!--[\s\S]+-->~U", $citation[$cit_i+1], $comments)) {
					$countComments = count($comments[0]);
					for ($j = 0; $j < $countComments; $j++) {
						$citation[$cit_i+1] = str_replace($comments[0][$j]
																			, "<!-- Citation bot : comment placeholder b$j -->"
																			, $citation[$cit_i+1]);
					}
				} else $countComments = null;
				// Comments have been replaced by placeholders; we'll restore them later.

				// Replace ids with appropriately formatted parameters
				$c = preg_replace("~\bid(\s*=\s*)(isbn\s*)?(\d[\-\d ]{9,})~i","isbn$1$3",
					preg_replace("~(isbn\s*=\s*)isbn\s?=?\s?(\d\d)~i","$1$2",
					preg_replace("~(?<![\?&]id=)isbn\s?:(\s?)(\d\d)~i","isbn$1=$1$2", $citation[$cit_i+1]))); // Replaces isbn: with isbn =
				#$noComC = preg_replace("~<!--[\s\S]*-->~U", "", $c);
				while (preg_match("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", $c)) {
          $c = preg_replace("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", "$1" . pipePlaceholder, $c);
        }
				preg_match(siciRegExp, urldecode($c), $sici);

				// Split citation into parameters
				$parts = preg_split("~([\n\s]*\|[\n\s]*)([\w\d-_]*)(\s*= *)~", $c, -1, PREG_SPLIT_DELIM_CAPTURE);
				$partsLimit = count($parts);
				if (strpos($parts[0], "|") >0 && strpos($parts[0],"[[") === FALSE && strpos($parts[0], "{{") === FALSE) set("unused_data", substr($parts[0], strpos($parts[0], "|")+1));
				for ($partsI=1; $partsI<=$partsLimit; $partsI+=4) {
					$value = $parts[$partsI+3];
					$pipePos = strpos($value, "|");
					if ($pipePos > 0 && strpos($value, "[[") === false & strpos($value, "{{") === FALSE) {
						// There are two "parameters" on one line.  One must be missing an equals.
						$p["unused_data"][0] .= " " . substr($value, $pipePos);
						$value = substr($value, 0, $pipePos);
					}
					// Load each line into $p[param][0123]
					$p[strtolower($parts[$partsI+1])] = Array($value, $parts[$partsI], $parts[$partsI+2]); // Param = value, pipe, equals
				}

				//Make a note of how things started so we can give an intelligent edit summary
				foreach($p as $param=>$value)	if (is($param)) $pStart[$param] = $value[0];


        //Check for the doi-inline template in the title
        if (preg_match("~\{\{\s*doi-inline\s*\|\s*(10\.\d{4}/[^\|]+)\s*\|\s*([^}]+)}}~",
                        str_replace('doi_bot_pipe_placeholder', "|", $p['title'][0]), $match)) {
          set('title', $match[2]);
          set('doi', $match[1]);
        }
        
				useUnusedData();

				if (trim(str_replace("|", "", $p["unused_data"][0])) == "") unset($p["unused_data"]);
				else {
					if (substr(trim($p["unused_data"][0]), 0, 1) == "|") $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);
				}
				echo "\n* {$p["title"][0]}";

				// Fix typos in parameter names

				if (is("edition")) $p["edition"][0] = preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $p["edition"][0]);

				//volume
				if (isset($p["vol"]) && !isset($p["volume"][0])) {$p["volume"] = $p["vol"]; unset($p["vol"]);}

				//page nos
				preg_match("~(\w?\w?\d+\w?\w?)(\D+(\w?\w?\d+\w?\w?))?~", $p["pages"][0], $pagenos);

				//Authors
				if (isset($p["authors"]) && !isset($p["author"][0])) {$p["author"] = $p["authors"]; unset($p["authors"]);}
				preg_match("~[^.,;\s]{2,}~", $p["author"][0], $firstauthor);
				if (!$firstauthor[0]) preg_match("~[^.,;\s]{2,}~", $p["last"][0], $firstauthor);
				if (!$firstauthor[0]) preg_match("~[^.,;\s]{2,}~", $p["last1"][0], $firstauthor);

				// Is there already a date parameter?
				$dateToStartWith = (isset($p["date"][0]) && !isset($p["year"][0]));

				if (!isset($p["date"][0]) && !isset($p["year"][0]) && is('origyear')) {
					$p['year'] = $p['origyear'];
					unset ($p['origyear']);
				}

				$isbnToStartWith = isset($p["isbn"]);
				if (!isset($p["isbn"][0]) && is("title")) set("isbn", findISBN( $p["title"][0], $p["author"][0] . " " . $p["last"][0] . $p["last1"][0]));
				else echo "\n  Already has an ISBN. ";
				if (!$isbnToStartWith && !$p["isbn"][0]) unset($p["isbn"]);

				if (	(is("pages") || is("page"))
							&& is("title")
							&& is("publisher")
							&& (is("date") || is("year"))
							&& (
									is("author") || is("coauthors") || is("others")
									|| is("author1")
									|| is("author1-last")
									|| is("last") || is("last1")
									|| is("editor1-first") || is("editor1-last") || is("editor1")
									|| is("editor") || is("editors")
								)
						)
				 echo "All details present - no need to look up ISBN. ";
				else {
					if (is("isbn")) getInfoFromISBN();
				}

				##############################
				# Finished with citation and retrieved ISBN data #
				#############################

				// Now wikify some common formatting errors - i.e. tidy up!
				if (isset($p["title"][0]) && !trim($pStart["title"])) $p["title"][0] = niceTitle($p["title"][0]);
				if (isset($p[$journal][0])) $p[$journal][0] = niceTitle($p[$journal][0], false);
				if (isset($p["periodical"][0])) $p["periodical"][0] = niceTitle($p["periodical"][0], false);
				if (isset($p["pages"][0]) && mb_ereg("([0-9A-Z])[\t ]*(-|\&mdash;|\xe2\x80\x94|\?\?\?)[\t ]*([0-9A-Z])", $p["pages"][0])) {
          $p["pages"][0] = mb_ereg_replace("([0-9A-Z])[\t ]*(-|\&mdash;|\xe2\x80\x94|\?\?\?)[\t ]*([0-9A-Z])", "\\1\xe2\x80\x93\\3", $p["pages"][0]);
          $changedDashes = true;
        }
				#if (isset($p["year"][0]) && trim($p["year"][0]) == trim($p["origyear"][0])) unset($p['origyear']);
				#if (isset($p["publisher"][0])) $p["publisher"][0] = truncatePublisher($p["publisher"][0]);

				if ($dateToStartWith) unset($p["year"]); // If there was a date parameter to start with, don't add a year too!

				// If we have any unused data, check to see if any is redundant!
				if (is("unused_data")){
					$freeDat = explode("|", trim($p["unused_data"][0]));
					unset($p["unused_data"]);
					foreach ($freeDat as $dat) {
						$eraseThis = false;
						foreach ($p as $oP) {
							similar_text(strtolower($oP[0]), strtolower($dat), $percentSim);
							if ($percentSim >= 85)
								$eraseThis = true;
						}
						if (!$eraseThis) $p["unused_data"][0] .= "|" . $dat;
					}
					if (trim(str_replace("|", "", $p["unused_data"][0])) == "") unset($p["unused_data"]);
					else {
						if (substr(trim($p["unused_data"][0]), 0, 1) == "|") $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);
						echo "\n* <div style=color:limegreen>XXX Unused data in following citation: {$p["unused_data"][0]}</div>";
					}
				}

				//And we're done!
				$endtime = time();
				$timetaken = $endtime - $starttime;
				print "\n  Book reference assessed in $timetaken secs.";

				// Get a format for spacing around the pipe or equals
				foreach ($p as $oP){
					$pipe=$oP[1]?$oP[1]:null;
					$equals=$oP[2]?$oP[2]:null;
					if ($pipe) break;
				}
				if (!$pipe) $pipe="\n | ";
				if (!$equals) $equals=" = ";
				foreach($p as $param => $v) {
					if ($param) $cText .= ($v[1]?$v[1]:$pipe ). $param . ($v[2]?$v[2]:$equals) . str_replace(pipePlaceholder, "|", trim($v[0]));
					if (is($param)) $pEnd[$param] = $v[0];
				}
				$p = null;
				if ($pEnd) {
					foreach ($pEnd as $param => $value) {
						if (!$pStart[$param]) {
              $additions[$param] = true;
            } elseif ($pStart[$param] != $value) {
              $changes[$param] = true;
            }
					}
				}

				// Convert into citation or cite journal, as appropriate
				if ($useCitationFormat) {
					$citation[$cit_i+2] = preg_replace("~[cC]ite[ _]\w+~", "Citation", $citation[$cit_i+2]);
				}
				// Restore comments we hid earlier
				for ($j = 0; $j < $countComments; $j++) {
					$cText = str_replace("<!-- Citation bot : comment placeholder b$j -->"
																				, $comments[0][$j]
																				, $cText);
				}
				$pagecode .=  $citation[$cit_i] . ($cText?"{{{$citation[$cit_i+2]}$cText{$citation[$cit_i+4]}}}":"");
				$cText = null;
				$crossRef = null;
			}
			$pagecode .= $citation[$cit_i]; // Adds any text that comes after the last citation
		}
###################################  START ASSESSING JOURNAL/OTHER CITATIONS ######################################

		if ($citation = preg_split("~{{((\s*[Cc]ite[_ ]?[jJ]ournal(?=\s*\|)|\s*[cC]itation(?=\s*\|))([^{}]|{{.*}})*)([\n\s]*)}}~U", $pagecode, -1, PREG_SPLIT_DELIM_CAPTURE)) {
			$pagecode = null;
			$iLimit = (count($citation)-1);
			for ($cit_i=0; $cit_i<$iLimit; $cit_i+=5){//Number of brackets in cite journal regexp + 1
				$starttime = time();

				// Strip comments, which may contain misleading pipes etc
				if (preg_match_all("~<!--[\s\S]+-->~U", $citation[$cit_i+1], $comments)) {
					$countComments = count($comments[0]);
					for ($j = 0; $j < $countComments; $j++) {
						$citation[$cit_i+1] = str_replace($comments[0][$j]
																			, "<!-- Citation bot : comment placeholder c$j -->"
																			, $citation[$cit_i+1]);
					}
				} else {
          // Comments will be replaced in the cText variable later
          $countComments = null;
        }

				$c = preg_replace("~(doi\s*=\s*)doi\s?=\s?(\d\d)~","$1$2",
					preg_replace("~(?<![\?&]id=)doi\s?:(\s?)(\d\d)~","doi$1=$1$2", $citation[$cit_i+1])); // Replaces doi: with doi =
				while (preg_match("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", $c)) $c = preg_replace("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", "$1" . pipePlaceholder, $c);
				preg_match(siciRegExp, urldecode($c), $sici);

##############################
#             Split citation into parameters                     #
##############################

				$parts = preg_split("~([\n\s]*\|[\n\s]*)([\w\d-_]*)(\s*= *)~", $c, -1, PREG_SPLIT_DELIM_CAPTURE);
				$partsLimit = count($parts);
				if (strpos($parts[0], "|") >0 && strpos($parts[0],"[[") === FALSE && strpos($parts[0], "{{") === FALSE) set("unused_data", substr($parts[0], strpos($parts[0], "|")+1));
				for ($partsI=1; $partsI<=$partsLimit; $partsI+=4) {
					$value = $parts[$partsI+3];
					$pipePos = strpos($value, "|");
					if ($pipePos > 0 && strpos($value, "[[") === false & strpos($value, "{{") === FALSE) {
						// There are two "parameters" on one line.  One must be missing an equals.
						$p["unused_data"][0] .= " " . substr($value, $pipePos);
						$value = substr($value, 0, $pipePos);
					}
					// Load each line into $p[param][0123]
          loadParam($parts[$partsI+1], $value, $parts[$partsI], $parts[$partsI+2]);
				}
        
				if ($p["doix"]){
					$p["doi"][0] = str_replace($dotEncode, $dotDecode, $p["doix"][0]);
					unset($p["doix"]);
				}
				//Make a note of how things started so we can give an intelligent edit summary
				foreach($p as $param=>$value)	if (is($param)) $pStart[$param] = $value[0];

				if (is("inventor") || is("inventor-last") || is("patent-number")) print "<p>Unrecognised citation type. Ignoring.</p>";// Don't deal with patents!
				else {
        //Check for the doi-inline template in the title
        if (preg_match("~\{\{\s*doi-inline\s*\|\s*(10\.\d{4}/[^\|]+)\s*\|\s*([^}]+)}}~",
                        str_replace('doi_bot_pipe_placeholder', "|", $p['title'][0]), $match)) {
          set('title', $match[2]);
          set('doi', $match[1]);
        }

###########################
//
echo "
*-> {$p["title"][0]}
 1: Tidy citation and try ISBN";
//  See if we can get any 'free' metadata from:
//  * mis-labelled parameters
//  * ISBN
// * SICI
//  * Tidying up existing parameters (and we'll do more tidying here too)
//
###########################
          
					$journal = is("periodical")?"periodical":"journal";
					// See if we can use any of the parameters lacking equals signs:
					$freeDat = explode("|", trim($p["unused_data"][0]));
					useUnusedData();


          // If the page has been created manually from a cite doi link, it will have an encoded 'doix' parameter - decode this.
          if (preg_match("~^10.\d{4}.2F~", $p['doix'][0])) {
            $p['doi'][0] = str_replace($dotEncode, $dotDecode, $p['doix'][0]);
            unset($p['doix']);
          }
          if (preg_match("~http://www.ncbi.nlm.nih.gov/.+=(\d\d\d+)~", $p['url'][0], $match)) {
            ifNullSet ('pmid', $match[1]);
            unset($p['url']);
          }

					if (is("isbn")) getInfoFromISBN();


          if (trim(str_replace("|", "", $p["unused_data"][0])) == "") {
            unset($p["unused_data"]);
          } else {
						if (substr(trim($p["unused_data"][0]), 0, 1) == "|") {
              $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);
            }
					}

					// Load missing parameters from SICI, if we found one...
					if ($sici[0]){
						if (!is($journal) && !is("issn")) set("issn", $sici[1]);
						#if (!is ("year") && !is("month") && $sici[3]) set("month", date("M", mktime(0, 0, 0, $sici[3], 1, 2005)));
						if (!is("year")) set("year", $sici[2]);
						#if (!is("day") && is("month") && $sici[4]) set ("day", $sici[4]);
						if (!is("volume")) set("volume", 1*$sici[5]);
						if (!is("issue") && $sici[6]) set("issue", 1*$sici[6]);
						if (!is("pages") && !is("page")) set("pages", 1*$sici[7]);
					}

					// Fix typos in parameter names

					// DOI - urldecode
					if (isset($p["doi"][0])) {
            $p['doi'][0] = trim(preg_replace("~\<!--.*--\>~", "", $p["doi"][0]));
						$p["doi"][0] = str_replace($pcEncode,$pcDecode,str_replace(' ', '+', trim(urldecode($p["doi"][0]))));
						$noComDoi= preg_replace("~<!--[\s\S]*-->~U", "", $p["doi"][0]);
						if (preg_match("~10\.\d{4}/\S+~", $noComDoi,$match)) set("doi", $match[0]);
					} else {
						if (preg_match("~10\.\d{4}/[^&\s]*~", urldecode($c), $match)) $p["doi"][0] = $match[0];
					}
					$doiToStartWith = isset($p["doi"]);

					//volume
					if (isset($p["vol"]) && !isset($p["volume"][0])) {$p["volume"] = $p["vol"]; unset($p["vol"]);}

					// pmid = PMID 1234 can produce pmpmid = 1234
					if (isset($p["pmpmid"])) {$p["pmid"] = $p["pmpmid"]; unset($p["pmpmid"]);}

					//pages
					preg_match("~(\w?\w?\d+\w?\w?)(\D+(\w?\w?\d+\w?\w?))?~", $p["pages"][0], $pagenos);

					//Authors
          // Move authors -> author
					if (isset($p["authors"]) && !isset($p["author"][0])) {
						$p["author"] = $p["authors"];
						unset($p["authors"]);
					}

          $authors_missing = false; // reset
          // The phrase 'et al' should not be included in the authors parameter.
          // It is discouraged and may be mistaken for an author by the bot.
          // If it is present, we will search for more authors when we get the chance - set $authors_missing = true
          if (is('author')) {
            if (preg_match("~([,.; ]+)'*et al['.]*(?!\w)~", $p['author'][0], $match)) {
              $chars = count_chars($p['author'][0]);
              if ($chars[ord(";")] > 0) {
                $truncate_after = $chars[ord(";")];
                if (strpos($match[0], ';') === false) {
                  $truncate_after++;
                }
              } elseif ($chars[ord(",")] > 0) {
                $truncate_after = $chars[ord(",")];
                if (strpos($match[0], ',') === false) {
                  $truncate_after++;
                }
              }
              $p['author'][0] = preg_replace("~[,.; ]+'*et al['.]*(?!\w)~", "", $p['author'][0]);
              print " - $truncate_after authors then <i>et al</i>. Will grow list later.";
              $authors_missing = true;
              ifNullSet('display-authors', $truncate_after);
            }
          }
          exit;
          $author_param = trim($p['author'][0]);
          if (preg_match_all("~([\w+\-. ]+\s+[\w+. ]+),~", $author_param, $matches)) {
            $last_author = preg_replace("~[\w+\-. ]+\s+[\w+. ]+,~", "", $author_param);
            $matches[1][] = $last_author;
            unset($p['author']);
            $au_i = 0;
            foreach ($matches[1] as $author) {
              $au_i++;
              set ("author" . $au_i, $author);
            }
            set('author-separator', ',');
            set('author-name-separator', '');
          }

          // Use semi-colons to split authors
					preg_match("~[^.,;\s]{2,}~", $p["author"][0], $firstauthor);
					if (!$firstauthor[0]) {
						preg_match("~[^.,;\s]{2,}~", $p["author1"][0], $firstauthor);
					}
					if (!$firstauthor[0]) {
						preg_match("~[^.,;\s]{2,}~", $p["last"][0], $firstauthor);
					}
					if (!$firstauthor[0]) {
						preg_match("~[^.,;\s]{2,}~", $p["last1"][0], $firstauthor);
					}
					// Is there already a date parameter?
					$dateToStartWith = (isset($p["date"][0]) && !isset($p["year"][0])) ;

#####################################
//
if (is('doi')) {
echo "
 2: DOI already present :-)";
} else {
echo "
 2: Find DOI";
//  Now we have got the citation ship-shape, let's try to find a DOI.
//
#####################################



						//Try CrossRef
						echo "\n - Checking CrossRef database... ";
						$crossRef = crossRefDoi(trim($p["title"][0]), trim($p[$journal][0]),
                                    trim($firstauthor[0]), trim($p["year"][0]), trim($p["volume"][0]),
                                    $pagenos[1], $pagenos[3], trim($p["issn"][0]), trim($p["url"][0]));
						if ($crossRef) {
              $p["doi"][0] = $crossRef->doi;
							echo "Match found: " . $p["doi"][0];
						} else {
              echo "no match.";
            }

            //Try URL param
						if (!isset($p["doi"][0])) {
							if (strpos($p["url"][0], "http://") !== false) {
								//Try using URL parameter
								echo $htmlOutput
                      ? ("\n - Trying <a href=\"" . $p["url"][0] . "\">URL</a>. <br>")
                      : "\n - Trying URL {$p["url"][0]}";
								$doi = findDoi($p["url"][0]);
								if ($doi) {
									echo " found doi $doi";
									$p["doi"][0] = $doi;
								} else {
                  echo " no doi found.";
                }
              } else {
                echo "No valid URL specified.  ";
              }
						}
					}

					if (!$doiToStartWith && !is("doi")) unset($p["doi"]);

#####################################
//
if (is ('pmid')) {
echo "
 3: PMID already present :-)";
} else {
echo "
 3: Find PMID & expand";
//  We've tried searching CrossRef and the URL for a DOI.
//  Now let's move on to find a PMID
//  If we don't find one, we'll check for an ISBN in case it's a book.
//
#####################################




          print "\n - Searching PubMed... ";
          $results = (pmSearchResults($p));
          if ($results[1] == 1) {
            set('pmid', $results[0]);
            $details = pmArticleDetails($results[0]);
            foreach ($details as $key=>$value) {
              ifNullSet ($key, $value);
            }
            if (false && !is("url")) { // BUGGY - CHECK PMID DATABASES
              if (!is('pmc')) {
              $url = pmFullTextUrl($p["pmid"][0]);
							} else {
								unset ($p['url']);
							}
              if ($url) {
                set ("url", $url);
                if ($citedoi) {
                  # set ("format", "Free full text"); // Don't do this any more.
                }
              }
            }
            echo " 1 result found; citation updated";
            if (!is('doi')) {
              // PMID search succeeded but didn't throw up a new DOI.  Try CrossRef again.
              echo "\n - Looking for DOI in CrossRef database with new information ... ";
              $crossRef = crossRefDoi(trim($p["title"][0]), trim($p[$journal][0]),
                                      trim($firstauthor[0]), trim($p["year"][0]), trim($p["volume"][0]),
                                      $pagenos[1], $pagenos[3], trim($p["issn"][0]), trim($p["url"][0]));
              if ($crossRef) {
                $p["doi"][0] = $crossRef->doi;
                echo "Match found: " . $p["doi"][0];
              } else {
                echo "no match.";
              }
            }
          } else {
            echo " nothing found.\n - Checking for ISBN";
							$isbnToStartWith = isset($p["isbn"]);
								if (!isset($p["isbn"][0]) && is("title")) set("isbn", findISBN( $p["title"][0], $p["author"][0] . " " . $p["last"][0] . $p["last1"][0]));
								else echo "\n  Already has an ISBN. ";
								if (!$isbnToStartWith && !$p["isbn"][0]) {
                    unset($p["isbn"]);
                } else {
                  getInfoFromISBN();
                }
          }
         }

#####################################
//
if (nothingMissing($journal)) {
echo "
 4: Citation complete :-)";
} else {
echo "
 4: Expand citation";
//  Try JSTOR (quick & easy); CrossRef...
//
#####################################


          if (preg_match("~jstor\D+(\d+)\D*$~i", $p['url'][0], $jid)
            || preg_match("~10.2307/(\d+)~", $p['doi'][0], $jid)
              ) {
            print "\n - Checking JSTOR record {$jid[0]} for data.";
            $newData = jstorData($jid[1]);
            foreach ($newData as $key => $value) {
              ifNullSet($key, $value);
            }
          }

          if (!nothingMissing($journal) && is('pmid')) {
            echo "\n - Checking PMID {$p['pmid'][0]} for more details";
            $details = pmArticleDetails($p['pmid'][0]);
            foreach ($details as $key=>$value) {
              ifNullSet($key, $value);
            }
            if (false && !is("url")) { // BUGGY - CHECK PMID DATABASES, and see other occurrence above
              if (!is('pmc')) {
              $url = pmFullTextUrl($p["pmid"][0]);
							} else {
								unset ($p['url']);
							}
              if ($url) {
                set ("url", $url);
                if ($citedoi) {
                  # set ("format", "Free full text"); // DOn't do this any more.
                }
              }
            }
          }

          if (!nothingMissing($journal)) {
            if (is("doi")) {
              echo "\n - Checking CrossRef for more details";
              $crossRef = $crossRef?$crossRef:crossRefData(urlencode(trim($p["doi"][0])));
              if ($crossRef) {
                if ($citedoi) {
                  $doiCrossRef = $crossRef;
                }
                ifNullSet("title", $crossRef->article_title);
                ifNullSet("year", $crossRef->year);
                if ($crossRef->contributors->contributor) {
                  $authors=null;
                  $au_i = 0;
                  foreach ($crossRef->contributors->contributor as $author) {
                    $au_i++;
                    if ($au_i < 10) {
                      ifNullSet("last$au_i", formatSurname($author->surname));
                      ifNullSet("first$au_i", formatInitials($author->given_name));
                    }
                  }
                }
                ifNullSet($journal, $crossRef->journal_title);
                ifNullSet("volume", $crossRef->volume);
                if (!is("page")) ifNullSet("pages", $crossRef->first_page);
              } else {
                echo "\n - No CrossRef record found :-(";
              }
            } else {
              echo "\n - No DOI; can't check CrossRef";
              $crossRef = null;
            }

          }
        }

#####################################
//
if ($citedoi && (strpos($page, 'ite doi') || strpos($page, 'ite_doi'))) {
echo "
 5: Cite Doi Enhancement";
// We have now recovered all possible information from CrossRef.
//If we're using a Cite Doi subpage and there's a doi present, check for a second author. Only do this on first visit (i.e. when citedoi = true)
//
#####################################



						// Check that DOI hasn't been urlencoded.  Note that the doix parameter is decoded and used in step 1.
            if (preg_match("~^10.\d{4}.2F~", $p['doi'][0])) {
							$p['doi'][0] = str_replace($dotEncode, $dotDecode, $p['doi'][0]);
						}

            // Get the surname of the first author. (We [apparently] found this earlier, but it might have changed since then)
            preg_match("~[^.,;\s]{2,}~", $p["author"][0], $firstauthor);
            if (!$firstauthor[0]) {
              preg_match("~[^.,;\s]{2,}~", $p["author1"][0], $firstauthor);
            }
            if (!$firstauthor[0]) {
              preg_match("~[^.,;\s]{2,}~", $p["last"][0], $firstauthor);
            }
            if (!$firstauthor[0]) {
              preg_match("~[^.,;\s]{2,}~", $p["last1"][0], $firstauthor);
            }

            // If we only have the first author, look for more!
						if (!is('coauthors')
							 && !is('author2')
							 && !is('last2')
							 && is('doi')
							){
              echo "\n - Looking for co-authors & page numbers...";
  						$moreAuthors = findMoreAuthors($p['doi'][0], $firstauthor[0], $p['pages'][0]);
							$count = count($moreAuthors['authors']);
							if ($count) {
                echo " Found more authors! ";
								for ($j = 0; $j < $count; $j++) {
									$au = explode(', ', $moreAuthors['authors'][$j]);
									if ($au[1]) {
										set ('last' . ($j+1), $au[0]);
										set ('first' . ($j+1), preg_replace("~(\w)\w*\.? ?~", "$1.", $au[1]));
										unset($p['author' . ($j+1)]);
									} else {
										set ('author' . ($j+1), $au[0]);
									}
								}
								unset($p['author']);
							}
							if ($moreAuthors['pages']) {
                set('pages', $moreAuthors['pages']);
                echo " Completed page range! (" . $p['pages'][0]  . ')';
              }
						}
					}

#####################################
//
echo "
Done.  Just a couple of things to tweak now...";
//
//
#####################################

          if (!is("format") && is("url")){
            print "\n - Determining format of URL...";
            $formatSet = isset($p["format"]);
            $p["format"][0] = assessUrl($p["url"][0]);
            if (!$formatSet && trim($p["format"][0]) == "") {
              unset($p["format"]);
            }
            echo "Done" , is("format")?" ({$p["format"][0]})":"" , ".</p>";
          }
				}

				// Now wikify some common formatting errors - i.e. tidy up!
				if (!trim($pStart["title"]) && isset($p["title"][0])) {
          $p["title"][0] = formatTitle($p["title"][0]);
        }
				if (isset($p[$journal][0])) {
          $p[$journal][0] = niceTitle($p[$journal][0], false);
        }
				if (isset($p["pages"][0])) {
          if (mb_ereg("([0-9A-Z])[\t ]*(-|\&mdash;|\xe2\x80\x94|\?\?\?)[\t ]*([0-9A-Z])", $p["pages"][0])) {
            $p["pages"][0] = mb_ereg_replace("([0-9A-Z])[\t ]*(-|\&mdash;|\xe2\x80\x94|\?\?\?)[\t ]*([0-9A-Z])", "\\1\xe2\x80\x93\\3", $p["pages"][0]);
            $changedDashes = true;
          }
        }
        // If there was a date parameter to start with, don't add a year too.  This will be created by the template.
				if ($dateToStartWith) {
          unset($p["year"]);
        }
        if (strpos($p['author'][0], ';') && !is('author2')) {
          $auths = explode(';', $p['author'][0]);
          unset($p['author']);
          foreach ($auths as $au_i => $auth) {
             set('author' . ($au_i+1), formatAuthor($auth));
          }
        }
        // If we're on a Cite Doi page, format authors accordingly
        if (strpos($page, 'ite doi') || strpos($page, 'ite_doi')) {
          citeDoiOutputFormat();
        }


        // Unset authors above 'author9' - the template won't render them.
        for ($au_i = 10; is("authors$au_i") || is ("last$au_i"); $au_i++){
          unset($p["author$au_i"]);
          unset($p["first$au_i"]);
          unset($p["last$au_i"]);
        }

				// Check that the DOI functions.
				if (trim($p["doi"][0]) != "" && trim($p["doi"][0]) != "|" && $slowMode) {
					echo "\nChecking that DOI {$p["doi"][0]} is operational...";
					$brokenDoi = isDoiBroken($p["doi"][0], $p);
					if ($brokenDoi && !is("doi_brokendate")) {
						set("doi_brokendate", date("Y-m-d"));
					}
					ELSE if (!$brokenDoi) unset($p["doi_brokendate"]);
					echo $brokenDoi?" It isn't.":"OK!", "</p>";
				}
				/*if (!$p["url"]){
					unset($p["format"]/*, $p["accessdate"], $p["accessyear"], $p["accessmonthday"], $p["accessmonth"], $p["accessday"]);
				}elseif (!$p["url"][0]){
					unset($p["format"][0]/*, $p["accessdate"][0], $p["accessyear"][0], $p["accessmonthday"], $p["accessmonth"][0], $p["accessday"][0]);
				}*/
				//DOIlabel is now redundant
				unset($p["doilabel"]);

        //Edition - don't want 'Edition ed.'
        if (is("edition")) $p["edition"][0] = preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $p["edition"][0]);

				//because of cite journal doc...
				if (is($p["journal"]) && (is("doi") || is("issn"))) unset($p["publisher"]);

				// If we have any unused data, check to see if any is redundant!
				if (is("unused_data")){
					$freeDat = explode("|", trim($p["unused_data"][0]));
					unset($p["unused_data"]);
					foreach ($freeDat as $dat) {
						$eraseThis = false;
						foreach ($p as $oP) {
							similar_text(strtolower($oP[0]), strtolower($dat), $percentSim);
							if ($percentSim >= 85)
								$eraseThis = true;
						}
						if (!$eraseThis) $p["unused_data"][0] .= "|" . $dat;
					}
					if (trim(str_replace("|", "", $p["unused_data"][0])) == "") unset($p["unused_data"]);
					else {
						if (substr(trim($p["unused_data"][0]), 0, 1) == "|") $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);
						echo "\nXXX Unused data in following citation: {$p["unused_data"][0]}";
					}
				}


				//And we're done!
				$endtime = time();
				$timetaken = $endtime - $starttime;
				print "\n*** Complete. Citation assessed in $timetaken secs.\n\n\n";
				foreach ($p as $oP){
					$pipe=$oP[1]?$oP[1]:null;
					$equals=$oP[2]?$oP[2]:null;
					if ($pipe) break;
				}
				if (!$pipe) $pipe="\n | ";
				if (!$equals) $equals=" = ";
				foreach($p as $param => $v) {
					if ($param) $cText .= ($v[1]?$v[1]:$pipe ). $param . ($v[2]?$v[2]:$equals) . str_replace(pipePlaceholder, "|", trim($v[0]));
					if (is($param)) $pEnd[$param] = $v[0];
				}
				if ($pEnd) {
					foreach ($pEnd as $param => $value) {
						if (!$pStart[$param]) $additions[$param] = true;
						elseif ($pStart[$param] != $value) $changes[$param] = true;
					}
				}
				if ($changeCitationFormat) {
					if ($useCitationFormat) {
						$citation[$cit_i+2] = preg_replace("~[cC]ite[ _]\w+~", "Citation", $citation[$cit_i+2]);
					} else {
						if (is('isbn') || is("oclc")) {$citeTemplate = "Cite book";}
						elseif (is('chapter')) {$citeTemplate = "Cite book";}
						elseif (is('conference') || is('conferenceurl')) {$citeTemplate = "Cite conference";}
						elseif (is('encyclopedia')) {$citeTemplate = "Cite encyclopedia";}
						elseif (is('agency') || is('newspaper') || is('magazine') || is('periodical')) {
							$citeTemplate = "Cite news";
						}
						elseif (is('journal')) {$citeTemplate = "Cite journal";}
						elseif (is('publisher')) {
              // This should be after we've checked for a journal parameter
              if (preg_match("~\w\.\w\w~", $p['publisher'][0])) {
               // it's a fair bet the piblisher is a web address
                $citeTemplate = "Cite web";
              } else {
                $citeTemplate = "Cite book";
              }
            }
						elseif (is('url')) {$citeTemplate = "Cite web";} // fall back to this if URL
						else {$citeTemplate = "Cite journal";} // If no URL, cite journal ought to handle it okay
						$citation[$cit_i+2] = preg_replace("~[cC]itation~", $citeTemplate, $citation[$cit_i+2]);
					}
				}
				// Restore comments we hid earlier
				for ($j = 0; $j < $countComments; $j++) {
					$cText = str_replace("<!-- Citation bot : comment placeholder c$j -->"
																				, $comments[0][$j]
																				, $cText);
				}
				$pagecode .=  $citation[$cit_i] . ($cText?"{{{$citation[$cit_i+2]}$cText{$citation[$cit_i+4]}}}":"");
				$cText = null;
				$crossRef = null;
				$p = null;
			}

			$pagecode .= $citation[$cit_i]; // Adds any text that comes after the last citation
		}

###################################  Cite arXiv ######################################
		if ($citation = preg_split("~{{((\s*[Cc]ite[_ ]?[aA]r[xX]iv(?=\s*\|))([^{}]|{{.*}})*)([\n\s]*)}}~U", $pagecode, -1, PREG_SPLIT_DELIM_CAPTURE)) {
			$pagecode = null;
			$iLimit = (count($citation)-1);
			for ($cit_i=0; $cit_i<$iLimit; $cit_i+=5){//Number of brackets in cite arXiv regexp + 1
				$starttime = time();
				$c = $citation[$cit_i+1];
				while (preg_match("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", $c)) $c = preg_replace("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", "$1" . pipePlaceholder, $c);
				// Split citation into parameters
				$parts = preg_split("~([\n\s]*\|[\n\s]*)([\w\d-_]*)(\s*= *)~", $c, -1, PREG_SPLIT_DELIM_CAPTURE);
				$partsLimit = count($parts);
				if (strpos($parts[0], "|") >0 && strpos($parts[0],"[[") === FALSE && strpos($parts[0], "{{") === FALSE) set("unused_data", substr($parts[0], strpos($parts[0], "|")+1));
				for ($partsI=1; $partsI<=$partsLimit; $partsI+=4) {
					$value = $parts[$partsI+3];
					$pipePos = strpos($value, "|");
					if ($pipePos > 0 && strpos($value, "[[") === false & strpos($value, "{{") === FALSE) {
						// There are two "parameters" on one line.  One must be missing an equals.
						$p["unused_data"][0] .= " " . substr($value, $pipePos);
						$value = substr($value, 0, $pipePos);
					}
					// Load each line into $p[param][0123]
					$p[strtolower($parts[$partsI+1])] = Array($value, $parts[$partsI], $parts[$partsI+2]); // Param = value, pipe, equals
				}
				//Make a note of how things started so we can give an intelligent edit summary
				foreach($p as $param=>$value)	if (is($param)) $pStart[$param] = $value[0];
				// See if we can use any of the parameters lacking equals signs:
				$freeDat = explode("|", trim($p["unused_data"][0]));
				useUnusedData();
				if (trim(str_replace("|", "", $p["unused_data"][0])) == "") unset($p["unused_data"]);
				else if (substr(trim($p["unused_data"][0]), 0, 1) == "|") $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);

				echo "\n* {$p["title"][0]}";

        // Fix typos in parameter names
				//Authors
				if (isset($p["authors"]) && !isset($p["author"][0])) {$p["author"] = $p["authors"]; unset($p["authors"]);}
				preg_match("~[^.,;\s]{2,}~", $p["author"][0], $firstauthor);
				if (!$firstauthor[0]) preg_match("~[^.,;\s]{2,}~", $p["last"][0], $firstauthor);
				if (!$firstauthor[0]) preg_match("~[^.,;\s]{2,}~", $p["last1"][0], $firstauthor);
        for ($au_i = 10; isset($p["last$au_i"]) || isset($p["author$au_i"]); $au_i++) {
          unset($p["last$au_i"]);
          unset($p["first$au_i"]);
          unset($p["author$au_i"]);
        }

				// Is there already a date parameter?
				$dateToStartWith = (isset($p["date"][0]) && !isset($p["year"][0])) ;
				print $p["eprint"][0] . "\n";
				if (is("eprint")
						&& !(is("title") && is("author") && is("year") && is("version")))
						getDataFromArxiv($p["eprint"][0]);

				// Now wikify some common formatting errors - i.e. tidy up!
				if (!trim($pStart["title"]) && isset($p["title"][0])) $p["title"][0] = formatTitle($p["title"][0]);

				// If we have any unused data, check to see if any is redundant!
				if (is("unused_data")){
					$freeDat = explode("|", trim($p["unused_data"][0]));
					unset($p["unused_data"]);
					foreach ($freeDat as $dat) {
						$eraseThis = false;
						foreach ($p as $oP) {
							similar_text(strtolower($oP[0]), strtolower($dat), $percentSim);
							if ($percentSim >= 85)
								$eraseThis = true;
						}
						if (!$eraseThis) $p["unused_data"][0] .= "|" . $dat;
					}
					if (trim(str_replace("|", "", $p["unused_data"][0])) == "") unset($p["unused_data"]);
					else {
						if (substr(trim($p["unused_data"][0]), 0, 1) == "|") $p["unused_data"][0] = substr(trim($p["unused_data"][0]), 1);
						echo "\nXXX Unused data in following citation: {$p["unused_data"][0]}";
					}
				}

				// Now: Citation bot task 5.  If there's a journal parameter switch the citation to 'cite journal'.
				$changeToJournal = is('journal');
				if ($changeToJournal && is('eprint')) {
					$p['id'][0] = "{{arXiv|{$p['eprint'][0]}}}";
					unset($p['class']);
					unset($p['eprint']);
					$changeCiteType = true;
				}

				//And we're done!
				$endtime = time();
				$timetaken = $endtime - $starttime;
				print "* Citation assessed in $timetaken secs. " . ($changeToJournal?"Changing to Cite Journal. ":"Keeping as cite arXiv") . "\n";
				foreach ($p as $oP){
					$pipe=$oP[1]?$oP[1]:null;
					$equals=$oP[2]?$oP[2]:null;
					if ($pipe) break;
				}
				if (!$pipe) {
           $pipe = "\n | ";
        }
				if (!$equals) {
          $equals = " = ";
        }
				foreach($p as $param => $v) {
					if ($param) $cText .= ($v[1]?$v[1]:$pipe ). $param . ($v[2]?$v[2]:$equals) . str_replace(pipePlaceholder, "|", trim($v[0]));
					if (is($param)) $pEnd[$param] = $v[0];
				}
				$p = null;
				if ($pEnd)
					foreach ($pEnd as $param => $value)
						if (!$pStart[$param]) {
              $additions[$param] = true;
            } elseif ($pStart[$param] != $value) {
              $changes[$param] = true;
            }
				$pagecode .=  $citation[$cit_i] . ($cText?"{{" . ($changeToJournal?"cite journal":$citation[$cit_i+2]) . "$cText{$citation[$cit_i+4]}}}":"");
#				$pagecode .=  $citation[$cit_i] . ($cText?"{{{$citation[$cit_i+2]}$cText{$citation[$cit_i+4]}}}":"");
				$cText = null;
				$crossRef = null;
			}

			$pagecode .= $citation[$cit_i]; // Adds any text that comes after the last citation
		}
		if ($changeCitationFormat && $useCitationFormat) {
			$pagecode = preg_replace("~[cC]ite[ _](web|conference|encyclopedia|news)~", "Citation", $pagecode);
		}

		if (trim($pagecode)){
			if (strtolower($pagecode) != strtolower($startcode)) {
				if ($additions){
					$smartSum = "Added: ";
					foreach ($additions as $param=>$v)	{$smartSum .= "$param, "; unset($changes[$param]);}
					$smartSum = substr($smartSum, 0, strlen($smartSum)-2);
					$smartSum .= ". ";
				}
				if ($changes["accessdate"]) {
					$smarSum .= "Removed accessdate with no specified URL. ";
					unset($changes["accessdate"]);
				}
				if ($changes) {
					$smartSum .= "Formatted: ";
					foreach ($changes as $param=>$v)	$smartSum .= 				"$param, ";
					$smartSum = substr($smartSum,0, strlen($smartSum)-2);
					$smartSum.=". ";
				}
				if ($changeCiteType || $changeCitationFormat) {
          $smartSum .= "Unified citation types. ";
        }
				if (!$smartSum) {
          if ($changedDashes) {
            $smartSum .= "Formatted [[WP:ENDASH|dashes]]. ";
          } else {
            $smartSum = "Removed redundant parameters. ";
          }
        }
				echo "$smartSum";
				$editSummary = $editSummaryStart . $editInitiator . $smartSum . $editSummaryEnd;
				if ($ON) {
					if ( strpos($page, "andbox")>1) {
							echo $htmlOutput?"<i style='color:red'>Writing to <a href=\"http://en.wikipedia.org/w/index.php?title=".urlencode($page)."\">$page</a> <small><a href=http://en.wikipedia.org/w/index.php?title=".urlencode($page)."&action=history>history</a></small></i>\n\n</br><br>":"\n*** Writing to $page";
							write($page . $_GET["subpage"], $pagecode, $editInitiator . "Citation maintenance: Fixing/testing bugs. "
								.	"Problems? [[User_talk:Smith609|Contact the bot's operator]]. ");
						} else {
							echo "<i style='color:red'>Writing to <a href=\"http://en.wikipedia.org/w/index.php?title=".urlencode($page)."\">$page</a> ... ";
							if (write($page . $_GET["subpage"], $pagecode, $editSummary)) {
								updateBacklog($page);
								echo "Success.";
							} else {
								echo "Edit may have failed. Retrying: <span style='font-size:1px'>xxx</span> ";
								if (write($page . $_GET["subpage"], $pagecode, $editSummary)) {
									updateBacklog($page);
									echo "Success.";
								} else {
									echo "Still no good. One last try: ";
									if (write($page . $_GET["subpage"], $pagecode, $editSummary)) {
										updateBacklog($page);
										echo "Success. Phew!";
									} else echo "Failed.  Abandoning page.";
								}
							}
							echo $htmlOutput?" <small><a href=http://en.wikipedia.org/w/index.php?title=".urlencode($page)."&action=history>history</a></small></i>\n\n<br>":".";
						}
						$page = nextPage();
						$pageDoneIn = time() - $startPage;
						if ($pageDoneIn<3) {echo "That was quick! ($pageDoneIn secs.) I think I'd better catch my breath."; sleep(3);} else echo "<i>Page took $pageDoneIn secs to process.</i>";
				} else {
					echo "\n\n\n<h5>Output</h5>\n\n\n<!--New code:--><pre>\n\n\n" . $pagecode . "\n\n\n</pre><!--DONE!-->\n\n\n<p><b>Bot switched off</b> &rArr; no edit made.<br><b>Changes:</b> <i>$smartSum</i></p>";
					$page = false;
				}

				//Unset smart edit summary parameters
				$pStart = null;
        $pEnd = null;
        $additions = null;
        $changes = null;
        $smartSum = null;
        $changedDashes = null;
			} else {
				echo "\n ** No changes required --> no edit made.";
        if ($citedoi) {
          if (!articleID($page) && !$doiCrossRef) {
            print "\n\n* $page found on \n  $now\n\n \n\n\n\n";
            $talkPage = "Talk:$now";
            $talkMessage = "== Reference to broken DOI ==\n"
                         . "A reference was recently added to this article using the [[Template:Cite doi|Cite DOI template]]. "
                         . "The [[User:Citation bot|citation bot]] tried to expand the citation, but could not access the specified DOI. "
                         . "Please check that the [[Digital object identifier|DOI]] [[doi:$oDoi]] has been correctly entered.  If the DOI is correct, it is possible that it "
                         . "has not yet been entered into the [[CrossRef]] database.  Please  "
                         . "[http://en.wikipedia.org/w/index.php?title=" . urlencode($page)
                         . "&preload=Template:Cite_doi/preload/nodoi&action=edit complete the reference by hand here]. "
                         . "\nThe script that left this message was unable to track down the user who added the citation; "
                         . "it may be prudent to alert them to this message.  Thanks, ";
            $talkId = articleId($now, 1);
            print "\n[[$talkId]]";
            if ($talkId) {
              $text = getRawWikiText($talkPage);
            } else $text = '';
            print "\n* -[Text:$text]-";
            if (strpos($text, "|DOI]] [[doi:".$oDoi) || strpos($text, "d/nodoi&a")) {
              print strpos($text, "|DOI]] [[doi:".$oDoi) . strpos($text, "d/nodoi&a");
              print "\n - Message already on talk page.  Zzz.\n";
            } else {
              print "\n * Writing message on talk page..." . $talkPage . "\n\n";
              if ($talkPage == "Talk:User_Smith609/Sandbox") write($talkPage, $text . "\n" . $talkMessage . "~~~~", "Reference to broken [[doi:$oDoi]] using [[Template:Cite doi]]: please fix!");
              else {}//exit; ########## Need to check that this is debugged!!!
              print " Message left.\n";
            }
          }
          $doiCrossRef = false;
        } else {
          updateBacklog($page);
        }
				$page = $ON?nextPage():null;
			}
		} else {
			if (trim($startcode)=='') {
				echo "<b>Blank page.</b> Perhaps it's been deleted?";
				if (!$citedoi) updateBacklog($page);
				$page = nextPage();
			} else {
				echo "<b>Error:</b> Blank page produced. This bug has been reported. Page content: $startcode";
				mail ("MartinS+doibot@gmail.com", "DOI BOT ERROR", "Blank page produced.\n[Page = $page]\n[SmartSum = $smartSum ]\n[\$citation = ". print_r($citation, 1) . "]\n[Request variables = ".print_r($_REQUEST, 1) . "]\n\nError message generated by doibot.php.");
				$page = null;
				exit; #legit
			}
		}
	}
	$urlsTried = null; //Clear some memory

	// These variables should change after the first edit
	$isbnKey = "3TUCZUGQ"; //This way we shouldn't exhaust theISBN key for on-demand users.
	$isbnKey2 = "RISPMHTS"; //This way we shouldn't exhaust theISBN key for on-demand users.
	$editSummaryEnd = " You can [[WP:UCB|use this bot]] yourself! Please [[User:DOI_bot/bugs|report any bugs]].";
}