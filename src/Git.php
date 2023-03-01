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

namespace Kbjr\Git;

/**
 * Git Interface Class
 *
 * This class enables the creating, reading, and manipulation
 * of git repositories.
 *
 * @class  Git
 */
class Git
{
	/**
	 * Git executable location
	 *
	 * @var string
	 */
	protected static $bin;

	/**
	 * Constructor
	 *
	 */

	function __construct()
	{
		if(file_exists('/usr/bin/git'))
		{self::$bin = '/usr/bin/git';}
		else{self::$bin = 'git';}
	}

	/**
	 * Sets git executable path
	 *
	 * @param string $path executable location
	 */
	public static function setBin($path)
	{
		self::$bin = $path;
	}

	/**
	 * Gets git executable path
	 */
	public static function getBin()
	{
		return self::$bin;
	}

	/**
	 * Sets up library for use in a default Windows environment
	 */
	public static function windowsMode()
    {
		self::setBin('git');
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
	public static function create($repoPath, $source = null)
	{
		return GitRepo::createNew($repoPath, $source);
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
	public static function open($repoPath)
	{
		return new GitRepo($repoPath);
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
	public static function cloneRemote($repoPath, $remote, $reference = null)
	{
		//Changed the below boolean from true to false, since this appears to be a bug when not using a reference repo.  A more robust solution may be appropriate to make it work with AND without a reference.
		return GitRepo::createNew($repoPath, $remote, false, $reference);
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
	public static function isRepo($var)
	{
		return (get_class($var) == GitRepo::class);
	}
}