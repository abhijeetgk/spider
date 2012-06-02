<?php
class Tagcloud {
    public $maximum;
    public function __construct() {
        //$link=mysql_connect('localhost','root',''); // local settings
        $link=mysql_connect('localhost','root','');// live
        @mysql_select_db('test',$link);
    }
    public function insert_data() {

    }
    public function get_tag_cloud(){
        $terms = array(); // create empty array
        $maximum = 0; // $maximum is the highest counter for a search term
        $query = mysql_query("SELECT term, counter FROM tagcloud ORDER BY counter DESC limit 170");
        while ($row = mysql_fetch_array($query))
        {
        $term = $row['term'];
        $counter = $row['counter'];

        // update $maximum if this term is more popular than the previous terms
        if ($counter > $maximum) $maximum = $counter;

        $terms[] = array('term' => $term, 'counter' => $counter);

        }

        // shuffle terms unless you want to retain the order of highest to lowest
        $this->maximum=$maximum;
        shuffle($terms);
        return $terms;
        }
}