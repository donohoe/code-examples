<?php
/*
	Haiku

	This is a terrible hack and doesn't at all make true haikus :)

	Hits The New Yorker for an article and returns a random Haiku that it finds (if any).

	Traditionally, haiku is written in three lines,
	with five syllables in the first line,
	seven syllables in the second line,
	and five syllables in the third line.

	Algorithm sources & help:
	- http://stackoverflow.com/questions/405161/detecting-syllables-in-a-word?rq=1
	- http://www.readingfirst.virginia.edu/prof_dev/phonemic_awareness/multi_syllables.html

*/
date_default_timezone_set('America/New_York');

header("Content-Type: application/json; charset=utf-8");
header("X-Clacks-Overhead: GNU Terry Pratchett");

function countSyllables($word) {

	$vowels       = array("a", "e", "i", "o", "u", "y");
	$numVowels    = 0;
	$lastWasVowel = false;

/*
	We don't want to count seen as havign two syllables...
	so lets remove one for now
	and then but it back later...
*/
	$replaceThis = array( "aa",  "ee",  "ii",  "oo",  "uu" );
	$withThis    = array( "=a=", "=e=", "=i=", "=o=", "=u=" );
	$word        = str_replace($replaceThis, $withThis, $word);

	$len = strlen( $word );
	for ($i = 0; $i < $len; $i++ ) {
		$foundVowel = false;
		$letter     = substr( $word, $i, 1 );
		if (in_array($letter, $vowels)) {
			if (!$lastWasVowel) {
				$numVowels++;
				$foundVowel = true;
			}
		}
	}

	if (strlen( $word ) > 2 && substr($word, -2) == "es") { #Remove es - it's "usually" silent (?)
		$numVowels--;
	} else if ($numVowels > 1 && strlen( $word ) > 1 && substr($word, -1) == "e") {
		$numVowels--;
	}
	return $numVowels;
}

function findHaiku($text) {
	$words         = explode(" ", $text);
	$debug         = array();
	$haikus        = array();
	$syllableTotal = 0;
	$syllableCount = 0;
	$lineNumber    = 1;
	$haiku         = "";
	$result        = "";

	$dontEndOn   = array( "the", "but", "and", "is", "who", "or", "of", "to", "which", "he", "she", "just", "with" );
	$replaceThis = array( ",", "hatll ",  "youd ",  "theyre",  "hasnt",  "cant",  "wont",  "didnt"  );
	$withThis    = array( "",  "hat'll ", "you'd ", "they're", "hasn't", "can't", "won't", "didn't" );

	foreach ($words as $word) {

		if ($word=="a" || is_numeric($word) ) {
			$syllableTotal = 0;
			$syllableCount = 0;
			$lineNumber    = 1;
			$haiku         = "";
			continue;
		}

		$requiredSyllables = 7;
		if ($lineNumber === 1 || $lineNumber === 3) {
			$requiredSyllables = 5;
		}

		$numOfSyllables = countSyllables($word);
		$syllableCount  = $syllableCount + $numOfSyllables;
		$syllableTotal  = $syllableTotal + $numOfSyllables;

		if ( $syllableCount <= $requiredSyllables) {

			$haiku = $haiku . " " . $word; // . " (" . $numOfSyllables . "/". $requiredSyllables . ")";

			if ($syllableCount === $requiredSyllables) {

				$haiku = $haiku . " \ ";
				$syllableCount = 0;
				$lineNumber++;

				if ($lineNumber > 3) {

					if (!in_array($word, $dontEndOn)) { /* dont accept a haiku if it ends with certain words */

						$haiku    = preg_replace('!\s+!', ' ', $haiku); // Remove multiple spaces with one
						$haiku    = str_replace($replaceThis, $withThis, $haiku);
						$haiku    = trim( $haiku );
						$haiku    = ucfirst( $haiku );
						$result   = substr($haiku, 0, -2);
					}

					$haiku         = "";
					$lineNumber    = 1;
					$syllableTotal = 0;
					$syllableCount = 0;
				}

			}

		} else {

			$syllableTotal = 0;
			$syllableCount = 0;
			$lineNumber    = 1;
			$haiku         = "";

		}

		$debug[] = $word . " " . $lineNumber;
	}

	return $result;
}

