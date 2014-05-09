<?php

namespace Kagency\CouchdbEndpoint\Replicator;

use Kagency\CouchdbEndpoint\Struct;

/**
 * Class: OK
 *
 * CouchDB success message
 *
 * @version $Revision$
 */
class OK extends Struct
{
    /**
     * Everything is OK
     *
     * @var bool
     */
    public $ok = true;
}