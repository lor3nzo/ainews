<?php
echo "PHP OK\n";
echo "version: ", PHP_VERSION, "\n";
echo "exts: ", (extension_loaded('curl')?'curl ':'-'), (extension_loaded('simplexml')?'simplexml ':'-'),
            (extension_loaded('libxml')?'libxml ':'-'), "\n";
$base = __DIR__;
echo "writable tmp: ", is_writable("$base/tmp") ? "yes\n" : "no\n";
echo "writable data: ", is_writable("$base/data") ? "yes\n" : "no\n";
echo "writable manifests: ", is_writable("$base/manifests") ? "yes\n" : "no\n";
