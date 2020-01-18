<?php
echo "<!DOCTYPE html><html><body><pre>\n";
// Make the person wait.  I know how I can be.
@echo "Wait 6</p>\n";
@sleep(1);
@echo "Wait 5</p>\n";
@sleep(1);
@echo "Wait 4</p>\n";
@sleep(1);
@echo "Wait 3</p>\n";
@sleep(1);
@echo "Wait 2</p>\n";
@sleep(1);
@echo "Wait 1</p>\n";
@sleep(1);
@rmdir('git_pull.lock') ;
@echo "Script done</p>\n";
@echo "</pre></body></html>\n"
?>
