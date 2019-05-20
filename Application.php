<?php
/**
 * DronePHP (http://www.dronephp.com)
 *
 * @link      http://github.com/Pleets/DronePHP
 * @copyright Copyright (c) 2016-2018 Pleets. (http://www.pleets.org)
 * @license   http://www.dronephp.com/license
 * @author    Darío Rivera <fermius.us@gmail.com>
 */

use Drone\FileSystem\Shell;
use Drone\Loader\ClassMap;
use Drone\Mvc\Router;
use Drone\Mvc\View;
use Drone\Mvc\ModuleFactory;
use Zend\Http\Request;

/**
 * Application class
 *
 * This is the main class for mvc pattern
 */
class Application
{
    /**
     * Base path for the application
     *
     * @var string
     */
    protected $basePath;

    /**
     * Module path
     *
     * The path where all modules are located.
     *
     * @var string
     */
    protected $modulePath;

    /**
     * List of modules available
     *
     * Each module in this list must be a folder inside $modulePath
     *
     * @var array
     */
    private $modules;

    /**
     * The router instance
     *
     * @var Router
     */
    private $router;

    /**
     * Development or production mode
     *
     * @var boolean
     */
    private $devMode;

    /**
     * Returns the modulePath attribute
     *
     * @return string
     */
    public function getModulePath()
    {
        return $this->modulePath;
    }

    /**
     * Returns the modules available
     *
     * @return array
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Returns the router instance
     *
     * @return Router
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * Constructor
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function __construct(Array $config)
    {
        # start sessions
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!array_key_exists('environment', $config)) {
            throw new \InvalidArgumentException("The 'environment' key was not defined");
        }

        if (!array_key_exists('dev_mode', $config['environment'])) {
            throw new \InvalidArgumentException("The 'dev_mode' key was not defined");
        }

        $this->devMode = $config["environment"]["dev_mode"];

        if (!array_key_exists('modules', $config)) {
            throw new \InvalidArgumentException("The 'modules' key was not defined");
        }

        $this->modules = $config["modules"];

        # setting module path
        $this->modulePath = (!array_key_exists('module_path', $config['environment']))
            ? 'module'
            : $config['environment']['module_path'];

        #  setting development or production environment
        if ($this->devMode) {
            ini_set('display_errors', 1);
            error_reporting(-1);
        } else {
            ini_set('display_errors', 0);
            error_reporting(0);
        }

        if (!array_key_exists('router', $config)) {
            throw new \InvalidArgumentException("The 'router' key was not defined");
        }

        if (!array_key_exists('routes', $config["router"])) {
            throw new \InvalidArgumentException("The 'routes' key was not defined");
        }

        $this->router = new Router();

        if (!array_key_exists('base_path', $config['environment'])) {
            throw new \InvalidArgumentException("The 'base_path' key was not defined");
        }

        $this->basePath = $config["environment"]["base_path"];

        # load routes from config
        foreach ($config["router"]["routes"] as $name => $route) {
            if ($route instanceof \Zend\Router\Http\RouteInterface) {
                $this->router->addZendRoute($name, $route);
            } else {
                $this->router->addRoute([$name => $route]);

                if ($name == "defaults") {
                    $this->router->setDefaults(
                        $route["module"],
                        $route["controller"],
                        $route["view"]
                    );
                }
            }
        }
        # register autoloading functions for each module
        foreach ($this->modules as $module) {
            ClassMap::$path = $this->modulePath .
                DIRECTORY_SEPARATOR . $module .
                DIRECTORY_SEPARATOR . 'source';

            spl_autoload_register("Drone\Loader\ClassMap::autoload");
        }

        # load routes from each module
        foreach ($this->modules as $module) {
            if (file_exists($this->modulePath . "/$module/config/module.config.php")) {
                $module_config_file = require($this->modulePath . "/$module/config/module.config.php");

                if (!array_key_exists('router', $module_config_file)) {
                    throw new \RuntimeException(
                        "The 'router' key was not defined in the config file for module '$module'"
                    );
                }

                if (!array_key_exists('routes', $module_config_file["router"])) {
                    throw new \RuntimeException(
                        "The 'routes' key was not defined in the config file for module '$module'"
                    );
                }

                $this->getRouter()->addRoute($module_config_file["router"]["routes"]);
            }
        }
    }

    /**
     * Runs the application
     *
     * @return null
     */
    public function run()
    {
        $module = isset($_GET["module"])
            ? $_GET["module"]
            : $this->router->getDefaults()["module"];
        $controller = isset($_GET["controller"])
            ? $_GET["controller"]
            : $this->router->getDefaults()["controller"];
        $view  = isset($_GET["view"])
            ? $_GET["view"]
            : $this->router->getDefaults()["view"];

        $request = new Request();

        # build URI
        $uri = '';
        $uri .= !empty($module)     ? '/' . $module : "";
        $uri .= !empty($controller) ? '/' . $controller : "";
        $uri .= !empty($view)       ? '/' . $view : "";

        if (empty($uri)) {
            $uri = "/";
        }

        $request->setUri($uri);

        $match = $this->router->getZendRouter()->match($request);

        if (!is_null($match)) {
            $params = $match->getParams();
            $parts  = explode("\\", $params["controller"]);

            $module     = $parts[0];
            $controller = $parts[2];
            $view       = $params["action"];

            $this->router->setIdentifiers($module, $controller, $view);
        } else {
            $this->router->setIdentifiers($module, $controller, $view);
        }

        $this->router->setClassNameBuilder(function($module, $class) {
            return "\\$module\Controller\\$class";
        });

        $this->router->match();
        $this->controller = $this->router->getController();

        include $this->modulePath . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Module.php";

        $this->controller->setModule(ModuleFactory::create($module, [
            "config"  => $this->basePath .
                DIRECTORY_SEPARATOR . $this->modulePath .
                DIRECTORY_SEPARATOR . $module .
                DIRECTORY_SEPARATOR . 'config/module.config.php'
        ]));

        if ($this->controller->getModule()->executionIsAllowed()) {
            $result = $this->router->run();

            if ($result instanceof View) {
                $result->setParam('base', $this->basePath);

                if (is_null($result->getName())) {
                    $result->setName($router->getController()->getMethod());
                }

                $result->setPath(
                    $this->modulePath .
                    DIRECTORY_SEPARATOR . $module .
                    DIRECTORY_SEPARATOR . 'view'
                );

                $result->setCache('storage');

                if (strpos($result->getName(), '.') === false) {
                    $result->setName($controller . DIRECTORY_SEPARATOR . $result->getName());
                }

                $result->render();
            }
        }
    }
}
