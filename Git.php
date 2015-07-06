<?php

/*
 * Git.php
 *
 * A PHP git library
 *
 * @package    Git.php
 * @version    0.1.4
 * @author     James Brumond
 * @copyright  Copyright 2013 James Brumond
 * @repo       http://github.com/kbjr/Git.php
 */

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) die('Bad load order');

// ------------------------------------------------------------------------

/**
 * Git Interface Class
 *
 * This class enables the creating, reading, and manipulation
 * of git repositories.
 *
 * @class  Git
 */
class Git {

	/**
	 * Git executable location
	 *
	 * @var string
	 */
	protected static $bin = '/usr/bin/git';

	/**
	 * Sets git executable path
	 *
	 * @param string $path executable location
	 */
	public static function set_bin($path) {
		self::$bin = $path;
	}

	/**
	 * Gets git executable path
	 */
	public static function get_bin() {
		return self::$bin;
	}

	/**
	 * Sets up library for use in a default Windows environment
	 */
	public static function windows_mode() {
		self::set_bin('git');
	}

	/**
	 * Create a new git repository
	 *
	 * Accepts a creation path, and, optionally, a source path
	 *
	 * @access  public
	 * @param   string  repository path
	 * @param   string  directory to source
	 * @return  GitRepo
	 */
	public static function &create($repo_path, $source = null) {
		return GitRepo::create_new($repo_path, $source);
	}

	/**
	 * Open an existing git repository
	 *
	 * Accepts a repository path
	 *
	 * @access  public
	 * @param   string  repository path
	 * @return  GitRepo
	 */
	public static function open($repo_path) {
		return new GitRepo($repo_path);
	}

	/**
	 * Clones a remote repo into a directory and then returns a GitRepo object
	 * for the newly created local repo
	 *
	 * Accepts a creation path and a remote to clone from
	 *
	 * @access  public
	 * @param   string  repository path
	 * @param   string  remote source
	 * @param   string  reference path
	 * @return  GitRepo
	 **/
	public static function &clone_remote($repo_path, $remote, $reference = null) {
		return GitRepo::create_new($repo_path, $remote, true, $reference);
	}

	/**
	 * Checks if a variable is an instance of GitRepo
	 *
	 * Accepts a variable
	 *
	 * @access  public
	 * @param   mixed   variable
	 * @return  bool
	 */
	public static function is_repo($var) {
		return (get_class($var) == 'GitRepo');
	}

}

// ------------------------------------------------------------------------

/**
 * Git Repository Interface Class
 *
 * This class enables the creating, reading, and manipulation
 * of a git repository
 *
 * @class  GitRepo
 */
class GitRepo {

	protected $repo_path = null;
	protected $bare = false;
	protected $envopts = array();

	/**
	 * Create a new git repository
	 *
	 * Accepts a creation path, and, optionally, a source path
	 *
	 * @access  public
	 * @param   string  repository path
	 * @param   string  directory to source
	 * @param   string  reference path
	 * @return  GitRepo
	 */
	public static function &create_new($repo_path, $source = null, $remote_source = false, $reference = null) {
		if (is_dir($repo_path) && file_exists($repo_path."/.git") && is_dir($repo_path."/.git")) {
			throw new Exception('"'.$repo_path.'" is already a git repository');
		} else {
			$repo = new self($repo_path, true, false);
			if (is_string($source)) {
				if ($remote_source) {
					if (!is_dir($reference) || !is_dir($reference.'/.git')) {
						throw new Exception('"'.$reference.'" is not a git repository. Cannot use as reference.');
					} else if (strlen($reference)) {
						$reference = realpath($reference);
						$reference = "--reference $reference";
					}
					$repo->clone_remote($source, $reference);
				} else {
					$repo->clone_from($source);
				}
			} else {
				$repo->run('init');
			}
			return $repo;
		}
	}

