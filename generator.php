<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;
use Grav\Common\Debugger;
use Grav\Common\Taxonomy;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class GeneratorPlugin
 * @package Grav\Plugin
 */
class GeneratorPlugin extends Plugin
{

    protected $displayPluginPage = false;

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onOutputRendered' => ['onOutputRendered', 0],
            'onPageInitialized' => ['onPageInitialized', 0],
        ];
    }

    public function onPageInitialized()
    {
      // Trigerring refresh of cached page and parents on page save / editing
      if ($this->isAdmin()) {

        $routes = $this->grav['pages']->routes();
        $routesToRefresh = [];

        // Admin page URL to real page URL
        $uri = $_SERVER['REQUEST_URI'];
        $uri = str_replace($this->config->get('plugins.admin.route') . "/pages", "", $uri);
        $routesToRefresh[] = $uri;

        // Getting this page directory (containing .md and assets)
        if (!array_key_exists($uri, $routes))
          return;
        $file = $routes[$uri];

        // Finding page aliases
        foreach ($routes as $route => $folder)
          if ($uri != $route && $folder == $file)
            $routesToRefresh[] = $route;

        // Finding parents
        foreach ($routes as $route => $folder)
          if (!in_array($route, $routesToRefresh))
            foreach ($routesToRefresh as $route2)
              if (strpos($route2, $route) !== false) {
                $routesToRefresh[] = $route;
                break;
              }

        // Finding parents aliases
        foreach ($routes as $route => $folder)
          if (!in_array($route, $routesToRefresh))
            foreach ($routesToRefresh as $route2)
              if ($routes[$route2] == $folder) {
                $routesToRefresh[] = $route;
                break;
              }

        foreach ($routesToRefresh as $route) {
          $uri = "http://" . $_SERVER['HTTP_HOST'] . $route;
          $s = curl_init();
          curl_setopt($s, CURLOPT_URL, $uri);
          curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
          $a = curl_exec($s);
          curl_close($s);
        }
      }
    }

    // Save cached version of page
    public function onOutputRendered()
    {
        if ($this->isAdmin())
          return ;

        $filename = $this->getCacheFilename();

        $dir = dirname($filename);
        $error = false;
        if (!is_dir($dir)) {
          $res = @mkdir($dir, 0775, true);
          if (!$res)
            $error = true;
        }
        if (!$error) {
            @file_put_contents($filename, $this->grav->output . '<!-- Generated: ' . date('c') . ' -->');
        }
    }

    protected function getCacheFilename() {
        $filename = array();
        $uri = trim($_SERVER['REQUEST_URI'], '/');

        $filename[] = $this->config->get('plugins.generator.destination_folder');
        if ($uri)
          $filename[] =  $uri;

        $filename[] = 'index.html';
        $filename = implode('/', $filename);

        $filename = str_replace("//", "/", $filename);

        return $filename;
    }
}
