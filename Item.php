<?php
/*
 * Item is the base class for:
 *     Comment
 *     Template
 *     Long_Reference
 *     Short_Reference
 *
 * It defines variables but doesn't offer much other structure. Implementation details of its
 * child classes vary significantly.
 *
 */

class Item {
  protected $rawtext;
  public $occurrences, $page;
}
