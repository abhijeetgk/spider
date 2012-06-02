<?php


class Spider {

 /**
  * cURL connection handler
  * 
  * @var resource
  * @access private
  */
  private $curl_session;

 /**
  * Root url value
  * 
  * @var array
  * @access private
  */
  private $root_url = array(
                      'scheme' => 'http',
                      'host' => 'localhost',
                      'path' => '/');

 /**
  * All found links
  * 
  * @var array
  * @access public
  */
  public $all_links = array();
  public $keyword_array=array();
 /**
  * Allowed file types
  * 
  * @var array
  * @access private
  */
  private $accept_types = array('htm', 'html', 'php', 'php5', 'aspx');

 /**
  * Verbose spidering process
  * 
  * @var boolean
  * @access private
  */
  private $verbose = false;

 /**
  * Fetched urls
  * 
  * @var integer
  * @access private
  */
  private $fetched_urls = 0;

 /**
  * Not fetched urls
  * 
  * @var integer
  * @access private
  */
  private $not_fetched_urls = 0;

 /**
  * User agent string
  * 
  * @var string
  * @access private
  */
  private $user_agent = 'Spider website 0.1';

 /**
  * Constructor
  *
  * @param string $site as root url
  * @access public
  * @return void
  */
  public function __construct ($site = '') {
    $this->setRootURL($site);
    $this->curl_session = curl_init();
   $link=mysql_connect('localhost','root','');
        @mysql_select_db('test',$link);
  }

 /**
  * Changes root url
  *
  * @param string $site as new root url
  * @access public
  * @return void
  */
  public function setRootURL($site) {
    if (!empty($site)) {
      $this->root_url = parse_url($site);
    }
  }

 /**
  * Changes verbose mode
  *
  * @param boolean $value as new verbose setting
  * @access public
  * @return void
  */
  public function setVerbose($value) {
    if (is_bool($value)) {
      $this->verbose = $value;
    }
  }

 /**
  * Allows file type being spidering
  *
  * @param string $extension
  * @access public
  * @return void
  */
  public function allowType($extension) {
    if (!empty($extension)) {
      if (!in_array($extension, $this->accept_types)) array_push($this->accept_types, $extension);
    }
  }

 /**
  * Restricts file type from being spidered
  *
  * @param string $extension
  * @access public
  * @return void
  */
  public function restrictType($extension) {
    if (!empty($extension) && in_array($extension, $this->accept_types)) {
      foreach ($this->accept_types as $key => $value) {
        if ($extension == $value) {
          $this->accept_types[$key] = null;
        }
      }
      $this->accept_types = array_filter($this->accept_types);
    }
  }

 /**
  * Checks if url allowed to be fetched
  *
  * @param string $url url of page, string $useragent as useragent string
  * @access private
  * @return boolean Returns true if url allowed to fetch and false if otherwise
  */
  private function _robotsAllowed ($url, $useragent=false) { 
    $parsed = parse_url($url);
    $agents = array(preg_quote('*'));
    if($useragent) {
      $agents[] = preg_quote($useragent);
    }
    $agents = implode('|', $agents);
    $robotstxt = @file('http://'.$parsed['host'].'/robots.txt');
    if(!$robotstxt) 
      return true;
    $rules = array();
    $ruleapplies = false;
    foreach($robotstxt as $line) {
      if(!$line = trim($line)) continue;
      if(preg_match('/User-agent: (.*)/i', $line, $match)) { 
        $ruleapplies = preg_match('/('.$agents.')/i', $match[1]);
      } 
      if($ruleapplies && preg_match('/Disallow:(.*)/i', $line, $regs)) { 
        if(!$regs[1]) return true;
        $rules[] = preg_quote(trim($regs[1]), '/');
      }
    }
    foreach($rules as $rule) {
      if(preg_match('/^'.$rule.'/', $parsed['path'])) return false;
    }
    return true; 
  } 

 /**
  * Prints fetching status
  *
  * @param boolean $type
  * @access private
  * @return void
  */
  private function _verboseStatus($type=false) {
    if ($this->verbose) {
      if ($type) {
        echo ' [OK]' . "\n";
        $this->fetched_urls++;
      } else {
        echo ' [Not fetched] (robots.txt rules, meta tags rules or error)' . "\n";
        $this->not_fetched_urls++;
      }
    }
  }

