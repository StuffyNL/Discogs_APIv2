<?php

/**
Usage:
$albumid   = release ID on Discogs
$useragent = name of the script, or something else
$cachedir  = Directory to cache json file

$array = The output array. (This is NOT the same array as what's in the JSON file! It's a bit simplefied)

PHP Code:

    require_once('class_discogs.php');
    $dsg   = new discogs($albumid, $useragent, $cachedir);
    $array = $dsg->getdata();
    $raw_array = $dsg->rawdata();

**/

/** BEGIN CLASS **/
class discogs {
  private $dgs_array;
  public $data = array();
  public $cache;


  function __construct($release, $useragent, $cachedir = '../cache/') {
    $url = "https://api.discogs.com/releases/".$release;
    $fln = $cachedir.$release.'.json';

    if (file_exists($fln)) {
      $t = stat($fln);
      if (($t['mtime']+(3600*24))<time()) {
        $content     = $this->get_dgs_data($url, $version);
        file_put_contents($fln, $content);
        $this->cache = 'updated file';
        }
      else {
        $content     = file_get_contents($fln);
        $this->cache = 'used file';
        }
      }
    else {
      $content     = $this->get_dgs_data($url, $version);
      file_put_contents($fln, $content);
      $this->cache = 'no file';
      }
    
    $this->dgs_array  = json_decode($content, true);
    }


  function get_dgs_data($url, $useragent) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
    $content = curl_exec($curl);
    curl_close($curl);
    return $content;
    }


  function remove_nr($a) {
    $i = 1;
    $repl_arr = array();
    while ($i <= 50) {
      $repl_arr[] = " ($i)";
      $i++;
      }
    return str_replace($repl_arr, '', $a);
    }
      
  function getarray($ar) {

    $this->data['stat']   = $ar['status'];
    foreach($ar['styles'] as $st) $this->data['style'] .= ((strlen($this->data['style'])!=0) ? ', ' : '').$st;
    foreach($ar['genres'] as $st) $this->data['genre'] .= ((strlen($this->data['genre'])!=0) ? ', ' : '').$st;

    $this->data['artist']='';
    foreach($ar['artists'] as $art) {
      $name                  = $this->remove_nr($art['name']);
      $name                  = (((substr($name, (strlen($name)-5), strlen($name))) == ', The')?"The ".substr($name, 0, (strlen($name)-5)) : $name);
      $this->data['artist'] .= $name.((strlen($art['join']) > 0) ? ' '.$art['join'].' ' : '');
      }

    $this->data['title']  = htmlentities($ar['title'], ENT_COMPAT, 'UTF-8');
    
    $this->data['label']  = $this->remove_nr($ar['labels'][0]['name']);
    //$this->data['label']  = $ar['labels'][0]['name'];
    $this->data['catlog'] = $ar['labels'][0]['catno'];

    foreach($ar['formats'] as $st) {
      foreach($st['descriptions'] as $d)
        $this->data['frmdsc'] .= ((strlen($this->data['frmdsc']) != 0) ? ', ' : '').htmlentities($d, ENT_COMPAT, 'UTF-8');
        
      $this->data['format'] = htmlentities($st['name'], ENT_COMPAT, 'UTF-8');
      $this->data['frmqty'] = $st['qty'];
      }
    $this->data['cntry']  = $ar['country'];
    $this->data['rdate']  = $ar['year'];
    $this->data['rnote']  = $ar['notes'];

    foreach($ar['tracklist'] as $trk) {
      $name = $role = $addtrack = $artist = '';
      
      if(is_array($trk['artists'])) {
        foreach($trk['artists'] as $art) {
          $name    = $this->remove_nr($art['name']);
          $name    = (((substr($name,(strlen($name)-5), strlen($name))) == ', The') ? "The ".substr($name, 0, (strlen($name)-5)) : $name);
          $artist .= $name.((strlen($art['join']) > 0) ? ' '.$art['join'].' ' : '');
          }
        }
      $artist = (((substr($artist, (strlen($artist)-5), strlen($artist))) == ', The') ? "The ".substr($artist, 0, (strlen($artist)-5)) : $artist);
      
      if ($trk['position'] == '') $cdlist[] = htmlentities($trk['title'], ENT_COMPAT, 'UTF-8');
      else {
      
        if (is_array($trk['extraartists'])) foreach($trk['extraartists'] as $extr) {
          $name     = $this->remove_nr($extr['name']);
          $name     = (((substr($name, (strlen($name)-5), strlen($name))) == ', The') ? "The ".substr($name, 0, (strlen($name)-5)) : $name);
          $role     = ($role != $extr['role']) ? $extr['role'] : 'and';
          $addtrack.= (($addtrack != '') ? ' ' : '').$role." ".$name.(($extr['join'] != '') ? " ".$extr['join'] : "");
          }
        
        $tracks[] = array(
          'no'       => $trk['position'],
          'artist'   => $artist,
          'title'    => htmlentities($trk['title'], ENT_COMPAT, 'UTF-8'),
          'length'   => $trk['duration'],
          'addtrack' => $addtrack
          );
        }
      }

    $this->data['tracks']  = $tracks;
    $cdlist = str_replace('CD', 'Disc', $cdlist);
    $this->data['cdlist']  = $cdlist;

    foreach($ar['extraartists'] as $s) {
      $name = $s['name'];
      $name = $this->remove_nr($name);
      $name = (((substr($name, (strlen($name)-5), strlen($name))) == ', The') ? "The ".substr($name, 0, (strlen($name)-5)) : $name);

      if($s['role'] == $role)  $credit     .= ', '.$s['name'].((strlen($s['join']) > 0) ? ' '.$s['join'].' ' : "");
      else $credit .= "\n".$s['role'].': '.$s['name'].((strlen($s['join']) > 0) ? ' '.$s['join'].' ' : "");

      $role = $s['role'];
      $s['name'] = $name;
      }
    $this->data['credits'] = htmlentities($credit, ENT_COMPAT, 'UTF-8');

    $this->data['rawdatalen'] = strlen($ar);
    return $this->data;
    }


  function getdata() {
    return $this->getarray($this->dgs_array);
    }


  function rawdata() {
    return $this->dgs_array;
    }

  }

/** EINDE CLASS **/

?>
