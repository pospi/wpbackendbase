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

//------------------------------------------------------------------------------

function printCommit($commit)
{
	echo sprintf('<div class="commit" id="c_%6$s_%2$s"><h4><span class="date">%4$s</span> %6$s #<a class="hash" alt="%1$s" href="#c_%6$s_%2$s">%2$s</a> by <span class="author">%3$s</span></h4>%5$s</div>',
			trim($commit['hash']), substr(trim($commit['hash']), 0, 7), htmlentities(trim($commit['author'])), trim($commit['date']), nl2br(trim($commit['msg'], " \r\n")), isset($commit['module']) ? $commit['module'] : '');
}

function handleLines(Array $output)
{
	$earliestRepoDate = null;

	$submoduleName = false;
	$commits = array();

	$commit = array();
	foreach($output as $line) {
		if (strpos($line, 'Entering') === 0) {
			$submoduleName = str_replace('/', '-', trim(substr($line, strlen('Entering')), ' \''));
		} else if (strpos($line, 'commit') === 0) {
			if (!empty($commit)) {
				// next commit. output the previous one & clean the var
				$time = strtotime($commit['date']);
				if ($submoduleName) {
					$commit['module'] = $submoduleName;
				} else if (null === $earliestRepoDate || $earliestRepoDate > $time) {
					$earliestRepoDate = $time;
				}
				while (isset($commits[$time])) {
					$time += 0.1;
				}
				$commits[$time] = $commit;
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

	// append the last commit
	if (!empty($commit)) {
		$time = strtotime($commit['date']);
		if ($submoduleName) {
			$commit['module'] = $submoduleName;
		} else if (null === $earliestRepoDate || $earliestRepoDate > $time) {
			$earliestRepoDate = $time;
		}
		while (isset($commits[$time])) {
			$time += 0.1;
		}
		$commits[$time] = $commit;
	}

	return array($commits, $earliestRepoDate);
}

//------------------------------------------------------------------------------

// read log output from command line
$output = array();
$output2 = array();
chdir($dir);
exec("git log", $output);

// iterate over the outputs and organise by commit date
list($commits, $earliestRepoDate) = handleLines($output);
krsort($commits);

// pull submodule history too if configured to do so. We only pull up until the creation of the host repo.
if (!defined('SITE_GIT_SUBMODULE_HISTORY') || SITE_GIT_SUBMODULE_HISTORY) {
	exec("git submodule foreach git log", $output2);

	list($subCommits, $ignored) = handleLines($output2);

	foreach ($subCommits as $time => $commit) {
		while (isset($commits[$time])) {
			$time += 0.1;
		}

		if ($time < $earliestRepoDate) {
			continue;
		}

		$commits[$time] = $commit;
	}
	krsort($commits);
}

// output the commits
echo "<h2>Change history</h2>";

array_walk($commits, 'printCommit');
