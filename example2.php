<?php
set_time_limit(0);
error_reporting(0);
$link=mysql_connect('localhost','root','');
@mysql_select_db('test',$link);
  $original_file = file_get_contents("http://www.simplysapphires.com/html/supplies.html");
  $stripped_file = strip_tags($original_file, "<a>");
  preg_match_all("/<a(?:[^>]*)href=\"([^\"]*)\"(?:[^>]*)>(?:[^<]*)<\/a>/is", $stripped_file, $matches);

  //DEBUGGING

  //$matches[0] now contains the complete A tags; ex: <a href="link">text</a>
  //$matches[1] now contains only the HREFs in the A tags; ex: link

  header("Content-type: text/plain"); //Set the content type to plain text so the print below is easy to read!
  print_r($matches[1]); //View the array to see if it worked

  foreach($matches[1] as $url){
      $tags=get_meta_tags($url);
      $metakeywords=explode(",",$tags['keywords']);
      foreach ($metakeywords as $keyword) {
          $keyword=trim($keyword);
          addkeyword($keyword);
      }
     echo "keywors added for url ".$url."<br>\n";
  }

  function addkeyword($keyword) {
      $query="select id from tagcloud where term='".$keyword."'";
      $res=mysql_query($query);
      if(mysql_num_rows($res)>0) {
          $updatequery="update tagcloud set counter=counter+1 where term='".$keyword."'";
          mysql_query($updatequery);
          echo $updatequery."<br>";
      }
      else {
          $insertquery="insert into tagcloud(term,counter)values('".$keyword."',1)";
          mysql_query($insertquery);
          echo $insertquery."<br>";
      }
  }

?>