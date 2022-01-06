<?php

namespace Furkifor\SqlDumper\Tests;
use Furkifor\SqlDumper\SqlDumperClass;
class SelectTest
{
    /** @test */
    public function true_is_true()
    {
        $table = new SqlDumperClass("user");
        echo $table->select()->get();
    }
}
