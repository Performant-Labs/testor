<?php

namespace PL\Tests\Robo\Task\Testor;

use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertEquals;

class RegexTest extends TestCase
{
    public function testRegex()
    {
        preg_match('/(.*?)(\.tar|\.sql)?(\.gz)?$/', 'file.sql', $m);

        assertEquals('file', $m[1]);
        assertEquals('.sql', $m[2]);
        assertEquals('', $m[3]);
    }

}