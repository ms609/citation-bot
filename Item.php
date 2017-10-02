<?php
/*
 * Item is the base class for:
 *     Comment
 *     Page
 *     Template
 *
 * It defines variables but doesn't offer much other structure. 
 * Implementation details of its child classes vary significantly.
 *
 */

class Item {
  protected $rawtext;
  public $occurrences, $page;
}
