<?php
/*$Id$*/
/* Treats comments, templates and references as objects */

include('temp_parameter_list.php'); // #TODO DELETE

define ('ref_regexp', '~<ref.*</ref>~u');
define ('refref_regexp', '~<ref.*/>~u');

class Item {
  protected $rawtext;
  public $occurrences;
}

class Comment extends Item {
  const placeholder_text = '# # # Citation bot : comment placeholder %s # # #';
  const regexp = '~<!--.*-->~us';
  
  public function parse_text($text) {
    $this->rawtext = $text;
  }
  
  public function parsed_text() {
    return $this->rawtext;
  }
}

class Short_Reference extends Item {
  const placeholder_text = '# # # Citation bot : short ref placeholder %s # # #';
  const regexp = '~<ref\s[^>]+?/>~s';
  
  public $start, $end, $attr;
  protected $rawtext;
  
  public function parse_text($text) {
    preg_match('~(<ref\s+)(.*)(\s*/>)~', $text, $bits);
    $this->start = $bits[1];
    $this->end = $bits[3];
    $bits = explode('=', $bits[2]);
    $next_attr = array_shift($bits);
    $last_attr = array_pop($bits);
    foreach ($bits as $bit) {
      preg_match('~(.*\s)(\S+)$~', $bit, $parts);
      $this->attr[$next_attr] = $parts[1];
      $next_attr = $parts[2];
    }
    $this->attr[$next_attr] = $last_attr;
  }
  
  public function parsed_text() {
    foreach ($this->attr as $key => $value) {
      $middle .= $key . '=' . $value;
    }
    return $this->start . $middle . $this->end;
  } 
}

class Long_Reference extends Item {
  const placeholder_text = '# # # Citation bot : long ref placeholder %s # # #';
  const regexp = '~<ref\s[^/>]+?>.*?<\s*/\s*ref\s*>~s';
  
  protected $open_start, $open_attr, $open_end, $content, $close;
  protected $rawtext;
  
  public function parse_text($text) {
    preg_match('~(<ref\s+)(.*)(\s*>)(.*?)(<\s*/\s*ref\s*>)~s', $text, $bits);
    $this->rawtext = $text;
    $this->open_start = $bits[1];
    $this->open_end = $bits[3];
    $this->content = $bits[4];
    $this->close = $bits[5];
    $bits = explode('=', $bits[2]);
    $next_attr = array_shift($bits);
    $last_attr = array_pop($bits);
    foreach ($bits as $bit) {
      preg_match('~(.*\s)(\S+)$~', $bit, $parts);
      $this->attr[$next_attr] = $parts[1];
      $next_attr = $parts[2];
    }
    $this->attr[$next_attr] = $last_attr;
  }
  
  public function parsed_text() {
    foreach ($this->attr as $key => $value) {
      $middle .= $key . '=' . $value;
    }
    return $this->open_start . $middle . $this->open_end . $this->content . $this -> close;
  }
  
}
  
class Template extends Item {
  const placeholder_text = '# # # Citation bot : template placeholder %s # # #';
  const regexp = '~\{\{(?:[^\{]|\{[^\{])+?\}\}~s';
  
  protected $name, $param;
  
  public function parse_text($text) {
    $this->rawtext = $text;
    $pipe_pos = strpos($text, '|');
    if ($pipe_pos) {
      $this->name = substr($text, 2, $pipe_pos-2);
      $this->split_params(substr($text, $pipe_pos + 1, -2));
    } else {
      $this->name = substr($text, 2, -2);
      $this->param = NULL;
    }
  }
  
  public function process() {
    switch ($this->wikiname()) {
      case 'cite web': 
        $this->useUnnamedParams();
      break;
    }
  }
  
  public function blank($param) {
    return trim($this->get($param)) == false;
  }
  