function getSentences($text) {

	$protected = array(
		"Mr.", "Ms.", "Mrs.", "Miss.", "Msr.", "Dr.", "Gov.", "Pres.", "Sen.", "Prof.", "Gen.", "Rep.", "St.", 
		"Messrs.", "Col.", "Sr.", "Jf.", "Ph.", "Sgt.", "Mgr.", "Fr.", "Rev.", "No.", "Jr.", "Snr.",
		"A.", "B.", "C.", "D.", "E.", "F.", "G.", "H.", "I.", "J.", "K.", "L.", "M.", "m.", "N.", "O.", 
		"P.", "Q.", "R.", "S.", "T.", "U.", "V.", "W.", "X.", "Y.", "Z.", 
		"etc.", "oz.", "cf.", "viz.", "sc.", "ca.", "Ave.", "St.",
		"Calif.", "Mass.", "Penn.", "AK.", "AL.", "AR.", "AS.", "AZ.", "CA.", "CO.", "CT.", 
		"DC.", "DE.", "FL.", "FM.", "GA.", "GU.", "HI.", "IA.", "ID.", "IL.", "IN.", "KS.", "KY.", 
		"LA.", "MA.", "MD.", "ME.", "MH.", "MI.", "MN.", "MO.", "MP.", "MS.", "MT.",
		"NC.", "ND.", "NE.", "NH.", "NJ.", "NM.", "NV.", "NY.", "OH.", "OK.", "OR.",
		"PA.", "PR.", "PW.", "RI.", "SC.", "SD.", "TN.", "TX.", "UT.", "VA.", "VI.", "VT.", 
		"WA.", "WI.", "WV.", "WY.", "AE.", "AA.", "AP.", "NYC.", "GB.", 
		"IRL.", "IE.", "UK.", "GB.", "FR.",
		"0.", "1.", "2.", "3.", "4.", "5.", "6.", "7.", "8.", "9,",
		"aero.", "asia.", "biz.", "cat.", "com.", "coop.", "edu.", "gov.", "info.", 
		"int.", "jobs.", "mil.", "mobi.", "museum.", "name.", "net.", "org.", 
		"pro.", "tel.", "travel.", "xxx", "www.",
		"i.e."
	);

	$dot       = "__DOT__";
	$text      = str_replace($protected, $dot, $text);
	$sentences = explode(".", $text);

//	Ignore sentences with DOTS in them
	$results = array();
	foreach ($sentences as $s) {
		if (strpos($s, $dot) === false && !empty($s) && strlen($s) > 42 ) {
			$results[] = $s;
		}
	}

	return $results;
}

function sentencefindHaiku($text) {
	$haikus    = array();
	$sentences = getSentences($text);
	foreach ($sentences as $sentence) {
		$haiku = findHaiku($sentence);
		if (!empty($haiku)) {
			$haikus[] = $haiku;
		}
	}
	return $haikus;
}

function cleanTheResponse($text) {

	if (empty($text)) {
		return "Space is limited \ In a haiku, so it's hard \ To finish what you (Error)";
	}

	$text = strip_tags($text);
	$text = str_replace($endash, '-', $text);
	$text = str_replace($emdash, '-', $text);
	$text = iconv("ISO-8859-1", "UTF-8//IGNORE" ,$text);
	$text = preg_replace("/[^A-Za-z0-9 -\.']/", '', $text);

	return $text;
}

function getArticle() {
	$url = "https://www.newyorker.com/feed/app/v1.2/daily.json"; // Local copy at data/daily.json
	$ch  = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, false);
	$response = curl_exec($ch);
	curl_close($ch);
	$response = json_decode($response);

	$collection = $response->collections[0];
	$article    = $collection->items[0]->article;

	return array(
		"headline" => $article->title,
		"link"     => $article->link,
		"content"  => strip_tags($article->content, '')
	);
}

function formatHaikuPlain($article) {
	
/*
	HipChat:
	html - Message is rendered as HTML and receives no special treatment. 
	Must be valid HTML and entities must be escaped (e.g.: '&amp;' instead of '&'). 
	May contain basic tags: a, b, i, strong, em, br, img, pre, code, lists, tables.
*/
	// $thumbnail = $article->image_url_thumbnail;
	$title     = $article["title"];
	$haiku     = $article["haikus"];
	$link      = $article["link"];

	$html = $haiku . " (<a href=\"" . $link . "\">Link</a>)";

	return $html;
}

function formatHaikuTable($article) {
	
/*
	HipChat:
	html - Message is rendered as HTML and receives no special treatment. 
	Must be valid HTML and entities must be escaped (e.g.: '&amp;' instead of '&'). 
	May contain basic tags: a, b, i, strong, em, br, img, pre, code, lists, tables.
*/
	$thumbnail = $article->image_url_thumbnail;
	$title     = $article->title;
	$haiku     = $article->haikus;
	$link      = $article->url;

	$html = implode("", array(
		"<table>",
		" <tr>",
		" <td>",
		"  <img src=\"" . $thumbnail . "\"/>",
		" </td>",
		" <td>",
		"  <b>" . $haiku . "</b>",
		"  <br>",
		"  <a href=\"" . $link . "\">" . $title . "</a>",
		" </td>",
		" </tr>",
		"</table>"
	));

	return $html;
}

function getHaikusFrom() {
	$article = getArticle();
	$haikus  = sentencefindHaiku($article["content"]);
	return array_values($haikus);
}

function getRandomHaikuFrom() {
	$article = getArticle();
	$haikus  = sentencefindHaiku($article["content"]);
	$haiku   = $haikus [array_rand($haikus, 1)];
	return $haiku;
}

$h = getRandomHaikuFrom();

print json_encode($h);
