<?php

namespace files;

use atomar\core\HookReceiver;

class Hooks extends HookReceiver
{
    function hookPermission()
    {
        return array(
            'files-download',
            'files-upload'
        );
    }

    function hookLibraries()
    {
        return array (
            'lib/DataStore.php',
            'lib/LocalDataStore.php',
            'lib/FileManager.php'
        );
    }
}