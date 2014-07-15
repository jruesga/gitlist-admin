<?php

namespace GitList\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class MainController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $route = $app['controllers_factory'];

        $route->get('/', function() use ($app) {
            $repositories = $app['git']->getRepositories($app['git.repos']);

            return $app['twig']->render('index.twig', array(
                'repositories'   => $repositories,
                'isadmin' => $app->isUserAdmin(),
            ));
        })->bind('homepage');

        $route->get('/repo/{repo}', function($repo) use ($app) {
            if (!$app->isUserAdmin()) {
                throw new \RuntimeException('Hey, you aren\'t and administrator! Get out of here!!!');
            }

            $repository = null;
            $action = 'create';
            if ($repo != null) {
                $repositories = $app['git']->getRepositories($app['git.repos']);
                $repository = $repositories[$repo];
                $action = 'modify';
            }

            return $app['twig']->render('repo.twig', array(
                'stores'       => $app['git.repos'],
                'repository'   => $repository,
                'initialized'  => $initialized,
                'action'       => $action,
            ));
        })->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->value('repo', null)
          ->bind('repo');

        $route->post('/repo', function(Request $request) use ($app) {
            if (!$app->isUserAdmin()) {
                throw new \RuntimeException('Hey, you aren\'t and administrator! Get out of here!!!');
            }

            $action = $request->get('action');
            $store = $request->get('store');
            $name = $request->get('name');
            $path = $request->get('path');
            $repo = $name . ".git";
            $desc = $request->get('description');
            $initialized = $request->get('initialized');
            if (($action == 'create' || $action == 'modify') && strlen($store) > 0 && strlen($name) > 0) {
                $repositories = $app['git']->getRepositories($app['git.repos']);
                if ($action == 'create' && array_key_exists($repo, $repositories)) {
                    $repository = $repositories[$repo];
                    $repository["normalizedName"] =  $app['git']->toNormalizedName($repo);
                    $repository["description"] = $desc;

                    return $app['twig']->render('repo.twig', array(
                        'stores'           => $app['git.repos'],
                        'repository'       => $repository,
                        'action'           => $action,
                        'initialized'       => $initialized,
                        'validation_error' => true,
                        'validation_msg'   => "Repository exists",
                    ));
                }

                $repo_path = $store . $repo;
                if ($action == 'create') {
                    $app['git']->createRepository($repo_path, true, $initialized);
                }
                $app['git']->setDescription($repo_path, $desc);
                return $app->redirect("/");

            } else if ($action == 'delete' && strlen($path) > 0) {
                $repo_path = $store . $repo;
                $app['git']->deleteRepository($path);
                return $app->redirect("/");

            } else {
                throw new \RuntimeException('Invalid request!');
            }
        })->bind('repo_process');

        $route->get('/refresh', function(Request $request) use ($app ) {
            # Go back to calling page
            return $app->redirect($request->headers->get('Referer'));
        })->bind('refresh');

        $route->get('{repo}/stats/{branch}', function($repo, $branch) use ($app) {
            $repository = $app['git']->getRepositoryFromName($app['git.repos'], $repo);

            if ($branch === null) {
                $branch = $repository->getHead();
            }

            $stats = $repository->getStatistics($branch);
            $authors = $repository->getAuthorStatistics($branch);

            return $app['twig']->render('stats.twig', array(
                'repo'           => $repo,
                'branch'         => $branch,
                'branches'       => $repository->getBranches(),
                'tags'           => $repository->getTags(),
                'stats'          => $stats,
                'authors'        => $authors,
            ));
        })->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('branch', $app['util.routing']->getBranchRegex())
          ->value('branch', null)
          ->convert('branch', 'escaper.argument:escape')
          ->bind('stats');

        $route->get('{repo}/{branch}/rss/', function($repo, $branch) use ($app) {
            $repository = $app['git']->getRepositoryFromName($app['git.repos'], $repo);

            if ($branch === null) {
                $branch = $repository->getHead();
            }

            $commits = $repository->getPaginatedCommits($branch);

            $html = $app['twig']->render('rss.twig', array(
                'repo'           => $repo,
                'branch'         => $branch,
                'commits'        => $commits,
            ));

            return new Response($html, 200, array('Content-Type' => 'application/rss+xml'));
        })->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('branch', $app['util.routing']->getBranchRegex())
          ->value('branch', null)
          ->convert('branch', 'escaper.argument:escape')
          ->bind('rss');

        return $route;
    }
}
