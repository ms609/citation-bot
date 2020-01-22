<?php

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

   public function testIDconvert8() {
     $text='{{Cite journal | id = {{ASIN|0226845494|country=eu}} }}';
     $template = $this->process_citation($text);
     echo "\n" . $template->parsed_text() . "\n";
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     echo "\n" . $template->parsed_text() . "\n";
     $this->assertSame('{{ASIN|0226845494|country=eu}}', $template->get('id'));
     $this->assertSame('0226845494', $template->get('isbn'));
   }
 
   public function testIDconvert9() {
     $text = '{{Cite journal | id = {{howdy|0226845494}} }}';
     $template = $this->process_citation($text);
     echo "\n" . $template->parsed_text() . "\n";
     $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
     echo "\n" . $template->parsed_text() . "\n";
     $this->assertSame('{{howdy|0226845494}}', $template->get('id'));
     $this->assertSame('0226845494', $template->get('isbn'));
    }
 
    public function testNOMERAGE() {
     $this->assertNull('no merge');
    }

}
