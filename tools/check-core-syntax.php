<?php
$fail=0;$count=0;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/var/www/html/wp-content/plugins/chidemoon-core')) as $file){if($file->getExtension()!=='php')continue;exec('php -l '.escapeshellarg($file->getPathname()).' 2>&1',$output,$status);$count++;if($status){echo implode("\n",$output);$fail++;}$output=[];}
echo "Checked $count PHP files; failures: $fail\n";exit($fail?1:0);
