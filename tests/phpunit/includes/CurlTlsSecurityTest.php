<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class CurlTlsSecurityTest extends testBaseClass {
    public function testMandatoryCurlSecurityOptionsReapplyTlsVerification(): void {
        $source = file_get_contents(__DIR__ . '/../../../src/includes/bot_curl.php');
        $this->assertIsString($source);

        $start = mb_strpos($source, 'function bot_curl_apply_security_options');
        $end = mb_strpos($source, 'function curl_limit_page_size', $start);
        $this->assertIsInt($start);
        $this->assertIsInt($end);
        $function = mb_substr($source, $start, $end - $start);

        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER => true', $function);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYHOST => 2', $function);
        $this->assertStringContainsString('CURLOPT_PREREQFUNCTION', $function);
    }
}