 /**
  * Fetches given url
  *
  * @access private
  * @return void
  */
  private function _fetchUrl($url) {
    if ($this->verbose) {
      echo 'Fetching ' . htmlentities($url);
    }

    if ($this->_robotsAllowed($url)) {
      curl_setopt($this->curl_session, CURLOPT_URL, $url);
      curl_setopt($this->curl_session, CURLOPT_USERAGENT, $this->user_agent);
      curl_setopt($this->curl_session, CURLOPT_HEADER, 0);
      curl_setopt($this->curl_session, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($this->curl_session, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($this->curl_session, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($this->curl_session, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($this->curl_session, CURLOPT_POST, 0);

      $result = curl_exec($this->curl_session);
      $info = curl_getinfo($this->curl_session);
      $robots = array();

      if ($info['http_code'] == 200) {
        $tags = get_meta_tags($url);
        $robots = explode(',', strtolower(str_replace(' ', '', trim($tags['robots']))));
      }

      if (!in_array('none', $robots)) {

        if (!in_array('noindex', $robots) && $info['http_code'] == 200) {
          if (!in_array($url, $this->all_links)) {
            array_push($this->all_links, $url);
            $keywords=$this->extract_keywords($url);
            $this->keyword_array[$url]=$keywords;
            
          }
          $fetched=true;
        }
        $this->_verboseStatus($fetched);

        if (!in_array('nofollow', $robots) && $info['http_code'] == 200) {

          preg_match_all('/href=\"(.*)\"/imsU', $result, $matches);
          foreach ($matches[1] as $fetch_url) {
            $tmp = @parse_url($fetch_url);
            if (!empty($tmp) && $tmp['host'] == $this->root_url['host']) {
              $url = $tmp;
              $extension = pathinfo($url['path'], PATHINFO_EXTENSION);
              if (in_array($extension, $this->accept_types)) {
                if (!in_array($url, $this->all_links)) {
                  $this->_fetchUrl($url);
                }
              }
            } else if (empty($tmp['host'])) {
              if (!empty($tmp['query'])) {
                $fetch_url = substr($fetch_url, 0, strpos($fetch_url, '?'));
              }
              $tmp_file = pathinfo($fetch_url);
              if ($tmp_file['dirname'][0] == '.' || empty($tmp_file['dirname'])) {
                $url = $this->root_url['scheme'].'://'.$this->root_url['host'].substr($this->root_url['path'], 0, -1).substr($tmp_file['dirname'], 1).'/'.$tmp_file['basename'];
                if (!empty($tmp['query'])) {
                  $url = $url . '?' . $tmp['query'];
                }
                if (empty($tmp_file['extension']) || in_array($tmp_file['extension'], $this->accept_types)) {
                  if (!in_array($url, $this->all_links)) {
                    $this->_fetchUrl($url);
                  }
                }
              }

              if ($tmp_file['dirname'][0] == '/') {
                $url = $this->root_url['scheme'].'://'.$this->root_url['host'].substr($tmp_file['dirname'], 1).'/'.$tmp_file['basename'];
                if (!empty($tmp['query'])) {
                  $url = $url . '?' . $tmp['query'];
                }
                if (empty($tmp_file['extension']) || in_array($tmp_file['extension'], $this->accept_types)) {
                  if (!in_array($url, $this->all_links)) {
                    $this->_fetchUrl($url);
                  }
                }
              }
            }
          }
        }
      }
    }
  }

 /**
  * Starts spidering
  *
  * @access public
  * @return void
  */
  public function startSpider() {
    $url = $this->root_url['scheme'].'://'.$this->root_url['host'].$this->root_url['path'];
    if (!empty($this->root_url['query'])) {
      $url = $url.'?'.$this->root_url['query'];
    }

    if ($this->verbose) {
      echo '<pre>'.
           'Started spidering on website ' . $url . ' on ' . date('Y-m-d H:i:s', time()) . "\n";
    }

    $this->_fetchUrl($url);

    if ($this->verbose) {
      echo 'Succesfully fetched ' . $this->fetched_urls . ' urls, not fetched ' .$this->not_fetched_urls. '. Finished on '. date('Y-m-d H:i:s', time()).
           '</pre>';
    }
  }

  public function extract_keywords($url) {
      /*Simple URL checking*/

if(!isset($url) || $url == '' || $url == 'http://')
{
	echo 'Please enter an url.';
}
else
{


$url=strip_tags($url);

/*we first retrieve the contente of the page using the Curl library*/
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt ($ch, CURLOPT_FILETIME, 1);
curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 2);

$store = curl_exec ($ch);


/*Error handling*/
if (curl_errno($ch))
{
print curl_error($ch);
}
else
{
	$info = curl_getinfo($ch);
	curl_close($ch);

//print <<<END
//<h1>Description for a given website</h1>
//Website: $url<br />
//END;

$ok=1;
$title='';
$description='';
$keywords='';



	/*We extract the Title from the head tags:*/
	preg_match("/<head.*>(.*)<\/head>/smUi",$store, $headers);
	if(count($headers) > 0)
	{
		/*Fetch the charset of the page*/
		if(preg_match("/<meta[^>]*http-equiv[^>]*charset=(.*)(\"|')>/Ui",$headers[1], $results))
		$charset= $results[1];
		else $charset='None';

		if(preg_match("/<title>(.*)<\/title>/Ui",$headers[1], $titles))
		{
			if(count($titles) > 0)
			{
				/*If the charset information has been extracted, we convert it to UTF-8 - Otherwise we assume it's already UTF-8*/
				if($charset == 'None')
				$title=trim(strip_tags($titles[1]));
				else
				$title=trim(strip_tags(iconv($charset, "UTF-8", $titles[1])));

			}
			else
			{
				/*If there is no title given we take the url as a title*/
				if(strlen($url) > 30)
				$title=trim(substr($url,30)).'...';
				else $title= trim($url);
			}
		}
		else
		{
			/*If there is no title given we take the url as a title*/
			if(strlen($url) > 30)
			$title=trim(substr($url,30)).'...';
			else $title= trim($url);

		}
	}
	else
	{
		$ok=0;
		echo 'No HEAD - That might not be an HTML page!';
	}



	/*Let's fetch the META description or give a description is there is not description available*/
	preg_match("|<meta[^>]*description[^>]*content=\"([^>]+)\"[^>]*>|Ui",$headers[1], $matches);
	if(count($matches) > 0)
	{
		if($charset != 'None')
		$description= trim(strip_tags(iconv($charset, "UTF-8",$matches[1])));
		else
		$description= trim(strip_tags($matches[1]));

	}
	else
	{
		preg_match("/<body.*>(.*)<\/body>/smUi",$store, $matches);
		if(count($matches) > 0)
		{
			if($charset != 'None')
			$description= trim(substr(trim(strip_tags(iconv($charset, "UTF-8",$matches[1]))),0,150));
			else
			$description= trim(substr(trim(strip_tags($matches[1])),0,150));

		}
		else
		{
			if($charset != 'None')
			$description= trim(substr(trim(strip_tags(iconv($charset, "UTF-8",$store))),0,150));
			else
			$description= trim(substr(trim(strip_tags($store)),0,150));
		}


	}

	/*Now the META keywords or some keywords which we extract from the description*/
	preg_match("|<meta[^>]*keywords[^>]*content=\"([^>]+)\"[^>]*>|Ui",$headers[1], $matches);
	if(count($matches) > 0)
	{
		if($charset != 'None')
		$keywords= trim(strip_tags(iconv($charset, "UTF-8",$matches[1])));
		else
		$keywords= trim(strip_tags($matches[1]));

	}
	else
	{
		/*We shall avoid the stopwords from the keywords*/
		$stopwords= array(' the ',' in ',' a ',' and ',' an ',' of ',' about ',' are ',' as ',' at ',' be ',' by ',' com ',' de ',' en ',' for ',' from ',' how ',' in ',' is ',' it ',' la ',' on ',' or ',' that ',' this ',' to ',' was ',' what ',' when ',' where ',' who ',' will ',' with ',' und ',' www ',' you ',' your ',' our ');

		$keywords=str_replace($stopwords," ",strtolower($description));
		$keywords=str_replace(" ",",",$keywords);

	}


	/*We print out the results*/
	if(!$ok)
	//echo '<hr><p><u>Title</u>: '.$title.'<p><u>Description</u>:'.$description.'<p><u>Keywords</u>:'.$keywords;

	{
		echo 'No title/description...';
	}

}
}
$this->insert_entry($keywords);
return  $keywords;
  }
  public function insert_entry($keywords) {
      $keyword_array=explode(",",$keywords);
      foreach($keyword_array as $s_string) { $s_string=trim($s_string);
          $query="select id from tagcloud where term='".$s_string."'";
          $q_res=mysql_query($query);
          if(mysql_num_rows($q_res)!=0) { // this means search term exists in the databse and need to increse the count
            $update_query="update tagcloud set counter=counter+1 where term='".$s_string."'";
         //   echo " update query ".$update_query."<br>";
            mysql_query($update_query);
          }
          else { // search term is not exists in the database and need to insert the term
          $ins_query="insert into tagcloud(term,counter) values('".$s_string."',1)";
          mysql_query($ins_query);
      //    echo "insert query ".$ins_query."<br>";
          }
      }
  }

  public function fetch_urls($site) {
       $original_file = file_get_contents($site);
  $stripped_file = strip_tags($original_file, "<a>");
  preg_match_all("/<a(?:[^>]*)href=\"([^\"]*)\"(?:[^>]*)>(?:[^<]*)<\/a>/is", $stripped_file, $matches);
  foreach($matches[1] as $urls) {
       $keywords=$this->extract_keywords($urls);
       $this->keyword_array[$urls]=$keywords;
  }
  }
  
}
?>