  public function addIfnew($param, $value) {
    if (substr($param, -4) > 0 || substr($param, -3) > 0 || substr($param, -2) > 30) {
      // Stop at 30 authors - or page codes will become cluttered! 
      $this->addIfNew('displayauthors', 30);
      return false;
    }
    preg_match('~\d+$~', $param, $auNo); $auNo = $auNo[0];
    switch ($param) {
      case "editor": case "editor-last": case "editor-first":
        $param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
        if ($this->blank('editor') && $this->blank("editor-last") && $this->blank("editor-first") && trim($value) != false)
          return $this->set($param, $value); 
        else return false;
      case "author": case "author1": case "last1": case "last": case "authors": // "authors" is automatically corrected by the bot to "author"; include to avoid a false positive.
        $param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
        if ($this->blank("last1") && $this->blank("last") && $this->blank("author") && $this->blank("author1") && $this->blank("editor") && $this->blank("editor-last") && $this->blank("editor-first") && trim($value) != false) {
          if (strpos($value, ',')) {
            $au = explode(',', $value);
            $this->set($param, formatSurname($au[0]));
            return $this->set('first' . (substr($param, -1) == '1' ? '1' : ''), formatForename(trim($au[1])));
          } else {
            return $this->set($param, $value);
          }
        }
      return false;
      case "first": case "first1":
        if ($this->blank("first") && $this->blank("first1") && $this->blank("author") && $this->blank('author1'))
          return $this->set($param, $value);
      return false;
      case "coauthor": case "coauthors":
        $param = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $param);
        if ($this->blank("last2") && $this->blank("coauthor") && $this->blank("coauthors") && $this->blank("author") && trim($value) != "")
          return $this->set($param, $value);
          // Note; we shouldn't be using this parameter ever....
      return false;
      case "last2": case "last3": case "last4": case "last5": case "last6": case "last7": case "last8": case "last9":
      case "last10": case "last20": case "last30": case "last40": case "last50": case "last60": case "last70": case "last80": case "last90":
      case "last11": case "last21": case "last31": case "last41": case "last51": case "last61": case "last71": case "last81": case "last91": 
      case "last12": case "last22": case "last32": case "last42": case "last52": case "last62": case "last72": case "last82": case "last92": 
      case "last13": case "last23": case "last33": case "last43": case "last53": case "last63": case "last73": case "last83": case "last93": 
      case "last14": case "last24": case "last34": case "last44": case "last54": case "last64": case "last74": case "last84": case "last94": 
      case "last15": case "last25": case "last35": case "last45": case "last55": case "last65": case "last75": case "last85": case "last95": 
      case "last16": case "last26": case "last36": case "last46": case "last56": case "last66": case "last76": case "last86": case "last96": 
      case "last17": case "last27": case "last37": case "last47": case "last57": case "last67": case "last77": case "last87": case "last97": 
      case "last18": case "last28": case "last38": case "last48": case "last58": case "last68": case "last78": case "last88": case "last98": 
      case "last19": case "last29": case "last39": case "last49": case "last59": case "last69": case "last79": case "last89": case "last99": 
      case "author2": case "author3": case "author4": case "author5": case "author6": case "author7": case "author8": case "author9":
      case "author10": case "author20": case "author30": case "author40": case "author50": case "author60": case "author70": case "author80": case "author90":
      case "author11": case "author21": case "author31": case "author41": case "author51": case "author61": case "author71": case "author81": case "author91": 
      case "author12": case "author22": case "author32": case "author42": case "author52": case "author62": case "author72": case "author82": case "author92": 
      case "author13": case "author23": case "author33": case "author43": case "author53": case "author63": case "author73": case "author83": case "author93": 
      case "author14": case "author24": case "author34": case "author44": case "author54": case "author64": case "author74": case "author84": case "author94": 
      case "author15": case "author25": case "author35": case "author45": case "author55": case "author65": case "author75": case "author85": case "author95": 
      case "author16": case "author26": case "author36": case "author46": case "author56": case "author66": case "author76": case "author86": case "author96": 
      case "author17": case "author27": case "author37": case "author47": case "author57": case "author67": case "author77": case "author87": case "author97": 
      case "author18": case "author28": case "author38": case "author48": case "author58": case "author68": case "author78": case "author88": case "author98": 
      case "author19": case "author29": case "author39": case "author49": case "author59": case "author69": case "author79": case "author89": case "author99": 
        $value = str_replace(array(",;", " and;", " and ", " ;", "  ", "+", "*"), array(";", ";", " ", ";", " ", "", ""), $value);
        if (strpos($value, ',')) {
          $au = explode(',', $value);
          $this->set('last' . $auNo, formatSurname($au[0]));
          return $this->addIfNew('first' . $auNo, formatForename(trim($au[1])));
        }
        if ($this->blank("last$auNo") && $this->blank("author$auNo")
                && $this->blank("coauthor") && $this->blank("coauthors")
                && underTwoAuthors($p['author'][0])) {
          return $this->set($param, $value);
        }
        return false;
      case "first2": case "first3": case "first4": case "first5": case "first6": case "first7": case "first8": case "first9": 
      case "first10": case "first11": case "first12": case "first13": case "first14": case "first15": case "first16": case "first17": case "first18": case "first19":
      case "first20": case "first21": case "first22": case "first23": case "first24": case "first25": case "first26": case "first27": case "first28": case "first29":
      case "first30": case "first31": case "first32": case "first33": case "first34": case "first35": case "first36": case "first37": case "first38": case "first39":
      case "first40": case "first41": case "first42": case "first43": case "first44": case "first45": case "first46": case "first47": case "first48": case "first49":
      case "first50": case "first51": case "first52": case "first53": case "first54": case "first55": case "first56": case "first57": case "first58": case "first59":
      case "first60": case "first61": case "first62": case "first63": case "first64": case "first65": case "first66": case "first67": case "first68": case "first69":
      case "first70": case "first71": case "first72": case "first73": case "first74": case "first75": case "first76": case "first77": case "first78": case "first79":
      case "first80": case "first81": case "first82": case "first83": case "first84": case "first85": case "first86": case "first87": case "first88": case "first89":
      case "first90": case "first91": case "first92": case "first93": case "first94": case "first95": case "first96": case "first97": case "first98": case "first99":
        if ($this->blank($param)
                && underTwoAuthors($p['author'][0]) && $this->blank("author" . $auNo)
                && $this->blank("coauthor") && $this->blank("coauthors")
                && trim($value) != "") {
          return $this->set($param, $value);
        }
        return false;
      case "date":
        if (preg_match("~^\d{4}$~", sanitize_string($value))) {
          // Not adding any date data beyond the year, so 'year' parameter is more suitable
          $param = "year";
        }
      // Don't break here; we want to go straight in to year;
      case "year":
        if (trim($value) != "" 
            && ($this->blank("date") || trim(strtolower($p['date'][0])) == "in press")
            && ($this->blank("year") || trim(strtolower($p['year'][0])) == "in press") 
          ) {
          return $this->set($param, $value);
        }
        return false;
      case "periodical": case "journal":
        if ($this->blank("journal") && $this->blank("periodical") && $this->blank("work") && trim($value) != "") {
          return $this->set($param, sanitize_string($value));
        }
        return false;
      case 'chapter': case 'contribution':
        if ($this->blank("chapter") && $this->blank("contribution") && trim($value) != "") {
          return $this->set($param, $value);
        }
        return false;
      case "page": case "pages":
        if (( $this->blank("pages") && $this->blank("page") && trim($value) != "")
                || strpos(strtolower($this->get('pages') . $this->get('page'), 'no')) !== FALSE
                || (strpos($value, chr(2013)) || (strpos($value, '-'))
                  && !strpos($this->get('pages'), chr(2013))
                  && !strpos($this->get('pages'), chr(150)) // Also en-dash
                  && !strpos($this->get('pages'), chr(226)) // Also en-dash
                  && !strpos($this->get('pages'), '-')
                  && !strpos($this->get('pages'), '&ndash;'))
        ) return $this->set($param, sanitize_string($value));
        return false;
      case 'title': 
        if ($this->blank($param) && trim($value) != "") {
          return $this->set($param, formatTitle(sanitize_string($value)));
        }
        return false;
      default: 
        if ($this->blank($param) && trim($value) != false) 
          return $this->set($param, sanitize_string($value));
    }
  }
  
  protected function useUnnamedParams() {
    for ($E = 0; $E < count($this->param); $E++) {
      if (!empty($this->param->par)) continue;
      $dat = trim($this->param[$E]->val);
      $endnote_test = explode("\n%", "\n" . $dat);
      if ($endnote_test[1]) {
        foreach ($endnote_test as $endnote_line) {
          switch ($endnote_line[0]) {
            case "A": $endnote_authors++; $endnote_parameter = "author$endnote_authors";        break;
            case "D": $endnote_parameter = "date";       break;
            case "I": $endnote_parameter = "publisher";  break;
            case "C": $endnote_parameter = "location";   break;
            case "J": $endnote_parameter = "journal";    break;
            case "N": $endnote_parameter = "issue";      break;
            case "P": $endnote_parameter = "pages";      break;
            case "T": $endnote_parameter = "title";      break;
            case "U": $endnote_parameter = "url";        break;
            case "V": $endnote_parameter = "volume";     break;
            case "@": // ISSN / ISBN
              if (preg_match("~@\s*[\d\-]{10,}~", $endnote_line)) {
                $endnote_parameter = "isbn";
                break;
              } else if (preg_match("~@\s*\d{4}\-?\d{4}~", $endnote_line)) {
                $endnote_parameter = "issn";
                break;
              } else {
                $endnote_parameter = false;
              }
            case "R": // Resource identifier... *may* be DOI but probably isn't always.
            case "8": // Date
            case "0":// Citation type
            case "X": // Abstract
            case "M": // Object identifier
              $dat = trim(str_replace("\n%$endnote_line", "", "\n" . $dat));
            default:
              $endnote_parameter = false;
          }
          if ($endnote_parameter && $this->addIfNew($endnote_parameter, substr($endnote_line, 1))) {
            $dat = trim(str_replace("\n%$endnote_line", "", "\n$dat"));
          }
        }
      }
      if (preg_match("~^TY\s+-\s+[A-Z]+~", $dat)) {
        // RIS formatted data:
        $ris = explode("\n", $dat);
        foreach ($ris as $ris_line) {
          $ris_part = explode(" - ", $ris_line . " ");
          switch (trim($ris_part[0])) {
            case "T1":
            case "TI":
              $ris_parameter = "title";
              break;
            case "AU":
              $ris_authors++;
              $ris_parameter = "author$ris_authors";
              $ris_part[1] = formatAuthor($ris_part[1]);
              break;
            case "Y1":
              $ris_parameter = "date";
              break;
            case "SP":
              $start_page = trim($ris_part[1]);
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
              break;
            case "EP":
              $end_page = trim($ris_part[1]);
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
              if_null_set("pages", $start_page . "-" . $end_page);
              break;
            case "DO":
              $ris_parameter = "doi";
              break;
            case "JO":
            case "JF":
              $ris_parameter = "journal";
              break;
            case "VL":
              $ris_parameter = "volume";
              break;
            case "IS":
              $ris_parameter = "issue";
              break;
            case "SN":
              $ris_parameter = "issn";
              break;
            case "UR":
              $ris_parameter = "url";
              break;
            case "PB":
              $ris_parameter = "publisher";
              break;
            case "M3": case "PY": case "N1": case "N2": case "ER": case "TY": case "KW":
              $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
            default:
              $ris_parameter = false;
          }
          unset($ris_part[0]);
          if ($ris_parameter
                  && if_null_set($ris_parameter, trim(implode($ris_part)))
              ) {
            global $auto_summary;
            if (!strpos("Converted RIS citation to WP format", $auto_summary)) {
              $auto_summary .= "Converted RIS citation to WP format. ";
            }
            $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
          }
        }

      }
      if (preg_match_all("~(\w+)\.?[:\-\s]*([^\s;:,.]+)[;.,]*~", $dat, $match)) {
        foreach ($match[0] as $i => $oMatch) {
          switch (strtolower($match[1][$i])) {
            case "vol": case "v": case 'volume':
              $matched_parameter = "volume";
              break;
            case "no": case "number": case 'issue': case 'n':
              $matched_parameter = "issue";
              break;
            case 'pages': case 'pp': case 'pg': case 'pgs': case 'pag':
              $matched_parameter = "pages";
              break;
            case 'p':
              $matched_parameter = "page";
              break;
            default: 
              $matched_parameter = null;
          }
          if ($matched_parameter) { 
            $dat = trim(str_replace($oMatch, "", $dat));
            if ($i) $this->addIfNew($matched_parameter, $match[2][$i]);
            else {
              $this->param[$E]->param = $matched_parameter;
              $this->param[$E]->val = $match[2][0];
            }
          }
        }
      }
      if (preg_match("~(\d+)\s*(?:\((\d+)\))?\s*:\s*(\d+(?:\d\s*-\s*\d+))~", $dat, $match)) {
        if_null_set('volume', $match[1]);
        if_null_set('issue', $match[2]);
        if_null_set('pages', $match[3]);
        $dat = trim(str_replace($match[0], '', $dat));
      }
      if (preg_match("~\(?(1[89]\d\d|20\d\d)[.,;\)]*~", $dat, $match)) {
        if (if_null_set('year', $match[1])) {
          $dat = trim(str_replace($match[0], '', $dat));
        }
      }
      // Load list of parameters used in citation templates.
      //We generated this earlier in expandFns.php.  It is sorted from longest to shortest.
      global $parameter_list;

      $shortest = -1;
      foreach ($parameter_list as $parameter) {
        $para_len = strlen($parameter);
        if (substr(strtolower($dat), 0, $para_len) == $parameter) {
          $character_after_parameter = substr(trim(substr($dat, $para_len)), 0, 1);
          $parameter_value = ($character_after_parameter == "-" || $character_after_parameter == ":")
            ? substr(trim(substr($dat, $para_len)), 1) : substr($dat, $para_len);
          $this->para = $parameter;
          $this->val = $parameter_value;
          break;
        }
        $test_dat = preg_replace("~\d~", "_$0",
                    preg_replace("~[ -+].*$~", "", substr(mb_strtolower($dat), 0, $para_len)));
        if ($para_len < 3)
        {
          break; // minimum length to avoid false positives
        }

        if (preg_match("~\d~", $parameter))
        {
          $lev = levenshtein($test_dat, preg_replace("~\d~", "_$0", $parameter));
          $para_len++;
        }
        else
        {
          $lev = levenshtein($test_dat, $parameter);
        }
        if ($lev == 0)
        {
          $closest = $parameter;
          $shortest = 0;
          break;
        }
        // Strict inequality as we want to favour the longest match possible
        if ($lev < $shortest || $shortest < 0)
        {
          $comp = $closest;
          $closest = $parameter;
          $shortish = $shortest;
          $shortest = $lev;
        }
        // Keep track of the second shortest result, to ensure that our chosen parameter is an out and out winner
        else if ($lev < $shortish)
        {
          $shortish = $lev;
          $comp = $parameter;
        }
      }

      if ($shortest < 3
         && (similar_text($shortest, $test_dat) / strlen($test_dat) > 0.4)
         &&  ($shortest + 1 < $shortish  // No close competitor
             || $shortest / $shortish <= 2/3
             || strlen($closest) > strlen($comp)
             )
      ) {
          // remove leading spaces or hyphens (which may have been typoed for an equals)
          if (preg_match("~^[ -+]*(.+)~", substr($dat, strlen($closest)), $match))
          {
            $this->addIfNew($closest, $match[1]/* . " [$shortest / $comp = $shortish]"*/);
          }
      } elseif (substr(trim($dat), 0, 7) == 'http://' && $this->blank('url')) {
        // Is the data a URL, and is the URL parameter blank?
        $this->param[$E]->param = 'url';
        $this->param[$E]->val = $dat;
      } elseif (preg_match("~(?!<\d)(\d{10}|\d{13})(?!\d)~", str_replace(Array(" ", "-"), "", $dat), $match))
      {
        // Is it a number formatted like an ISBN?
        $this->addIfNew("isbn", $match[1]);
        $pAll = "";
      } else {
        // Extract whatever appears before the first space, and compare it to common parameters
        $pAll = explode(" ", trim($dat));
        $p1 = mb_strtolower($pAll[0]);
        switch ($p1) {
          case "volume": case "vol":
          case "pages": case "page":
          case "year": case "date":
          case "title":
          case "authors": case "author":
          case "issue":
          case "journal":
          case "accessdate":
          case "archiveurl":
          case "archivedate":
          case "format":
          case "url":
          if ($this->blank($p1)) {
            unset($pAll[0]);
            $this->param[$E]->param = $p1;
            $this->param[$E]->val = implode(" ", $pAll);
          }
          break;
          case "issues":
          if ($this->blank($p1)) {
            unset($pAll[0]);
            $this->param[$E]->param = 'issue';
            $this->param[$E]->val = implode(" ", $pAll);
          }
          break;
          case "access date":
          if ($this->blank($p1)) {
            unset($pAll[0]);
            $this->param[$E]->param = 'accessdate';
            $this->param[$E]->val = implode(" ", $pAll);
          }
          break;
          default:
            // No good; we'll have to return it to the unused data parameter
            $i++;
        }
      }
    }
  
  }
  
  protected function join_params() {
    if ($this->param) foreach($this->param as $p) {
      $ret .= '|' . $p->parsed_text();
    }
    return $ret;
  }
  
  public function wikiname() {
    return trim(mb_strtolower(str_replace('_', ' ', $this->name)));
  }
  
  protected function split_params($text) {
    # | [pre] [param] [eq] [value] [post]
    if ($this->wikiname() == 'cite doi')
      $text = preg_replace('~d?o?i?\s*[:.,;>]*\s*(10\.\S+).*?(\s*)$~', "$1$2", $text);
    $params = explode('|', $text);
    foreach ($params as $i => $text) {
      $this->param[$i] = new Parameter();
      $this->param[$i]->parse_text($text);
    }
  }
  
  public function get_param_position ($needle) {
    foreach ($this->param as $i => $p) {
      if ($p->param == $needle) return $i;
    }
  }
  
  public function get($name) {
    foreach ($this->param as $p) {
      if ($p->param == $name) return $p->value;
    }
  }
  
  public function set($par, $val) {return $this->add_param($par, $val);} 
  public function add_param($par, $val) {
    if ($this->param[0]) {
      $p = new Parameter;
      $p->parse_text($this->param[0]->parsed_text());
    } else {
      $p = new Parameter;
      $p->parse_text('| param = val');
    }
    $p->param = $par;
    $p->val = $val;
    $this->param[] = $p;
    return true;
  }
  
  public function parsed_text() {
    return '{{' . $this->name . $this->join_params() . '}}';
  }
}


