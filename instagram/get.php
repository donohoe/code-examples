<?php
/*
	Get Instagram

Example:
	https://www.instagram.com/lisawhittaker_realestate/

	Posts:
	https://www.instagram.com/p/BLJ32KsDcrF/
	https://www.instagram.com/p/BK5_UO9D0BO/

Local dev:
	http://localhost:7020/instagram/get.php?ids=donohoe
	http://localhost:7020/instagram/get.php?ids=donohoe,nytimes
*/

date_default_timezone_set('America/New_York');

header("Content-Type: application/json; charset=utf-8");
header("X-Clacks-Overhead: GNU Terry Pratchett");

include("../lib/phpQuery/phpQuery.php");

class Instagram {

    function Run() {

		$instagram_ids = $this->getValidIds();

		$hashKey  = "key_" . intval( implode("", $instagram_ids), 36);
		$response = array();

		if (class_exists('memcached')) {

			$mem = new memcached();
			$mem->addServer("localhost", 11211);

			$result = $mem->get( $hashKey );
			if ($result) {
			//	Get from cache...
				$response = $result;
				$response['status']['message'] = "ok - from cache";
			} else {
			//	Not in cache, get the data, and then try to cahce it
				$response = $this->getData($instagram_ids);

				$oneDay  = 60*60*24;
				$oneHour = 60*60;
				$oneMin  = 60;

				$mem->set($hashKey, $response, $oneMin) or die("Nothing Saved");

				$response['status']['message'] = "ok - was not cache";
			}

		} else {
			$response = $this->getData($instagram_ids);
			$response['status']['message'] = "ok. no cache. direct";
		}

		print json_encode($response);
	}

	function getValidIds() {
		$valid_ids = array();
		$instagram_ids = isset($_GET['ids']) ? $_GET['ids'] : "";

		if (!empty($instagram_ids)) {
			$instagram_ids = explode(",", $instagram_ids);
			foreach($instagram_ids as $id) {
				if (!preg_match('/[^a-z_\-0-9]/i', $id) && !empty($id)) {
					$valid_ids[] = $id;
				}
			}
		}
		return $valid_ids;
	}

	function getData($instagram_ids) {

		$data = array(
			"items"  => array(),
			"status" => array(
				"code"    => 200,
				"message" => "ok",
				"haiku"   => $this->response_200(),
				"updated" => date('r')
			)
		);

		foreach($instagram_ids as $id) {
			$url = "https://www.instagram.com/" . $id . "/";
			$data['items'][] = $this->getItem($url);
		}

		return $data;
	}

	function getItem($url) {

		$item = array();
		if ($this->startsWith( $url, "https://www.instagram.com/" )) {

			$html = file_get_contents($url);
			phpQuery::newDocument($html, $contentType = null);

			$scriptText = "";
			foreach(pq('script') as $script) {
				$textContent = $script->textContent;
				if ($this->startsWith( $textContent, "window._sharedData" )) {
					$scriptText = trim (str_replace( "window._sharedData =", "", $textContent ));
					$scriptText = substr($scriptText, 0, -1);
					$item = $this->filterItemData( $scriptText );
					break;
				}
			}
		}

		return $item;
	}

	function filterItemData($data) {

		$data = json_decode($data, true);
		$items = array();

		if (isset($data['entry_data'])) {
			if (isset($data['entry_data']['ProfilePage'][0]['user']['media'])) {
				$media = $data['entry_data']['ProfilePage'][0]['user']['media'];

				foreach ($media['nodes'] as $node) {

					$thumbnails = $node["thumbnail_resources"];
					$thumbnail = "";
					if (isset($thumbnails[3])) {
						$thumbnail = $thumbnails[3];
					}

					$items[] = array(
						"id" => $node["id"],
						"link" => "https://www.instagram.com/p/" . $node["code"] . "/",
						"caption" => $node["caption"],
						"thumbnail" => $thumbnail
					);
				}

			}
		}

		return $items;
	}

	function convertImageToBase64($src) {
		$type = pathinfo($src, PATHINFO_EXTENSION);
		$data = file_get_contents($src);
		return 'data:image/' . $type . ';base64,' . base64_encode($data);
	}

/*	Utilities */

/*	http://stackoverflow.com/a/17852480/24224 */
	function truncate($str, $width) {
		return strtok(wordwrap($str, $width, "&hellip;\n"), "\n");
	}

	private function startsWith($haystack, $needle) {
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
	}

	private function endsWith($haystack, $needle) {
		return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
	}

	private function response_200() {
		$msgs = array(
			"Falcon at light speed / wit banter swagger shoot first/ carbonite classic",
			"We are no strangers / Never gonna give you up / Ain't gonna let go",
			"C-Beams in the dark / Ships burning near Orion / Lost like tears in rain",
			"A world with two suns / You'd think they'd have two shadows / What's up, Tatooine?",
			"If time is money / Are ATMs time machines? / our mind has been blown",
			"1981 / I'm into Space Invaders / 2600",
			"This tiny haiku / is just sixty characters / ideal for Twitter.",
			"When I read haiku, / I hear it in the voice of / William Shatner."
		);
		return $msgs[array_rand($msgs, 1)];
	}
}

$event = new Instagram;
$response = $event->Run();