	/**
	 * Constructor
	 *
	 * Accepts a repository path
	 *
	 * @access  public
	 * @param   string  repository path
	 * @param   bool    create if not exists?
	 * @return  void
	 */
	public function __construct($repo_path = null, $create_new = false, $_init = true) {
		if (is_string($repo_path)) {
			$this->set_repo_path($repo_path, $create_new, $_init);
		}
	}

	/**
	 * Set the repository's path
	 *
	 * Accepts the repository path
	 *
	 * @access  public
	 * @param   string  repository path
	 * @param   bool    create if not exists?
	 * @param   bool    initialize new Git repo if not exists?
	 * @return  void
	 */
	public function set_repo_path($repo_path, $create_new = false, $_init = true) {
		if (is_string($repo_path)) {
			if ($new_path = realpath($repo_path)) {
				$repo_path = $new_path;
				if (is_dir($repo_path)) {
					// Is this a work tree?
					if (file_exists($repo_path."/.git") && is_dir($repo_path."/.git")) {
						$this->repo_path = $repo_path;
						$this->bare = false;
					// Is this a bare repo?
					} else if (is_file($repo_path."/config")) {
					  $parse_ini = parse_ini_file($repo_path."/config");
						if ($parse_ini['bare']) {
							$this->repo_path = $repo_path;
							$this->bare = true;
						}
					} else {
						if ($create_new) {
							$this->repo_path = $repo_path;
							if ($_init) {
								$this->run('init');
							}
						} else {
							throw new Exception('"'.$repo_path.'" is not a git repository');
						}
					}
				} else {
					throw new Exception('"'.$repo_path.'" is not a directory');
				}
			} else {
				if ($create_new) {
					if ($parent = realpath(dirname($repo_path))) {
						mkdir($repo_path);
						$this->repo_path = $repo_path;
						if ($_init) $this->run('init');
					} else {
						throw new Exception('cannot create repository in non-existent directory');
					}
				} else {
					throw new Exception('"'.$repo_path.'" does not exist');
				}
			}
		}
	}
	
	/**
	 * Get the path to the git repo directory (eg. the ".git" directory)
	 * 
	 * @access public
	 * @return string
	 */
	public function git_directory_path() {
		return ($this->bare) ? $this->repo_path : $this->repo_path."/.git";
	}

	/**
	 * Get the path to the git repo directory
	 * 
	 * @access public
	 * @return string
	 */
	public function get_repo_path()
	{
		return $this->repo_path;
	}