# PARAMETERS #
class Parameter {
  public $pre, $param, $eq, $val, $post;
  
  public function parse_text($text) {
    $split = explode('=', $text, 2);
    preg_match('~^\s+~', $split[0], $pre);
    preg_match('~\s+$~', $split[0], $pre_eq);
    if ($split[1]) {
      preg_match('~^\s+~', $split[1], $post_eq);
      preg_match('~\s+$~', $split[1], $post);
      $this->pre = $pre[0];
      $this->param = trim($split[0]);
      $this->eq = $pre_eq[0] . '=' . $post_eq[0];
      $this->parse_val(trim($split[1]));
      $this->post= $post[0];
    } else {
      $this->pre = $pre[0];
      $this->val = trim($split[0]);
      $this->post = $pre_eq[0];
    }
  }
  
  protected function parse_val($value) {
    switch ($this->param) {
      case 'pages':
        $this->val = mb_ereg_replace(to_en_dash, en_dash, $value);
      break;
      default: $this->val = $value;
    }
  }
  
  public function parsed_text() {
    return $this->pre . $this->param . (($this->param && empty($this->eq))?' = ':$this->eq) . $this->val . $this->post;
  }
}

function extract_object ($text, $class) {
  $i = 0;
  $regexp = $class::regexp;
  $placeholder_text = $class::placeholder_text;
  while(preg_match($regexp, $text, $match)) {
    $obj = new $class();
    $obj->parse_text($match[0]);
    $objects[] = $obj;
    $text = str_replace($match[0], sprintf($placeholder_text, $i++), $text, $matches);
    $obj->occurrences = $matches;
  }
  return array($text, $objects);
}

function replace_object ($text, $objects) {
  $i = count($objects);
  foreach (array_reverse($objects) as $obj) {
    $placeholder_format = $obj::placeholder_text;
    $text = str_replace(sprintf($placeholder_format, --$i), $obj->parsed_text(), $text);
  }
  return $text;
} 

function allowBots( $text ) {
  // from http://en.wikipedia.org/wiki/Template:Nobots example implementation
  $user = '(?:Citation|DOI)[ _]bot';
  if (preg_match('/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?'.$user.'.*?)\}\}/iS',$text))
    return false;
  if (preg_match('/\{\{(bots\|allow=all|bots\|allow=.*?'.$user.'.*?)\}\}/iS', $text))
    return true;
  if (preg_match('/\{\{(bots\|allow=.*?)\}\}/iS', $text))
    return false;
  return true;
}
