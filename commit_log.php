<?php
/**
 * Show the site's commit log
 *
 * By convention, we expect that the base git repository for the site will be in wp-content.
 * If this is not the case, change the SITE_GIT_REPO_DIR constant in pospi_base.php accordingly.
 *
 * @author Sam Pospischil <pospi@spadgos.com>
 * @since 16/7/12
 */

$dir = defined('SITE_GIT_REPO_DIR') ? SITE_GIT_REPO_DIR : false;
$numPerPage = 25;

// read pagination arguments from request
$page = isset($_GET['start']) ? dechex(intval($_GET['start'], 16)) : false;

// read log output from command line
$output = array();
chdir($dir);
exec("git log", $output);


function printCommit($commit)
{
	echo sprintf('<div class="commit" id="c_%1$s"><h4><span class="date">%4$s</span> #<a class="hash" href="#c_%2$s">%2$s</a> by <span class="author">%3$s</span></h4>%5$s</div>',
			trim($commit['hash']), substr(trim($commit['hash']), 0, 7), htmlentities(trim($commit['author'])), trim($commit['date']), nl2br(trim($commit['msg'], " \r\n")));
}

//------------------------------------------------------------------------------

echo "<h2>Change history</h2>";

// iterate over it and output
$history = array();

$commit = array();
foreach($output as $line) {
	if (strpos($line, 'commit') === 0) {
		if (!empty($commit)) {
			// next commit. output the previous one & clean the var
			printCommit($commit);
			$commit = array();
		}
		$commit['hash'] = substr($line, strlen('commit'));
	} else if (strpos($line, 'Author') === 0){
		$commit['author'] = substr($line, strlen('Author:'));
	} else if (strpos($line, 'Date') === 0){
		$commit['date'] = substr($line, strlen('Date:'));
	} else {
		if (!$commit['msg']) $commit['msg'] = '';
		$commit['msg'] .= trim($line) . "\n";
	}
}

// output the last commit
if (!empty($commit)) {
	printCommit($commit);
}