	/**
	 * Tests if git is installed
	 *
	 * @access  public
	 * @return  bool
	 */
	public function test_git() {
		$descriptorspec = array(
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$pipes = array();
		$resource = proc_open(Git::get_bin(), $descriptorspec, $pipes);

		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		foreach ($pipes as $pipe) {
			fclose($pipe);
		}

		$status = trim(proc_close($resource));
		return ($status != 127);
	}

	/**
	 * Run a command in the git repository
	 *
	 * Accepts a shell command to run
	 *
	 * @access  protected
	 * @param   string  command to run
	 * @return  string
	 */
	protected function run_command($command) {
		$descriptorspec = array(
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$pipes = array();
		/* Depending on the value of variables_order, $_ENV may be empty.
		 * In that case, we have to explicitly set the new variables with
		 * putenv, and call proc_open with env=null to inherit the reset
		 * of the system.
		 *
		 * This is kind of crappy because we cannot easily restore just those
		 * variables afterwards.
		 *
		 * If $_ENV is not empty, then we can just copy it and be done with it.
		 */
		if(count($_ENV) === 0) {
			$env = NULL;
			foreach($this->envopts as $k => $v) {
				putenv(sprintf("%s=%s",$k,$v));
			}
		} else {
			$env = array_merge($_ENV, $this->envopts);
		}
		$cwd = $this->repo_path;
		$resource = proc_open($command, $descriptorspec, $pipes, $cwd, $env);

		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		foreach ($pipes as $pipe) {
			fclose($pipe);
		}

		$status = trim(proc_close($resource));
		if ($status) throw new Exception($stderr . "\n" . $stdout);

		return $stderr . $stdout;
	}

	/**
	 * Run a git command in the git repository
	 *
	 * Accepts a git command to run
	 *
	 * @access  public
	 * @param   string  command to run
	 * @return  string
	 */
	public function run($command) {
		return $this->run_command(Git::get_bin()." ".$command);
	}

	/**
	 * Runs a 'git status' call
	 *
	 * Accept a convert to HTML bool
	 *
	 * @access public
	 * @param bool  return string with <br />
	 * @return string
	 */
	public function status($html = false) {
		$msg = $this->run("status");
		if ($html == true) {
			$msg = str_replace("\n", "<br />", $msg);
		}
		return $msg;
	}

	/**
	 * Runs a `git add` call
	 *
	 * Accepts a list of files to add
	 *
	 * @access  public
	 * @param   mixed   files to add
	 * @return  string
	 */
	public function add($files = "*") {
		if (is_array($files)) {
			$files = '"'.implode('" "', $files).'"';
		}
		return $this->run("add $files -v");
	}

	/**
	 * Runs a `git rm` call
	 *
	 * Accepts a list of files to remove
	 *
	 * @access  public
	 * @param   mixed    files to remove
	 * @param   Boolean  use the --cached flag?
	 * @return  string
	 */
	public function rm($files = "*", $cached = false) {
		if (is_array($files)) {
			$files = '"'.implode('" "', $files).'"';
		}
		return $this->run("rm ".($cached ? '--cached ' : '').$files);
	}


	/**
	 * Runs a `git commit` call
	 *
	 * Accepts a commit message string
	 *
	 * @access  public
	 * @param   string  commit message
	 * @param   boolean  should all files be committed automatically (-a flag)
	 * @return  string
	 */
	public function commit($message = "", $commit_all = true) {
		$flags = $commit_all ? '-av' : '-v';
		return $this->run("commit ".$flags." -m ".escapeshellarg($message));
	}

	/**
	 * Runs a `git clone` call to clone the current repository
	 * into a different directory
	 *
	 * Accepts a target directory
	 *
	 * @access  public
	 * @param   string  target directory
	 * @return  string
	 */
	public function clone_to($target) {
		return $this->run("clone --local ".$this->repo_path." $target");
	}

	/**
	 * Runs a `git clone` call to clone a different repository
	 * into the current repository
	 *
	 * Accepts a source directory
	 *
	 * @access  public
	 * @param   string  source directory
	 * @return  string
	 */
	public function clone_from($source) {
		return $this->run("clone --local $source ".$this->repo_path);
	}

	/**
	 * Runs a `git clone` call to clone a remote repository
	 * into the current repository
	 *
	 * Accepts a source url
	 *
	 * @access  public
	 * @param   string  source url
	 * @param   string  reference path
	 * @return  string
	 */
	public function clone_remote($source, $reference) {
		return $this->run("clone $reference $source ".$this->repo_path);
	}

	/**
	 * Runs a `git clean` call
	 *
	 * Accepts a remove directories flag
	 *
	 * @access  public
	 * @param   bool    delete directories?
	 * @param   bool    force clean?
	 * @return  string
	 */
	public function clean($dirs = false, $force = false) {
		return $this->run("clean".(($force) ? " -f" : "").(($dirs) ? " -d" : ""));
	}

	/**
	 * Runs a `git branch` call
	 *
	 * Accepts a name for the branch
	 *
	 * @access  public
	 * @param   string  branch name
	 * @return  string
	 */
	public function create_branch($branch) {
		return $this->run("branch $branch");
	}

	/**
	 * Runs a `git branch -[d|D]` call
	 *
	 * Accepts a name for the branch
	 *
	 * @access  public
	 * @param   string  branch name
	 * @return  string
	 */
	public function delete_branch($branch, $force = false) {
		return $this->run("branch ".(($force) ? '-D' : '-d')." $branch");
	}

	/**
	 * Runs a `git branch` call
	 *
	 * @access  public
	 * @param   bool    keep asterisk mark on active branch
	 * @return  array
	 */
	public function list_branches($keep_asterisk = false) {
		$branchArray = explode("\n", $this->run("branch"));
		foreach($branchArray as $i => &$branch) {
			$branch = trim($branch);
			if (! $keep_asterisk) {
				$branch = str_replace("* ", "", $branch);
			}
			if ($branch == "") {
				unset($branchArray[$i]);
			}
		}
		return $branchArray;
	}

	/**
	 * Lists remote branches (using `git branch -r`).
	 *
	 * Also strips out the HEAD reference (e.g. "origin/HEAD -> origin/master").
	 *
	 * @access  public
	 * @return  array
	 */
	public function list_remote_branches() {
		$branchArray = explode("\n", $this->run("branch -r"));
		foreach($branchArray as $i => &$branch) {
			$branch = trim($branch);
			if ($branch == "" || strpos($branch, 'HEAD -> ') !== false) {
				unset($branchArray[$i]);
			}
		}
		return $branchArray;
	}

	/**
	 * Returns name of active branch
	 *
	 * @access  public
	 * @param   bool    keep asterisk mark on branch name
	 * @return  string
	 */
	public function active_branch($keep_asterisk = false) {
		$branchArray = $this->list_branches(true);
		$active_branch = preg_grep("/^\*/", $branchArray);
		reset($active_branch);
		if ($keep_asterisk) {
			return current($active_branch);
		} else {
			return str_replace("* ", "", current($active_branch));
		}
	}

	/**
	 * Runs a `git checkout` call
	 *
	 * Accepts a name for the branch
	 *
	 * @access  public
	 * @param   string  branch name
	 * @return  string
	 */
	public function checkout($branch) {
		return $this->run("checkout $branch");
	}


	/**
	 * Runs a `git merge` call
	 *
	 * Accepts a name for the branch to be merged
	 *
	 * @access  public
	 * @param   string $branch
	 * @return  string
	 */
	public function merge($branch) {
		try {
			$branch = escapeshellarg($branch);
			$result = $this->run("merge $branch --no-ff");
		} catch(\Exception $e) {
			$result = $e->getMessage();
		}

		return $result;
	}

	/**
	 * Runs a `git merge --abort`
	 *
	 * Reverts last merge
	 *
	 * @access  public
	 * @return  string
	 */
	public function mergeAbort()
	{
		try {
			$result = $this->run('merge --abort');
		} catch(\Exception $e) {
			$result = $e->getMessage();
		}

		return $result;
	}

	/**
	 * Runs a `git reset` with params
	 *
	 * @access  public
	 * @param   string $resetStr
	 * @return  string
	 */
	public function reset($resetStr)
	{
		return $this->run("reset {$resetStr}");
	}

	/**
	 * Runs a git fetch on the current branch
	 *
	 * @param   bool $dry
	 *
	 * @access  public
	 * @return  string
	 */
	public function fetch($dry = false)
	{
		$dry = $dry ? ' --dry-run' : '';
		return $this->run("fetch{$dry}");
	}

	/**
	 * Runs a git stash
	 *
	 * @access  public
	 * @return  string
	 */
	public function stash()
	{
		return $this->run("stash");
	}

	/**
	 * Runs a git stash pop
	 *
	 * @access  public
	 * @return  string
	 */
	public function stashPop()
	{
		try {
			$result = $this->run("stash pop");
		} catch (Exception $e) {
			$result = $e->getMessage();
		}

		return $result;
	}

	/**
	 * Add a new tag on the current position
	 *
	 * Accepts the name for the tag and the message
	 *
	 * @param string $tag
	 * @param string $message
	 * @return string
	 */
	public function add_tag($tag, $message = null) {
		if ($message === null) {
			$message = $tag;
		}
		return $this->run("tag -a $tag -m " . escapeshellarg($message));
	}

	/**
	 * List all the available repository tags.
	 *
	 * Optionally, accept a shell wildcard pattern and return only tags matching it.
	 *
	 * @access	public
	 * @param	string	$pattern	Shell wildcard pattern to match tags against.
	 * @return	array				Available repository tags.
	 */
	public function list_tags($pattern = null) {
		$tagArray = explode("\n", $this->run("tag -l $pattern"));
		foreach ($tagArray as $i => &$tag) {
			$tag = trim($tag);
			if ($tag == '') {
				unset($tagArray[$i]);
			}
		}

		return $tagArray;
	}

	/**
	 * Push specific branch to a remote
	 *
	 * Accepts the name of the remote and local branch
	 *
	 * @param string $remote
	 * @param string $branch
	 * @return string
	 */
	public function push($remote, $branch) {
		return $this->run("push --tags $remote $branch");
	}

	/**
	 * Pull specific branch from remote
	 *
	 * Accepts the name of the remote and local branch
	 *
	 * @param string $remote
	 * @param string $branch
	 * @return string
	 */
	public function pull($remote, $branch) {
		return $this->run("pull $remote $branch");
	}

	/**
	 * List log entries.
	 *
	 * @param strgin $format
	 * @return string
	 */
	public function log($format = null, $file = '', $limit = null) {
		if ($limit === null) {
			$limitArg = "";
		} else {
			$limitArg = "-{$limit}";
		}

		if ($format === null) {
			return $this->run("log {$limitArg} {$file}");
		} else {
			return $this->run("log {$limitArg} --pretty=format:'{$format}' {$file}");
		}
	}

    /**
     * List log entries with `--grep`
     *
     * @param  string $grep    grep by ...
     * @param  strgin $format
     * @return string
     */
    public function logGrep($grep, $format = null)
    {
        if ($format === null) {
            return $this->run("log --grep='{$grep}'");
        } else {
            return $this->run("log --grep='{$grep}' --pretty=format:'{$format}'");
        }
    }

    /**
     * Runs a `git diff`
     *
     * @param   string  $params
     * @access  public
     */
    public function diff($params = '')
    {
        return $this->run("diff {$params}");
    }

    /**
     * Runs a `git diff --cached`
     *
     * @access  public
     */
    public function diffCached()
    {
        return $this->run('diff --cached');
    }

	/**
	 * Sets the project description.
	 *
	 * @param string $new
	 */
	public function set_description($new) {
		$path = $this->git_directory_path();
		file_put_contents($path."/description", $new);
	}

	/**
	 * Gets the project description.
	 *
	 * @return string
	 */
	public function get_description() {
		$path = $this->git_directory_path();
		return file_get_contents($path."/description");
	}

	/**
	 * Sets custom environment options for calling Git
	 *
	 * @param string key
	 * @param string value
	 */
	public function setenv($key, $value) {
		$this->envopts[$key] = $value;
	}

	/*
	 * Clears the local repository from the branches, which were deleted from the remote repository.
	 *
	 * @return string
	 */
	public function remotePruneOrigin()
	{
		return $this->run('remote prune origin');
	}

	/**
	 * Gets remote branches by pattern
	 *
	 * @param   string $pattern
	 *
	 * @access  public
	 * @return  string
	 */
	public function getRemoteBranchesByPattern($pattern)
	{
		try {
			return $this->run("branch -r | grep '{$pattern}'");
		} catch (Exception $ex) {
			return '';
		}
	}

	/**
	 * Gets remote branches count
	 *
	 * @access  public
	 * @return  int
	 */
	public function getRemoteBranchesCount()
	{
		try {
			return $this->run("branch -r | wc -l");
		} catch (Exception $ex) {
			return '';
		}
	}

	/**
	 * Deletes remote branches
	 *
	 * @param   array $branches
	 *
	 * @access  public
	 * @return  void
	 */
	public function deleteRemoteBranches(array $branches)
	{
		try {
			$this->run("push origin --delete " . implode(" ", $branches));
		} catch (Exception $ex) {
			return;
		}
	}

	/**
	 * Runs git gc command
	 *
	 * @param   string $command
	 *
	 * @access  public
	 * @return  string
	 */
	public function gc($command = '')
	{
		try {
			$this->run("gc {$command}");
		} catch (Exception $ex) {
			return false;
		}
	}
}

/* End of file */
