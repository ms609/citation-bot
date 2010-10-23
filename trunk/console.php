#!/usr/bin/php
<?
// $Id$

$abort_mysql_connection = true; // Whilst there's a problem with login

error_reporting(E_ALL^E_NOTICE);
$slowMode = false;
$fastMode = false;
$accountSuffix = '_1'; // Keep this before including expandFns
include("expandFns.php");
$htmlOutput = false;
$editInitiator = '[Bot task 6 / 7: Pu' . revisionID() . ']';
$ON = true; // Override later if necessary
define ("START_HOUR", date("H"));


function nextPage($page){
  // touch last page
  if ($page) {
    touch($page);
  }

  // Get next page
  global $ON, $STOP;
	if (!$ON || $STOP) die ("\n** EXIT: Bot switched off.\n");
  if (date("H") != START_HOUR) die ("\n ** EXIT: It's " . date("H") . " o'clock!\n");
	//$db = udbconnect();
	$result = mysql_query ("SELECT page FROM citation ORDER BY fast ASC") or die(mysql_error());
	$result = mysql_query("SELECT page FROM citation ORDER BY fast ASC") or die (mysql_error());
	$result = mysql_fetch_row($result);
  mysql_close($db);
	return $result[0];
}

#$STOP = true;
$ON = false; // Uncomment this line to set the bot onto the Zandbox, switched off.

$page = "User:DOI bot/Zandbox";  // Leave this line as is.  It'll be over-written when the bot is turned on.
if ($ON) {
  echo "\n Fetching first page from backlog ... ";
  $page = nextPage();
  echo " done. ";
}
#$page = " Template:Cite doi/10.1002.2F.28SICI.291097-0290.2819980420.2958:2.2F3.3C121::AID-BIT2.3E3.0.CO.3B2-N";
$ON = true; // Uncomment this line to test edits in the Zandbox; but remember to break the bot after it touches the page or it'll keep on going!
// The line to swtich between active & sandbox modes is in the comment block above.
#$page = "";
#$slowMode = true;


$alphabet = array("", "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", );
// Returns
function ref_templates($page_code, $type) {
  global $alphabet;
  while (false !== ($ref_template = extract_template($page_code, "ref $type"))) {
    print "\n\n";
    $ref_parameters = extract_parameters($ref_template);
    $ref_id = $ref_parameters[1] ? $ref_parameters[1][0] : $ref_parameters["unnamed_parameter_1"][0];
    $template = create_cite_template($type, $ref_id);
    if ($template["last1"] && $template["year"]) {
      while (preg_match("~<ref name=['\"]?"
              . preg_quote($template["last1"] . $template["year"] . $alphabet[$i++])
                      . "['\"/]*>~i", $page_code)) {}
      $ref_content = "<ref name=\"{$template["last1"]}{$template["year"]}{$alphabet[--$i]}\">"
                   . $ref_template
                   . "</ref>";
    } else {
      $ref_content = "<ref>$ref_template</ref>";
    }
    $page_code = str_replace($ref_template, str_ireplace("ref $type", "cite $type", $ref_content), $page_code);
  }
  return $page_code;
}

function create_cite_template($type, $id) {
  print ("\n -- Create cite template at Template:Cite $type/$id");
  return array("last1" => "Smith", "year" => "2010");
}

function combine_duplicate_references($page_code) {
  preg_match_all("~<ref\s*name=[\"']?([^\"'>]+)[\"']?\s*REMOVETHISWORD/>~", $page_code, $empty_refs);
    // match 1 = ref names
  if (preg_match_all("~<ref(\s*name=[\"']?([^\"'>]+)[\"']?\s*)?>(([^<]|<(?!ref))*?)</ref>~i", $page_code, $refs)) {
    // match 0 = full ref; 1 = redundant; 2 = ref name; 3 = ref content 4 = redundant
    $refs_content = $refs[3];
    foreach ($refs[3] as $i => $content) {
      $refs_content[$i] = "!--didnt_match_this--!";
      if (false !== ($key = array_search($content, $refs_content))) {
        $duplicate_references[] = $refs[0][$key]; // This way round the later reference gets deleted
        $duplicate_names[] = $refs[2][$key]; // This way round the later reference gets deleted
        $duplicate_of[] = $refs[2][$i];
      }
    }
    foreach ($duplicate_references as $i => $duplicate) {
      print ("\n replacing reference $duplicate \n");

      $page_code = str_replace($duplicate, "<ref name=\"{$duplicate_of[$i]}\" />", 
                   preg_replace("~<ref\s*name=[\"']?" . preg_quote($duplicate_names[$i])
                                . "[\"']?(\s*/>)~", "<ref name=\"" . $duplicate_of[$i] . "$1",
                          $page_code));
    }
  }
  return $page_code;
}

//
//include("expand.php");// i.e. GO!

$end_text = combine_duplicate_references(ref_templates(getRawWikiText($page), "doi"));
//$end_text = ref_templates($end_text, "pmid");
print "\n" . $end_text;
//write($page, $end_text, "Testing routine for Task #6");

print "\n Done. \n";
