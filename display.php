<link rel="stylesheet" type="text/css" media="all" href="css/style.css" /> 
<?php
include_once('tagcloud.php');
$tagcloud=new tagcloud();
$terms=$tagcloud->get_tag_cloud();
$maximum=$tagcloud->maximum;
// start the output to the page
echo "
<div id=\"tagcloud\">
<div>\n";


foreach ($terms as $k) // start looping through the tags
{
    // determine the popularity of this term as a percentage
    $percent = floor(($k['counter'] / $maximum) * 100);

    // determine the class for this term based on the percentage
    if ($percent < 20){$class = 'smallest';} 
    elseif ($percent >= 20 and $percent < 30) { $class = 'small';}
    elseif ($percent >= 30 and $percent < 40) { $class = 'small-1';}
    elseif ($percent >= 40 and $percent < 50) {$class = 'medium';}
    elseif ($percent >= 50 and $percent < 60) {$class = 'medium-1';}
    elseif ($percent >= 60 and $percent < 70) {$class = 'large';}
    elseif ($percent >= 70 and $percent < 80) {$class = 'large-1';}
    else {$class = 'largest';}

    // output this term
    echo "<span class=\"$class\"><a href=\"http://www.transpacific.in\">" . $k['term'] . "</a></span>\n ";
}

// close the output
echo "</div>
</div>\n";