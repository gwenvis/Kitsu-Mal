<?php

function exportuser($user) {
    
    $userid = getuserid($user);
    $library = getuserlibrary($userid);
    return $library;
}

function initcurl() 
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/vnd.api+json",
        "Accept: application/vnd.api+json"
    ));
    return $ch;
}

function getuserid($user) 
{
    $ch = initcurl();
    $url = "https://kitsu.io/api/edge/users?filter[name]=" . $user;
    curl_setopt($ch, CURLOPT_URL, $url);

    $response = curl_exec($ch);
    $json = json_decode($response);
    return $json->data[0]->id;
}

function getuserlibrary($userid) 
{
    $offset = 0;
    $pagelimit = 5;
    $animeremaining = true;

    $ch = initcurl();

    $malanime = array();
    $failedlist = array();

    while($animeremaining)
    {
        $url = "https://kitsu.io/api/edge/library-entries?filter[userId]=" . $userid
            . "&page[offset]=$offset&page[limit]=$pagelimit&filter[media_type]=Anime";
        curl_setopt($ch, CURLOPT_URL, $url);

        $response = curl_exec($ch);
        $json = json_decode($response);
        $animearray = $json->data;

        if(count($animearray) == 0) {
            break;
        }

        foreach ($animearray as &$anime) {
            $attributes = $anime->attributes;
            $a = new MyAnimeListAnimeObject();
            $a->ensurerating($attributes->rating);
            $a->episodeswatched = $attributes->progress;
            $id = $a->getanimeid($anime->id);
            $a->status = $a->convertstatus($attributes->status);

            if ($id === FALSE) {
                array_push($failedlist, 'Anime of id ' . $anime->id . ' failed.');
                continue;
            }

            $a->animeid = $id;

            array_push($malanime, $a);
        }

        $offset += $pagelimit;
    }

    $xml = makeXML($malanime);
    echo "<pre>".print_r($failedlist, true)."</pre>";

    curl_close($ch);

    file_put_contents("exports/$userid.xml", $xml);
    return "$userid.xml";
}

function makeXML($animearray)
{
    $importxml = <<<XML
<?xml version="1.0" encoding="UTF-8" ?> <myanimelist>
            <myinfo>
				<user_id>0</user_id>
				<user_name>no one you dimwit</user_name>
				<user_export_type>1</user_export_type>
				<user_total_anime>0</user_total_anime>
				<user_total_watching>0</user_total_watching>
				<user_total_completed>0</user_total_completed>
				<user_total_onhold>0</user_total_onhold>
				<user_total_dropped>0</user_total_dropped>
				<user_total_plantowatch>0</user_total_plantowatch>
			</myinfo>
XML;
    $updateonimport = isset($_GET['updateon']) ? 1 : 0;

    foreach($animearray as $anime)
    {
        $id = $anime->animeid;
        $status = $anime->status;
        $episodeswatched = $anime->episodeswatched;
        $rewatching = $anime->rewatching;
        $rewatchamount = $anime->rewatchamount;
        $score = $anime->rating;
        $rewatchingep = $rewatching == 1 ? $episodeswatched : 0;
        $name = $anime->animename;

        $importxml .= <<<XML
        <anime>
<series_animedb_id>$id</series_animedb_id>
<series_title><![CDATA[$name]]></series_title>
<series_type>I don't know</series_type>
<series_episodes>500000</series_episodes>
<my_id>0</my_id>
<my_watched_episodes>$episodeswatched</my_watched_episodes>
<my_start_date>0000-00-00</my_start_date>
<my_finish_date>0000-00-00</my_finish_date>
<my_rated></my_rated>
<my_score>$score</my_score>
<my_dvd></my_dvd>
<my_storage></my_storage>
<my_status>$status</my_status>
<my_comments><![CDATA[]]></my_comments>
<my_times_watched>$rewatchamount</my_times_watched>
<my_rewatching>$rewatching</my_rewatching>
<my_rewatching_ep>$rewatchingep</my_rewatching_ep>
<my_rewatch_value></my_rewatch_value>
<my_tags></my_tags>
<update_on_import>1</update_on_import>
</anime>
XML;

    }

    $importxml .= '</myanimelist>';

    return $importxml;
}

class AnimeObject
{
    public $animename = "";
    public $episodeswatched = 0;
    public $rewatching = 0;
    public $rewatchamount = 0;
    public $notes = "";
    public $status = "Watching";
    public $rating = 5.0;
    public $progress = 0;
}

class MyAnimeListAnimeObject extends AnimeObject
{
    public $animeid = 0;

    function ensurerating($rating)
    {
        $this->rating = floor($rating * 2);
        if($this->rating < 0)
            $this->rating = 0;
        else if($this->rating > 10)
            $this->rating = 10;
    }

    function convertstatus($status)
    {
        switch($status)
        {
            case "current":
                return "Watching";
            case "completed";
                return "Completed";
            case "planned":
                return "Plan to Watch";
            case "on_hold":
                return "On-Hold";
            case "dropped":
                return "Dropped";
        }
    }

    function addcomment($comment)
    {

    }

    function getanimeid($kitsuid)
    {
        $ch = initcurl();

        curl_setopt($ch, CURLOPT_URL, "https://kitsu.io/api/edge/library-entries/$kitsuid/media");

        $response = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($response);
        $this->animename = $json->data->attributes->canonicalTitle;
        return $this->maltoid($json->data->id);
    }

    function maltoid($id)
    {
        $ch = initcurl();

        curl_setopt($ch, CURLOPT_URL, "https://kitsu.io/api/edge/anime/$id/mappings");

        $response = curl_exec($ch);

        if(strlen($response) < 10 || $response === FALSE)
            return FALSE;

        curl_close($ch);

        try {
            $json = json_decode($response);
            if(isset($json->data[0]->attributes->externalId))
               return $json->data[0]->attributes->externalId;
            return FALSE;
        }
        catch(Exception $e) {
            return FALSE;
        }
    }

}
