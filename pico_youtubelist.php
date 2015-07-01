<?php
/**
 * Pico Youtube List
 * Youtubeの動画再生リストを取得し、ページとして追加する自動更新モジュール
 *
 * @author TakamiChie
 * @link http://onpu-tamago.net/
 * @license http://opensource.org/licenses/MIT
 * @version 1.0
 */
class Pico_YoutubeList {
  
  private $settings;
  
  public function run($settings) {
    if(empty($settings["youtube"]) || 
      empty($settings["youtube"]["apikey"]) ||
      empty($settings["youtube"]["channels"])) {
      return;
    }
    $this->settings = $settings;
    
    $apikey = $settings["youtube"]["apikey"];
    $channels = $settings["youtube"]["channels"];

    foreach($channels as $channel){
      $this->loadchannel($apikey, $channel);
    }
  }
  
  private function loadchannel($apikey, $channel) {
    if(empty($channel) ||
      empty($channel["channel"]) ||
      empty($channel["directory"])){
      return;
    }
    $cdir = ROOT_DIR . $this->settings["content_dir"] . $channel["directory"];
    $cachedir = LOG_DIR . "youtube/";
    $cachefile = $cachedir . $channel["channel"] . ".json";
    if(!file_exists($cachedir)){
      mkdir($cachedir, "0500", true);
    }
    $query = array(
      "part" => "snippet,status",
      "channelId" => $channel["channel"],
      "maxResults" => 50,
      "key" => $apikey,
    );
		$list_url = "https://www.googleapis.com/youtube/v3/playlists?" . http_build_query($query);
		$base_playlist = "https://www.youtube.com/playlist?list=";

    // 以前作成したファイルがあれば全削除
    if($handle = opendir($cdir)){
      while(false !== ($file = readdir($handle))){
        if(!is_dir($file) && $file != "index.md"){
          unlink($cdir. "/" . $file);
        }
      }
      closedir($handle);
    }
    /* テキストファイル作成処理 */
    try{
      // まずはJSON読み込み
      $content = $this->curl_getcontents($list_url);
      file_put_contents($cachefile, $content);
      $json = json_decode($content, true);
      foreach($json["items"] as $j){
        // 非Publicなもの、説明文が空なものは公開しない
        if($j["status"]["privacyStatus"] != "public" || empty($j["snippet"]["localized"]["description"])) continue;
        // mdファイル作成
        $page = "/*\n";
        $page .= sprintf("  Title: %s\n", $j["snippet"]["localized"]["title"]);
        $page .= sprintf("  Author: %s\n", $j["snippet"]["channelTitle"]);
        $page .= sprintf("  Date: %s\n", $j["snippet"]["publishedAt"]);
        $page .= sprintf("  Description: %s\n", str_replace(array("\n", "\r"), " ", $j["snippet"]["localized"]["description"]));
        $page .= sprintf("  URL: %s\n", $base_playlist . $j["id"]);
        $page .= sprintf("  Image: %s\n", $j["snippet"]["thumbnails"]["medium"]["url"]);
        $page .= "*/\n";
        $page .= $j["snippet"]["localized"]["description"];

        file_put_contents($cdir . $j["id"] . ".md", $page);
      }
    }catch(Exception $e){
      $page = "/*\n";
      $page .= sprintf("  Title: %s\n", "Youtube Access Error");
      $page .= sprintf("  Description: %s\n", "Youtube Access Error");
      $page .= "*/\n";
      $page .= "Githubに接続できませんでした。\n";
      $page .= $e->getMessage();
      file_put_contents($cdir . "error.md", $page);
    }
	}
  
  private function curl_getcontents($url)
  {
    $ch = curl_init();
    curl_setopt_array($ch, array(
      CURLOPT_URL => $url,
      CURLOPT_TIMEOUT => 10,
    	CURLOPT_CUSTOMREQUEST => 'GET',
    	CURLOPT_SSL_VERIFYPEER => FALSE,
    	CURLOPT_RETURNTRANSFER => TRUE,
    	CURLOPT_USERAGENT => "Pico"));

    $content = curl_exec($ch);
    if(!$content){
      throw new Exception(curl_error($ch));
    }
    curl_close($ch);
    return $content;
  }
}

?>