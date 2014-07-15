<?php

namespace GitList\Git;

use Gitter\Client as BaseClient;

class Client extends BaseClient
{
    protected $defaultBranch;
    protected $hidden;

    public function __construct($options = null)
    {
        parent::__construct($options['path']);
        $this->setDefaultBranch($options['default_branch']);
        $this->setHidden($options['hidden']);
    }

    public function getRepositoryFromName($paths, $repo)
    {
        $repositories = $this->getRepositories($paths);
        $path = $repositories[$repo]['path'];

        return $this->getRepository($path);
    }

    /**
     * Searches for valid repositories on the specified path
     *
     * @param  array $paths Array of paths where repositories will be searched
     * @return array Found repositories, containing their name, path and description sorted
     *               by repository name
     */
    public function getRepositories($paths)
    {
        $allRepositories = array();

        foreach ($paths as $path) {
            $repositories = $this->recurseDirectory($path, $path);

            /*if (empty($repositories)) {
                throw new \RuntimeException('There are no GIT repositories in ' . $path);
            }*/

            $allRepositories = array_merge($allRepositories, $repositories);
        }

        $allRepositories = array_unique($allRepositories, SORT_REGULAR);
        uksort($allRepositories, function($k1, $k2) {
            return strtolower($k2)<strtolower($k1);
        });

        return $allRepositories;
    }

    public function setDescription($path, $desc) {
        if (is_dir($path)) {
            $isBare = file_exists($path . '/HEAD');
            $isRepository = file_exists($path . '/.git/HEAD');

            $desc_file = $path . '/.git/description';
            if ($isBare) {
                $desc_file = $path . '/description';
            }
            return file_put_contents($desc_file, $desc) === TRUE;
        }
        return false;
    }

    private function recurseDirectory($path, $store, $topLevel = true)
    {
        $dir = new \DirectoryIterator($path);

        $repositories = array();

        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }

            if (strrpos($file->getFilename(), '.') === 0) {
                continue;
            }

            if (!$file->isReadable()) {
                continue;
            }

            if ($file->isDir()) {
                $isBare = file_exists($file->getPathname() . '/HEAD');
                $isRepository = file_exists($file->getPathname() . '/.git/HEAD');

                if ($isRepository || $isBare) {
                    if (in_array($file->getPathname(), $this->getHidden())) {
                        continue;
                    }

                    if ($isBare) {
                        $description = $file->getPathname() . '/description';
                    } else {
                        $description = $file->getPathname() . '/.git/description';
                    }

                    if (file_exists($description)) {
                        $description = file_get_contents($description);
                    } else {
                        $description = null;
                    }

                    if (!$topLevel) {
                        $repoName = $file->getPathInfo()->getFilename() . '/' . $file->getFilename();
                    } else {
                        $repoName = $file->getFilename();
                    }

                    $repositories[$repoName] = array(
                        'name' => $repoName,
                        'normalizedname' => $this->toNormalizedName($repoName),
                        'path' => $file->getPathname(),
                        'store' => $store,
                        'description' => $description
                    );

                    continue;
                } else {
                    $repositories = array_merge($repositories, $this->recurseDirectory($file->getPathname(), $store, false));
                }
            }
        }

        return $repositories;
    }

    /**
     * Set default branch as a string.
     *
     * @param string $branch Name of branch to use when repo's HEAD is detached.
     */
    protected function setDefaultBranch($branch)
    {
        $this->defaultBranch = $branch;

        return $this;
    }

    /**
     * Return name of default branch as a string.
     */
    public function getDefaultBranch()
    {
        return $this->defaultBranch;
    }

    /**
     * Get hidden repository list
     *
     * @return array List of repositories to hide
     */
    protected function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Set the hidden repository list
     *
     * @param array $hidden List of repositories to hide
     */
    protected function setHidden($hidden)
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Overloads the parent::createRepository method for the correct Repository class instance
     * 
     * {@inheritdoc}
     */
    public function createRepository($path, $bare = null, $initialize = false)
    {
        if (file_exists($path . '/.git/HEAD') && !file_exists($path . '/HEAD')) {
            throw new \RuntimeException('A GIT repository already exists at ' . $path);
        }

        $repository = new Repository($path, $this);

        $repo = $repository->create($bare);
        if ($initialize) {
            // Creates an initial commit with a default readme
            $temp_dir = sys_get_temp_dir() . "/" . basename($path) . round(microtime(true) * 1000);
            mkdir($temp_dir, 0700, true);

            // Create a temp repository, a new README, add the remote and push to it
            $tmp_repository = new Repository($temp_dir, $this);
            $tmp_repository->create(false);
            $tmp_repository->setConfig("user.name", $_SERVER['REMOTE_USER']);
            $tmp_repository->setConfig("user.email", $_SERVER['REMOTE_USER']);
            file_put_contents($temp_dir . "/README.md", "This is a default README for the initial commit.");
            $tmp_repository->addAll();
            $tmp_repository->commit("Initial directory");
            $tmp_repository->addRemote("origin", $path);
            $tmp_repository->push("origin", "master");

            // Remove the temp repository
            $this->recursive_remove_directory($temp_dir);
        }

        return $repo;
    }

    public function deleteRepository($path)
    {
        if (file_exists($path . '/.git/HEAD') || file_exists($path . '/HEAD')) {
            $this->recursive_remove_directory($path);
        }
    }

    /**
     * Overloads the parent::getRepository method for the correct Repository class instance
     * 
     * {@inheritdoc}
     */
    public function getRepository($path)
    {
        if (!file_exists($path) || !file_exists($path . '/.git/HEAD') && !file_exists($path . '/HEAD')) {
            throw new \RuntimeException('There is no GIT repository at ' . $path);
        }

        return new Repository($path, $this);
    }

    public function toNormalizedName($repoName) {
        $pos = strrpos(strtolower($repoName), ".git");
        if ($pos) {
            return substr($repoName, 0, $pos);
        }
        return $repoName;
    }

    private function recursive_remove_directory($directory, $empty=FALSE) {
        if(substr($directory,-1) == '/') {
            $directory = substr($directory,0,-1);
        }
        if(!file_exists($directory) || !is_dir($directory)) {
            return FALSE;
        } elseif (is_readable($directory)) {
            $handle = opendir($directory);
            while (FALSE !== ($item = readdir($handle)))
            {
                if($item != '.' && $item != '..')
                {
                    $path = $directory.'/'.$item;
                    if(is_dir($path)) 
                    {
                        $this->recursive_remove_directory($path);
                    }else{
                        unlink($path);
                    }
                }
            }
            closedir($handle);
            if($empty == FALSE)
            {
                if(!rmdir($directory))
                {
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

}

